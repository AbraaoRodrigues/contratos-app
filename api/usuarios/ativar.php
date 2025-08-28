<?php
session_start();
require_once '../../config/db.php';
require_once '../../templates/includes/verifica_login.php';

if ($_SESSION['nivel_acesso'] !== 'admin') exit('Acesso restrito.');

$id = $_GET['id'] ?? null;
if (!$id) exit('Operação inválida.');

$stmt = $pdo->prepare("UPDATE usuarios SET status = 'ativo' WHERE id = ?");
$stmt->execute([$id]);

$pdo->prepare("INSERT INTO logs (usuario_id, acao, ip) VALUES (?, ?, ?)")
  ->execute([$_SESSION['usuario_id'], 'ativou_usuario', $_SERVER['REMOTE_ADDR']]);

header('Location: ../../templates/lista_usuarios.php');
