<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

// se não estiver logado, retorna JSON de erro
if (!isset($_SESSION['usuario_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'msg' => 'Usuário não autenticado']);
  exit;
}

require __DIR__ . '/db.php';
$pdo = Conexao::getInstance();


$body = json_decode(file_get_contents('php://input'), true) ?: [];
$nome = trim($body['nome'] ?? '');
$itens = $body['itens'] ?? [];

if ($nome === '' || !is_array($itens) || count($itens) === 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'msg' => 'Informe nome e ao menos 1 item.']);
  exit;
}

$pdo->beginTransaction();
try {
  // Verifica se já existe lista com mesmo nome
  $stCheck = $pdo->prepare('SELECT id FROM listas WHERE nome = ?');
  $stCheck->execute([$nome]);
  $existente = $stCheck->fetch();

  if ($existente && empty($body['forcarUpdate'])) {
    echo json_encode([
      'ok' => false,
      'duplicado' => true,
      'msg' => 'Lista já existente',
      'lista_id' => $existente['id']
    ]);
    $pdo->rollBack();
    exit;
  }
  $usuarioId = $_SESSION['usuario_id'];

  if ($existente && !empty($body['forcarUpdate'])) {
    $listaId = (int)$existente['id'];
    // 🔄 Remove itens antigos (mas mantém logs!)
    $pdo->prepare('DELETE FROM lista_itens WHERE lista_id=?')->execute([$listaId]);
  } else {
    // Nova lista
    $st = $pdo->prepare('INSERT INTO listas (nome, usuario_id) VALUES (?, ?)');
    $st->execute([$nome, $usuarioId]);
    $listaId = (int)$pdo->lastInsertId();
  }

  // Reinsere itens atualizados
  $stI = $pdo->prepare('INSERT INTO lista_itens
    (lista_id, descricao, quantidade, valor_unitario, origem)
    VALUES (?,?,?,?,?)');

  // Logs sempre acumulam (não apagamos nada!)
  $stLog = $pdo->prepare('INSERT INTO lista_logs
    (lista_id, descricao, quantidade, valor_unitario, valor_total, referencias, criado_em)
    VALUES (?,?,?,?,?,?,NOW())');

  foreach ($itens as $it) {
    $stI->execute([
      $listaId,
      $it['descricao'],
      $it['quantidade'],
      $it['valor_medio'],
      $it['referencias']
    ]);

    $total = $it['quantidade'] * $it['valor_medio'];
    $stLog->execute([
      $listaId,
      $it['descricao'],
      $it['quantidade'],
      $it['valor_medio'],
      $total,
      $it['referencias']
    ]);
  }

  $pdo->commit();
  echo json_encode([
    'ok' => true,
    'lista_id' => $listaId,
    'atualizado' => !empty($existente)
  ]);
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok' => false, 'msg' => 'Erro ao salvar', 'det' => $e->getMessage()]);
}
