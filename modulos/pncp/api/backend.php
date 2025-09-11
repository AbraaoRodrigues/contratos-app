<?php
header("Content-Type: application/json; charset=UTF-8");

$acao = $_GET['acao'] ?? '';
$idCompra = $_GET['idCompra'] ?? '';
$palavra = $_GET['palavra'] ?? '';
$uf = $_GET['uf'] ?? '';
$status = $_GET['status'] ?? '';

if ($acao === 'listar') {
  // 1. Buscar lista de processos
  $url = "https://pncp.gov.br/api/consulta/v1/contratacoes?palavraChave=" . urlencode($palavra);
  if ($uf) $url .= "&uf=" . $uf;
  if ($status) $url .= "&statusCompra=" . $status;

  $response = file_get_contents($url);
  echo $response;
} elseif ($acao === 'detalhes' && $idCompra) {
  // 2. Buscar itens do processo
  $url = "https://pncp.gov.br/api/consulta/v1/contratacoes/$idCompra/itens";
  $response = file_get_contents($url);
  echo $response;
}
