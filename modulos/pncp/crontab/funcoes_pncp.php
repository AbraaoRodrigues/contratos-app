<?php
// funcoes_pncp.php
// Funções utilitárias para integração com PNCP
// Substitua / adapte conforme sua estrutura — o arquivo foi escrito para ser "drop-in".

// Segurança: timezone
if (!ini_get('date.timezone')) {
  date_default_timezone_set('America/Sao_Paulo');
}

// Se logar() não existir (cron às vezes já define), definimos um fallback que grava no mesmo arquivo.
/*if (!function_exists('logar')) {
  function logar($mensagem)
  {
    // tenta escrever no mesmo caminho esperado pelos crons
    $logFile = __DIR__ . '/logs/cron_full_cache.log';
    $linha = "[" . date('Y-m-d H:i:s') . "] " . $mensagem . PHP_EOL;
    // ecoa para stdout (útil ao rodar CLI)
    echo $linha;
    // garante diretório
    $dir = dirname($logFile);
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    @file_put_contents($logFile, $linha, FILE_APPEND);
  }
}*/

// Registrar falha simples
function registrarFalha(PDO $pdo, string $motivo, string $numeroControlePNCP, int $tentativas = 0)
{
  try {
    $st = $pdo->prepare("INSERT INTO cache_pncp_falhas
            (numeroControlePNCP, motivo, tentativas, status, criado_em)
            VALUES (?, ?, ?, 'pendente', NOW())");
    $st->execute([$numeroControlePNCP, $motivo, $tentativas]);
  } catch (Throwable $e) {
    // não interromper o fluxo, apenas log local
    logar("⚠️ registrarFalha erro: " . $e->getMessage());
  }
}

/**
 * consultarApi
 * - Retorna array (json) em caso de 200
 * - Retorna null em caso de resposta HTTP 400 (indicando janela inválida / params)
 * - Retorna false em caso de erro de cURL / timeout (para re-tentativa externa)
 */
function consultarApi(string $url, int $maxTentativas = 4)
{
  $uas = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 13_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16 Safari/605.1.15"
  ];

  for ($tent = 1; $tent <= $maxTentativas; $tent++) {
    $ua = $uas[array_rand($uas)];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS      => 5,
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_TIMEOUT        => 45,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_ENCODING       => "",
      CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
      CURLOPT_HTTPHEADER     => [
        "Accept: application/json",
        "User-Agent: $ua"
      ],
    ]);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
      logar("⚠️ cURL tent.$tent: $err");
      // continue para retry
    } else {
      if ($status === 200) {
        $json = json_decode($res, true);
        if (is_array($json)) return $json;
        logar("⚠️ Resposta não-JSON tent.$tent: " . substr($res, 0, 300));
      } elseif ($status === 400) {
        // bad request → retornar null (janela inválida)
        logar("⚠️ HTTP 400 (Bad Request) — ignorando params: $url");
        return null;
      } elseif (in_array($status, [429, 502, 503])) {
        logar("⚠️ HTTP $status — retry tent.$tent");
      } elseif ($status === 204) {
        // sem conteúdo - tratar como sucesso mas sem data
        logar("ℹ️ HTTP 204 (No Content) — $url");
        return ['data' => []];
      } else {
        logar("⚠️ HTTP $status — " . substr($res, 0, 300));
      }
    }

    // backoff com jitter
    $sleep = min(8, 2 ** $tent) + mt_rand(0, 1000) / 1000;
    usleep((int)($sleep * 1_000_000));
  }

  return false;
}

/**
 * baixarJson - wrapper simples
 * Tenta a consulta e retorna o 'data' quando apropriado.
 */
function baixarJson(string $url)
{
  $r = consultarApi($url);
  if ($r === false) return false;
  if ($r === null) return null;
  // alguns endpoints retornam array diretamente, outros retornam ['data'=>[...] ]
  if (isset($r['data'])) return $r['data'];
  return $r;
}

/**
 * salvarProcesso - insere/atualiza processo na tabela cache_pncp_processos
 * Mantém redundância de colunas para facilitar busca (modalidade_id, modalidade_nome, situacao_nome, orgao, uf, dataPublicacao)
 */

