<?php
session_start();
require_once '../../config/db.php';
$pdo = Conexao::getInstance();

if (!isset($_SESSION['usuario_id'])) {
  header('Location: ../../templates/login.php');
  exit;
}


$id = $_GET['id'] ?? null;
if ($id) {
  $stmt = $pdo->prepare("DELETE FROM contratos WHERE id = ?");
  $stmt->execute([$id]);
  $pdo->prepare("INSERT INTO logs (usuario_id, acao, ip) VALUES (?, 'excluiu contrato ID: $id', ?)")
    ->execute([$_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR']]);
}


header('Location: ../../templates/contratos.php?msg=Contrato excluído com sucesso');
