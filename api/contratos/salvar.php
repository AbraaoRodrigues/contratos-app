<?php
session_start();
require_once '../../config/db.php';
$pdo = Conexao::getInstance();

if (!isset($_SESSION['usuario_id'])) {
  header('Location: ../../templates/login.php');
  exit;
}

function parseValorReal($valor)
{
  return str_replace(',', '.', str_replace('.', '', $valor));
}

$valor = isset($_POST['valor_total']) ? parseValorReal($_POST['valor_total']) : 0.00;

$stmt = $pdo->prepare("INSERT INTO contratos (numero, processo, fornecedor, data_inicio, data_fim, valor_total, local_arquivo, observacoes,
 objeto, data_assinatura, prorrogavel, responsavel)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");


$stmt->execute([
  $_POST['numero'],
  $_POST['processo'],
  $_POST['fornecedor'],
  $_POST['data_inicio'],
  $_POST['data_fim'],
  $valor,
  $_POST['local_arquivo'],
  $_POST['observacoes'] ?? null,
  $_POST['objeto'] ?? null,
  $_POST['data_assinatura'],
  $_POST['prorrogavel'],
  $_POST['responsavel']
]);


// log
$pdo->prepare("INSERT INTO logs (usuario_id, acao, ip) VALUES (?, 'cadastrou contrato', ?)")
  ->execute([$_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR']]);


header('Location: ../../templates/contratos.php?msg=Contrato salvo com sucesso');