function salvarProcesso(PDO $pdo, array $proc): bool
{
  $numero = $proc['numeroControlePNCP'] ?? null;
  if (!$numero) {
    logar("⚠️ salvarProcesso chamado sem numeroControlePNCP");
    return false;
  }

  // Normaliza campos obrigatórios (aceita null se não existir)
  $objeto = $proc['objetoCompra'] ?? '(sem objeto)';
  $orgao  = $proc['orgaoEntidade']['razaoSocial'] ?? ($proc['orgao'] ?? 'N/D');
  $uf     = $proc['unidadeOrgao']['ufSigla'] ?? ($proc['uf'] ?? 'N/D');
  $status = $proc['situacaoCompraNome'] ?? ($proc['status'] ?? 'N/D');

  $dataPub = !empty($proc['dataPublicacaoPncp'])
    ? substr($proc['dataPublicacaoPncp'], 0, 10)
    : (!empty($proc['dataInclusao']) ? substr($proc['dataInclusao'], 0, 10) : null);

  $dataAbe = !empty($proc['dataAberturaProposta'])
    ? substr($proc['dataAberturaProposta'], 0, 10)
    : null;

  $dataEnc = !empty($proc['dataEncerramentoProposta'])
    ? substr($proc['dataEncerramentoProposta'], 0, 10)
    : null;

  $modalidadeId   = $proc['modalidadeId']   ?? null;
  $modalidadeNome = $proc['modalidadeNome'] ?? null;

  $sql = "INSERT INTO cache_pncp_processos
              (numeroControlePNCP, objeto, orgao, uf, modalidade_nome, modalidade_id,status,
               dataPublicacao, dataAbertura, dataEncerramento, json_original)
            VALUES
              (:num, :obj, :orgao, :uf, :mod_nome, :mod_id,:status,
               :dPub, :dAbe, :dEnc, :json)
            ON DUPLICATE KEY UPDATE
              objeto = VALUES(objeto),
              orgao = VALUES(orgao),
              uf = VALUES(uf),
              modalidade_nome = VALUES(modalidade_nome),
              modalidade_id = VALUES(modalidade_id),
              status = VALUES(status),
              dataPublicacao = VALUES(dataPublicacao),
              dataAbertura = VALUES(dataAbertura),
              dataEncerramento = VALUES(dataEncerramento),
              json_original = VALUES(json_original),
              atualizado_em = CURRENT_TIMESTAMP";

  $stmt = $pdo->prepare($sql);

  $ok = $stmt->execute([
    ':num'   => $numero,
    ':obj'   => $objeto,
    ':orgao' => $orgao,
    ':uf'    => $uf,
    ':mod_nome'   => $modalidadeNome, // usamos só o nome; se precisar o ID é fácil incluir
    ':mod_id'   => $modalidadeId,
    ':status' => $status,
    ':dPub'  => $dataPub,
    ':dAbe'  => $dataAbe,
    ':dEnc'  => $dataEnc,
    ':json'  => json_encode($proc, JSON_UNESCAPED_UNICODE)
  ]);

  if (!$ok) {
    $err = $stmt->errorInfo();
    logar("❌ Erro ao salvar processo $numero — " . ($err[2] ?? 'desconhecido'));
  }

  logar("💾 Processo $numero salvo/atualizado");
  return true;
}


/**
 * salvarItensDoProcesso - utiliza endpoint PNCP paginado (estrutura /pncp/v1/orgaos/{cnpj}/compras/{ano}/{seq}/itens)
 * - remove itens antigos antes de inserir
 * - registra falhas quando não encontra
 */
