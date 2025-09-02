<?php
session_start();
require_once '../../config/db.php';
$pdo = Conexao::getInstance();



if (isset($_SESSION['usuario_id'])) {
  $stmt = $pdo->prepare("INSERT INTO logs (usuario_id, acao, ip) VALUES (?, 'logout', ?)");
  $stmt->execute([$_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR']]);
}
session_destroy();
header('Location: ../../templates/login.php');
