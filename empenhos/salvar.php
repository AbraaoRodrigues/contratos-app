<?php
session_start();
require_once '../../config/db.php';


if (!isset($_SESSION['usuario_id'])) {
  header('Location: ../../templates/login.php');
  exit;
}


$stmt = $pdo->prepare("INSERT INTO empenhos (contrato_id, valor_empenhado, data_empenho, data_fim_previsto)
VALUES (?, ?, ?, ?)");


$stmt->execute([
  $_POST['contrato_id'],
  $_POST['valor_empenhado'],
  $_POST['data_empenho'],
  $_POST['data_fim_previsto']
]);


$pdo->prepare("INSERT INTO logs (usuario_id, acao, ip) VALUES (?, ?, ?)")
  ->execute([$_SESSION['usuario_id'], 'cadastrou empenho para contrato ID: ' . $_POST['contrato_id'], $_SERVER['REMOTE_ADDR']]);


header('Location: ../../templates/empenhos.php?msg=Empenho salvo com sucesso');