function salvarItensDoProcesso(PDO $pdo, string $numeroControlePNCP): bool
{
  try {
    // Quebra numeroControlePNCP → cnpj-orgao, modalidade, sequencial/ano
    $partes = explode('-', $numeroControlePNCP);
    if (count($partes) < 3) {
      throw new Exception("Formato inválido de numeroControlePNCP: $numeroControlePNCP");
    }

    $cnpj = $partes[0];
    list($sequencial, $ano) = explode('/', $partes[2]);
    $sequencial = (int)$sequencial;

    // Monta URL dos itens
    $urlItens = "https://pncp.gov.br/api/pncp/v1/orgaos/$cnpj/compras/$ano/$sequencial/itens?pagina=1&tamanhoPagina=100";

    $itens = baixarJson($urlItens);
    if (!$itens || !is_array($itens)) {
      throw new Exception("Itens não encontrados para $numeroControlePNCP");
    }

    // SQL compatível com a tabela atual
    $sql = "INSERT INTO cache_pncp_itens
                  (numeroControlePNCP, numeroItem, descricao, quantidade,
                   valorUnitarioEstimado, valorUnitarioHomologado, sigiloso, json_original)
                VALUES
                  (:numeroControlePNCP, :numeroItem, :descricao, :quantidade,
                   :valorUnitarioEstimado, :valorUnitarioHomologado, :sigiloso, :json)
                ON DUPLICATE KEY UPDATE
                  descricao = VALUES(descricao),
                  quantidade = VALUES(quantidade),
                  valorUnitarioEstimado = VALUES(valorUnitarioEstimado),
                  valorUnitarioHomologado = VALUES(valorUnitarioHomologado),
                  sigiloso = VALUES(sigiloso),
                  json_original = VALUES(json_original),
                  atualizado_em = CURRENT_TIMESTAMP";

    $stmt = $pdo->prepare($sql);

    foreach ($itens as $it) {
      $stmt->execute([
        ':numeroControlePNCP'    => $numeroControlePNCP,
        ':numeroItem'            => $it['numeroItem'] ?? null,
        ':descricao'             => $it['descricao'] ?? '',
        ':quantidade'            => $it['quantidade'] ?? 0,
        ':valorUnitarioEstimado' => $it['valorUnitarioEstimado'] ?? null,
        ':valorUnitarioHomologado' => $it['valorUnitarioHomologado'] ?? null,
        ':sigiloso'              => !empty($it['orcamentoSigiloso']) ? 1 : 0,
        ':json'                  => json_encode($it, JSON_UNESCAPED_UNICODE)
      ]);
    }

    logar("💾 Itens do processo $numeroControlePNCP salvos/atualizados (" . count($itens) . ")");
    return true;
  } catch (Throwable $e) {
    logar("⚠️ Erro ao salvar itens de $numeroControlePNCP — " . $e->getMessage());
    return false;
  }
}


/**
 * salvarItensDoProcesso_alternativo
 * - tenta o endpoint /consulta/v1/itens/{numeroControlePNCP}
 * - alguns números de controle funcionam melhor neste endpoint
 */
function salvarItensDoProcesso_alternativo(PDO $pdo, string $numeroControlePNCP): bool
{
  $url = "https://pncp.gov.br/api/consulta/v1/itens/{$numeroControlePNCP}";
  $r = consultarApi($url);

  if ($r === false) {
    logar("⚠️ Alternativo: cURL/timeout ao buscar itens de $numeroControlePNCP");
    registrarFalha($pdo, $numeroControlePNCP, "Falha ao consultar itens (alternativo cURL)");
    return false;
  }
  if ($r === null) {
    logar("⚠️ Alternativo: HTTP 400 para itens de $numeroControlePNCP");
    registrarFalha($pdo, $numeroControlePNCP, "Falha ao consultar itens (alternativo 400)");
    return false;
  }

  $dados = $r['data'] ?? $r;
  if (!is_array($dados) || count($dados) === 0) {
    logar("⚠️ Alternativo: nenhum item retornado para $numeroControlePNCP");
    registrarFalha($pdo, $numeroControlePNCP, "Falha ao consultar itens (alternativo vazio)");
    return false;
  }

  try {
    $pdo->prepare("DELETE FROM cache_pncp_itens WHERE numeroControlePNCP = ?")->execute([$numeroControlePNCP]);
  } catch (Throwable $e) {
    logar("⚠️ Alternativo: falha ao deletar itens antigos: " . $e->getMessage());
  }

  $ins = $pdo->prepare("INSERT INTO cache_pncp_itens
        (numeroControlePNCP, numeroItem, descricao, quantidade, valorUnitarioEstimado, valorUnitarioHomologado, json_original, dataAbertura)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

  foreach ($dados as $it) {
    $numItem = $it['numeroItem'] ?? ($it['numero'] ?? null);
    $desc = $it['descricao'] ?? ($it['descricaoItem'] ?? '');
    $qtd = is_numeric($it['quantidade'] ?? null) ? $it['quantidade'] : null;
    $vEst = $it['valorUnitarioEstimado'] ?? null;
    $vHom = $it['valorUnitarioHomologado'] ?? null;
    $jsonIt = json_encode($it, JSON_UNESCAPED_UNICODE);

    try {
      $ins->execute([$numeroControlePNCP, $numItem, $desc, $qtd, $vEst, $vHom, $jsonIt]);
    } catch (Throwable $e) {
      logar("⚠️ Alternativo: erro inserindo item: " . $e->getMessage());
    }
  }

  logar("📦 Alternativo: itens salvos para $numeroControlePNCP (" . count($dados) . " itens)");
  return true;
}
