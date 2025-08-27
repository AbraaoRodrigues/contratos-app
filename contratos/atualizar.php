<?php
session_start();
require_once '../../config/db.php';


if (!isset($_SESSION['usuario_id']) || !isset($_POST['id'])) {
  header('Location: ../../templates/login.php');
  exit;
}


$stmt = $pdo->prepare("UPDATE contratos SET numero = ?, processo = ?, orgao = ?, valor_total = ?, data_inicio = ?, data_fim = ?, local_arquivo = ?, observacoes = ? WHERE id = ?");
$stmt->execute([
  $_POST['numero'],
  $_POST['processo'],
  $_POST['orgao'],
  $_POST['valor_total'],
  $_POST['data_inicio'],
  $_POST['data_fim'],
  $_POST['local_arquivo'],
  $_POST['observacoes'],
  $_POST['id']
]);


$pdo->prepare("INSERT INTO logs (usuario_id, acao, ip) VALUES (?, ?, ?)")
  ->execute([$_SESSION['usuario_id'], 'editou contrato ID: ' . $_POST['id'], $_SERVER['REMOTE_ADDR']]);


header('Location: ../../templates/contratos.php?msg=Contrato atualizado com sucesso');
