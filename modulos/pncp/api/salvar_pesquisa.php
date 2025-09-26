<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['usuario_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'msg' => 'Usuário não autenticado']);
  exit;
}

require __DIR__ . '/../../../config/db_precos.php'; // Onde estão listas, lista_itens, lista_consolidada
$pdo = ConexaoPrecos::getInstance();

// Conexão separada para LOGS (banco contratos_agudos)
require __DIR__ . '/../../../config/db.php'; // ajuste se necessário
$pdoLogs = Conexao::getInstance();

function logSistema(PDO $pdoLogs, $usuarioId, $acao)
{
  try {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $st = $pdoLogs->prepare("INSERT INTO logs (usuario_id, acao, ip) VALUES (?,?,?)");
    $st->execute([$usuarioId, $acao, $ip]);
  } catch (Throwable $e) {
    // não interrompe fluxo
  }
}

$body          = json_decode(file_get_contents('php://input'), true) ?: [];
$nome          = trim($body['nome'] ?? '');
$listaId       = !empty($body['lista_id']) ? (int)$body['lista_id'] : null;
$itensRaw      = $body['itens_raw'] ?? [];     // [{descricao, quantidade, valor_unitario, origem}]
$consolidados  = $body['consolidados'] ?? [];  // [{descricao, quantidade, valor_medio, referencias}]

if ($listaId === null && $nome === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'msg' => 'Informe nome da lista ou selecione uma existente.']);
  exit;
}
if (!is_array($consolidados) || count($consolidados) === 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'msg' => 'Nenhum item consolidado recebido.']);
  exit;
}

$usuarioId = $_SESSION['usuario_id'];

