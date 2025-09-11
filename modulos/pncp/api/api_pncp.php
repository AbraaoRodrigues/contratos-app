<?php
// Sempre JSON:
header('Content-Type: application/json; charset=UTF-8');

// Transformar *qualquer* erro/aviso em JSON (evita "<br /><b>Warning...")
ini_set('display_errors', '0');
set_error_handler(function ($severity, $message, $file, $line) {
  http_response_code(500);
  echo json_encode(['error' => [
    'code' => 'PHP_WARNING',
    'message' => $message,
    'file' => basename($file),
    'line' => $line
  ]]);
  exit;
});
set_exception_handler(function ($ex) {
  http_response_code(500);
  echo json_encode(['error' => [
    'code' => 'PHP_EXCEPTION',
    'message' => $ex->getMessage()
  ]]);
  exit;
});
register_shutdown_function(function () {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
    http_response_code(500);
    echo json_encode(['error' => [
      'code' => 'PHP_FATAL',
      'message' => $e['message'],
      'file' => basename($e['file']),
      'line' => $e['line']
    ]]);
  }
});

function httpGetAny($url)
{
  // cURL se disponível, senão stream
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTPHEADER => ['accept: application/json, */*']
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$body, $code, $err];
  } else {
    $ctx = stream_context_create([
      'http' => ['method' => 'GET', 'timeout' => 30, 'header' => "accept: application/json\r\n"],
      'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true]
    ]);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) $code = (int)$m[1];
    $err = $body === false ? 'stream_context error' : null;
    return [$body, $code, $err];
  }
}

function isoToBr($iso)
{
  if (!$iso) return '';
  $d = substr($iso, 0, 10);
  $p = explode('-', $d);
  return count($p) === 3 ? "{$p[2]}/{$p[1]}/{$p[0]}" : $iso;
}

$acao = $_GET['acao'] ?? '';

