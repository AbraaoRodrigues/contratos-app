<?php
// debug_itens.php

$orgao  = "46374500000194";
$ano    = "2025";
$edital = "7731";
$pagina = 1;

$url = "https://pncp.gov.br/api/pncp/v1/orgaos/{$orgao}/compras/{$ano}/{$edital}/itens?pagina={$pagina}&tamanhoPagina=50";

echo "🔍 Testando endpoint: $url\n\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_SSL_VERIFYPEER => true,
]);
$res = curl_exec($ch);
$err = curl_error($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
  echo "⚠️ Erro cURL: $err\n";
  exit;
}

if ($http !== 200) {
  echo "⚠️ HTTP $http → $res\n";
  exit;
}

$json = json_decode($res, true);
if (!$json) {
  echo "⚠️ Resposta inválida: $res\n";
  exit;
}

// alguns endpoints retornam em "data", outros já retornam array direto
$itens = $json['data'] ?? $json;

if (!is_array($itens)) {
  echo "⚠️ Estrutura inesperada:\n";
  print_r($json);
  exit;
}

echo "✅ Itens recebidos: " . count($itens) . "\n\n";
print_r($itens);