$pdo->beginTransaction();
try {
  // 1) Criar/Obter lista
  if ($listaId) {
    $st = $pdo->prepare("SELECT id, nome FROM listas WHERE id=?");
    $st->execute([$listaId]);
    $existente = $st->fetch(PDO::FETCH_ASSOC);
    if (!$existente) {
      throw new Exception("Lista #$listaId não encontrada");
    }
    // Atualiza nome se veio um novo
    if ($nome !== '') {
      $up = $pdo->prepare("UPDATE listas SET nome=? WHERE id=?");
      $up->execute([$nome, $listaId]);
    }
    $acaoLog = "Atualizou lista existente #$listaId (" . ($nome ?: $existente['nome']) . ")";
  } else {
    $ins = $pdo->prepare("INSERT INTO listas (nome, usuario_id) VALUES (?, ?)");
    $ins->execute([$nome, $usuarioId]);
    $listaId = (int)$pdo->lastInsertId();
    $acaoLog = "Criou nova lista #$listaId ($nome)";
  }

  // 2) Inserir ITENS BRUTOS (append/merge; NUNCA apaga os anteriores)
  $chavesAfetadas = []; // lower(descricao) das descrições afetadas
  if (is_array($itensRaw) && count($itensRaw) > 0) {
    $insRaw = $pdo->prepare("
      INSERT INTO lista_itens (lista_id, descricao, quantidade, valor_unitario, origem)
      VALUES (?,?,?,?,?)
    ");
    foreach ($itensRaw as $r) {
      $desc   = trim((string)($r['descricao'] ?? ''));
      if ($desc === '') continue;
      $qtd    = (float)($r['quantidade'] ?? 1);
      $vu     = (float)($r['valor_unitario'] ?? 0);
      $origem = trim((string)($r['origem'] ?? ''));
      $insRaw->execute([$listaId, $desc, $qtd, $vu, $origem]);
      $chavesAfetadas[strtolower($desc)] = true;
    }
  }

  // 3) Recalcular CONSOLIDADO para as descrições afetadas (com base em lista_itens)
  if (!empty($chavesAfetadas)) {
    $keys = array_keys($chavesAfetadas);

    // pega médias e refs por LOWER(descricao)
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $params = array_merge([$listaId], $keys);
    $sqlMedia = "
      SELECT LOWER(descricao) AS k,
             MIN(descricao)   AS descricao_exemplo,
             AVG(valor_unitario) AS media,
             GROUP_CONCAT(DISTINCT origem ORDER BY origem SEPARATOR ', ') AS refs
      FROM lista_itens
      WHERE lista_id = ?
        AND LOWER(descricao) IN ($placeholders)
      GROUP BY LOWER(descricao)
    ";
    $stM = $pdo->prepare($sqlMedia);
    $stM->execute($params);
    $recs = $stM->fetchAll(PDO::FETCH_ASSOC);

    foreach ($recs as $rec) {
      $k     = $rec['k'];
      $media = (float)$rec['media'];
      $refs  = $rec['refs'] ?? '';
      $descEx = $rec['descricao_exemplo'];

      // verifica se já existe consolidado para esta chave
      $stFind = $pdo->prepare("SELECT id, descricao, quantidade FROM lista_consolidada WHERE lista_id=? AND LOWER(descricao)=? LIMIT 1");
      $stFind->execute([$listaId, $k]);
      $ex = $stFind->fetch(PDO::FETCH_ASSOC);

      if ($ex) {
        // preserva a quantidade já definida pelo usuário
        $qtdAtual = (float)$ex['quantidade'];
        $upd = $pdo->prepare("UPDATE lista_consolidada SET quantidade=?, referencias=?, atualizado_em=NOW(), acessado_em=NOW() WHERE id=?");
        $upd->execute([$media, $refs, $ex['id']]);
      } else {
        // cria novo consolidado com quantidade 0 (usuário ajusta depois)
        $insC = $pdo->prepare("INSERT INTO lista_consolidada (lista_id, descricao, quantidade, valor_medio, referencias, acessado_em) VALUES (?,?,?,?,?, NOW())");
        $insC->execute([$listaId, $descEx, 0, $media, $refs]);
      }
    }
  }

  // 4) Aplicar ajustes da TABELA CONSOLIDADA enviada pelo usuário (quantidades e, opcionalmente, refs)
  if (is_array($consolidados) && count($consolidados) > 0) {
    foreach ($consolidados as $c) {
      $desc = trim((string)($c['descricao'] ?? ''));
      if ($desc === '') continue;
      $qtd  = (float)($c['quantidade'] ?? 0);
      $refs = (string)($c['referencias'] ?? '');

      // atualiza quantidade e pode atualizar refs (mescla: mantemos os da média + acrescentamos os do usuário)
      // estratégia simples: se refs enviada não está vazia, sobrescreve; senão mantém as existentes
      $stFind = $pdo->prepare("SELECT id, referencias FROM lista_consolidada WHERE lista_id=? AND LOWER(descricao)=? LIMIT 1");
      $stFind->execute([$listaId, strtolower($desc)]);
      $ex = $stFind->fetch(PDO::FETCH_ASSOC);

      if ($ex) {
        $refsFinal = ($refs !== '') ? $refs : ($ex['referencias'] ?? '');
        $upd = $pdo->prepare("UPDATE lista_consolidada SET quantidade=?, referencias=?, atualizado_em=NOW(), acessado_em=NOW() WHERE id=?");
        $upd->execute([$qtd, $refsFinal, $ex['id']]);
      } else {
        // se não houver consolidado (caso sem itens brutos), cria com valor_medio 0 — usuário poderá editar depois
        $insC = $pdo->prepare("INSERT INTO lista_consolidada (lista_id, descricao, quantidade, valor_medio, referencias, acessado_em) VALUES (?,?,?,?,?, NOW())");
        $insC->execute([$listaId, $desc, $qtd, (float)($c['valor_medio'] ?? 0), $refs]);
      }
    }
  }

  $pdo->commit();

  // 5) LOG
  logSistema($pdoLogs, $usuarioId, $acaoLog . " | novos brutos: " . count($itensRaw) . " | consolidados informados: " . count($consolidados));

  echo json_encode(['ok' => true, 'lista_id' => $listaId]);
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok' => false, 'msg' => 'Erro ao salvar', 'det' => $e->getMessage()]);
}