if ($acao === 'listar') {
  $dataInicial = $_GET['dataInicial'] ?? '';
  $dataFinal   = $_GET['dataFinal']   ?? '';
  $modalidade  = $_GET['modalidade']  ?? '6';
  $uf          = $_GET['uf']          ?? '';
  $pagina      = max(1, (int)($_GET['pagina'] ?? 1));
  $tam         = min(100, max(10, (int)($_GET['tamanhoPagina'] ?? 50)));

  // Valida período <= 365 dias
  $diObj = new DateTime($dataInicial);
  $dfObj = new DateTime($dataFinal);
  $diff  = $diObj->diff($dfObj)->days;
  if ($diff > 365) {
    echo json_encode(['error' => [
      'code' => 'PERIODO_MAX',
      'message' => 'O período informado excede 365 dias.',
      'hint' => 'Reduza para ≤ 365 dias.'
    ]]);
    exit;
  }

  // AAAAMMDD
  $di = preg_replace('/[^0-9]/', '', $dataInicial);
  $df = preg_replace('/[^0-9]/', '', $dataFinal);
  if (strlen($di) !== 8 || strlen($df) !== 8) {
    echo json_encode(['error' => ['code' => 'FORMATO_DATA', 'message' => 'Use YYYY-MM-DD.']]);
    exit;
  }

  $qs = [
    "dataInicial={$di}",
    "dataFinal={$df}",
    "codigoModalidadeContratacao={$modalidade}",
    "pagina={$pagina}",
    "tamanhoPagina={$tam}"
  ];
  if ($uf) $qs[] = "uf={$uf}";
  $url = "https://pncp.gov.br/api/consulta/v1/contratacoes/publicacao?" . implode('&', $qs);

  [$raw, $code, $err] = httpGetAny($url);
  if ($err || $code >= 400 || $raw === false) {
    $apiMsg = null;
    if ($raw) {
      $try = json_decode($raw, true);
      if (isset($try['message'])) $apiMsg = $try['message'];
    }
    echo json_encode([
      'error' => [
        'code' => "HTTP_$code",
        'message' => $apiMsg ?: 'Erro na consulta ao PNCP.',
        'hint' => ($code === 422 ? 'Verifique intervalo de datas (≤365 dias) e parâmetros.' : null)
      ],
      'debugUrl' => $url,
      'httpCode' => $code
    ]);
    exit;
  }

  $j = json_decode($raw, true);
  $data = $j['data'] ?? [];

  // Normalização mínima
  $out = [];
  foreach ($data as $row) {
    $num        = $row['numeroControlePNCP'] ?? '';
    $statusNome = $row['situacaoCompraNome'] ?? '';
    $statusAgr  = '';

    $ab = $row['dataAberturaProposta'] ?? null;
    $en = $row['dataEncerramentoProposta'] ?? null;
    if ($ab && $en) {
      $agora = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
      $dab   = new DateTime($ab);
      $den   = new DateTime($en);
      if ($agora >= $dab && $agora <= $den) $statusAgr = 'RECEBENDO';
      elseif ($agora > $den) $statusAgr = 'JULGAMENTO';
    }
    if (!$statusAgr && $statusNome) {
      if (stripos($statusNome, 'encerr') !== false || stripos($statusNome, 'homolog') !== false || stripos($statusNome, 'adjudic') !== false || stripos($statusNome, 'finaliz') !== false) $statusAgr = 'ENCERRADA';
      elseif (stripos($statusNome, 'julg') !== false) $statusAgr = 'JULGAMENTO';
      elseif (stripos($statusNome, 'abert') !== false || stripos($statusNome, 'receb') !== false) $statusAgr = 'RECEBENDO';
    }

    $out[] = [
      'numeroControlePNCP' => $num,
      'objeto'             => $row['objetoCompra'] ?? $row['descricaoObjeto'] ?? '',
      'orgao'              => $row['orgaoEntidade']['razaoSocial'] ?? ($row['orgaoEntidade']['razao_social'] ?? ''),
      'uf'                 => $row['unidadeOrgao']['ufSigla'] ?? ($row['unidadeOrgao']['ufNome'] ?? ''),
      'statusNome'         => $statusNome,
      'statusAgrupado'     => $statusAgr,
      'dataPublicacao'     => isoToBr($row['dataPublicacaoPncp'] ?? $row['dataPublicacao'] ?? ''),
      'linkPublico'        => $num ? "https://pncp.gov.br/app/contratacoes/visualizacao/{$num}" : ''
    ];
  }

  echo json_encode(['data' => $out, 'debugUrl' => $url, 'pagina' => $pagina, 'tamanhoPagina' => $tam], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($acao === 'itens') {
  $id = $_GET['numeroControlePNCP'] ?? '';
  // Espera CNPJ-1-SEQUENCIAL/ANO
  if (!preg_match('/^(\d{14})-1-(\d{6})\/(\d{4})$/', $id, $m)) {
    echo json_encode(['error' => ['code' => 'ID_INVALIDO', 'message' => 'numeroControlePNCP inválido']]);
    exit;
  }
  $cnpj = $m[1];
  $seq  = ltrim($m[2], '0');
  $ano  = $m[3];

  $urls = [
    "https://pncp.gov.br/api/pncp/v1/orgaos/{$cnpj}/compras/{$ano}/{$seq}/itens",
    "https://pncp.gov.br/pncp-api/v1/orgaos/{$cnpj}/compras/{$ano}/{$seq}/itens"
  ];

  $ok = null;
  $tried = [];
  foreach ($urls as $u) {
    $tried[] = $u;
    [$raw, $code, $err] = httpGetAny($u);
    if (!$err && $code < 400 && $raw) {
      $ok = $raw;
      break;
    }
  }
  if (!$ok) {
    echo json_encode(['error' => ['code' => 'SEM_ACESSO', 'message' => 'Itens não acessíveis sem token.'], 'tried' => $tried]);
    exit;
  }

  $j = json_decode($ok, true);
  if (!is_array($j)) {
    echo json_encode(['error' => ['code' => 'FORMATO_ITENS', 'message' => 'Retorno inesperado']]);
    exit;
  }

  $out = [];
  foreach ($j as $it) {
    $out[] = [
      'descricao' => $it['descricao'] ?? ($it['descricaoItem'] ?? ''),
      'quantidade' => $it['quantidade'] ?? ($it['quantidadeEstimado'] ?? ''),
      'valorUnitarioEstimado' => $it['valorUnitarioEstimado'] ?? ($it['valorUnitario'] ?? null)
    ];
  }
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
  exit;
}

echo json_encode(['error' => ['code' => 'ACAO_INVALIDA', 'message' => 'Ação inválida']]);
