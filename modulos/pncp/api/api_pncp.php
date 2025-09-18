<?php
header('Content-Type: application/json; charset=utf-8');

$acao = $_GET['acao'] ?? '';

// === LISTAR PROCESSOS ===
if ($acao === 'listar') {
  $dataInicial = $_GET['dataInicial'] ?? '';
  $dataFinal   = $_GET['dataFinal']   ?? '';
  $modalidade  = $_GET['modalidade']  ?? '6';
  $uf          = $_GET['uf']          ?? '';
  $pagina      = max(1, (int)($_GET['pagina'] ?? 1));
  $tam         = min(100, max(10, (int)($_GET['tamanhoPagina'] ?? 50)));

  // Valida período ≤ 365 dias
  try {
    $diObj = new DateTime($dataInicial);
    $dfObj = new DateTime($dataFinal);
  } catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => [
      'code' => 'DATA_INVALIDA',
      'message' => 'Data inicial/final inválida.',
      'hint' => 'Use o formato YYYY-MM-DD.'
    ]], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $diff  = $diObj->diff($dfObj)->days;
  if ($diff > 365) {
    http_response_code(422);
    echo json_encode(['error' => [
      'code' => 'PERIODO_MAX',
      'message' => 'O período informado excede 365 dias.',
      'hint' => 'Reduza para ≤ 365 dias.'
    ]], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Monta query
  $qs = [
    'dataInicial' => str_replace('-', '', $dataInicial),
    'dataFinal'   => str_replace('-', '', $dataFinal),
    'codigoModalidadeContratacao' => $modalidade,
    'pagina' => $pagina,
    'tamanhoPagina' => $tam,
  ];
  if ($uf !== '') $qs['uf'] = $uf;

  $url = 'https://pncp.gov.br/api/consulta/v1/contratacoes/publicacao?' . http_build_query($qs);

  // cURL
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_CONNECTTIMEOUT => 20,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
  ]);
  $resp = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  if ($resp === false) {
    http_response_code(502);
    echo json_encode(['error' => [
      'code' => 'HTTP_CLIENT_ERROR',
      'message' => 'Falha ao contatar a API do PNCP.',
      'hint' => $err ?: 'Sem detalhes',
    ], 'debugUrl' => $url], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Verifica se é JSON válido
  $dados = json_decode($resp, true);
  if ($dados === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(502);
    echo json_encode([
      'error' => [
        'code'    => "HTTP_$http",
        'message' => 'A API do PNCP retornou uma resposta inválida (não-JSON).',
        'hint'    => 'Pode ser instabilidade no serviço PNCP.'
      ],
      'debugUrl' => $url
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($http !== 200) {
    http_response_code($http);
    echo json_encode([
      'error' => [
        'code'    => "HTTP_$http",
        'message' => $dados['message'] ?? 'Erro na API do PNCP.',
        'hint'    => $dados['path'] ?? null,
      ],
      'debugUrl' => $url
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // OK
  $lista = is_array($dados['data'] ?? null) ? $dados['data'] : [];
  $totalReg   = isset($dados['totalRegistros']) ? (int)$dados['totalRegistros'] : (int)($dados['total'] ?? 0);
  if ($totalReg === 0) $totalReg = count($lista);
  $paginaOut  = isset($dados['pagina']) ? (int)$dados['pagina'] : $pagina;
  $tamOut     = isset($dados['tamanhoPagina']) ? (int)$dados['tamanhoPagina'] : $tam;

  echo json_encode([
    'data'            => $lista,
    'totalRegistros'  => $totalReg,
    'pagina'          => $paginaOut,
    'tamanhoPagina'   => $tamOut,
    'debugUrl'        => $url
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// === LISTAR ITENS DE UM PROCESSO ===
if ($acao === 'itens') {
  $num = $_GET['numeroControlePNCP'] ?? '';
  if (!$num) {
    http_response_code(400);
    echo json_encode(['error' => [
      'code' => 'SEM_ID',
      'message' => 'Informe numeroControlePNCP'
    ]], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $num = str_replace('%2F', '/', $num);
  $url = 'https://pncp.gov.br/api/consulta/v1/contratacoes/' . $num . '/itens';

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
  ]);
  $resp = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  if ($resp === false) {
    http_response_code(502);
    echo json_encode(['error' => [
      'code' => 'HTTP_CLIENT_ERROR',
      'message' => 'Falha ao contatar a API do PNCP.',
      'hint' => $err ?: 'Sem detalhes',
    ], 'debugUrl' => $url], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Trata 404 → sem itens
  if ($http === 404) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Verifica se é JSON válido
  $dados = json_decode($resp, true);
  if ($dados === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(502);
    echo json_encode([
      'error' => [
        'code'    => "HTTP_$http",
        'message' => 'A API do PNCP retornou uma resposta inválida (não-JSON).',
        'hint'    => 'Pode ser instabilidade no serviço PNCP.'
      ],
      'debugUrl' => $url
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($http !== 200) {
    http_response_code($http);
    echo json_encode([
      'error' => [
        'code'    => "HTTP_$http",
        'message' => $dados['message'] ?? 'Erro na API do PNCP.',
        'hint'    => $dados['path'] ?? null,
      ],
      'debugUrl' => $url
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // OK
  $lista = is_array($dados) ? $dados : [];
  echo json_encode($lista, JSON_UNESCAPED_UNICODE);
  exit;
}

// === AÇÃO DESCONHECIDA ===
http_response_code(400);
echo json_encode(['error' => [
  'code' => 'ACAO_INVALIDA',
  'message' => 'Ação inválida ou não informada.'
]], JSON_UNESCAPED_UNICODE);
exit;
