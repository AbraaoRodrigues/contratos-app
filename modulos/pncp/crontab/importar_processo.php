<?php
require_once __DIR__ . '/../../../config/db_precos.php';
require_once __DIR__ . '/funcoes_pncp.php';

$pdo = ConexaoPrecos::getInstance();

if (new DateTime($cabecalho['dataAbertura']) < (new DateTime())->modify('-365 days')) {
  echo "⛔ Processo {$numeroControlePNCP} é mais antigo que 365 dias e não será importado.\n";
  return false;
}

$numero = $_GET['numero'] ?? '';
if (!$numero) {
  exit("Informe ?numero=numeroControlePNCP");
}

importarProcesso($pdo, $numero);
