<?php
session_start();
require_once '../../config/db.php';
$pdo = Conexao::getInstance();

require_once '../../templates/includes/verifica_login.php';

if ($_SESSION['nivel_acesso'] !== 'admin') exit('Acesso restrito.');

$id = $_GET['id'] ?? null;
if (!$id || $id == $_SESSION['usuario_id']) exit('Operação inválida.');

// Verifica se o usuário tem eventos vinculados antes de desativar
// (Opcional: se quiser implementar essa checagem, me avise)

$stmt = $pdo->prepare("UPDATE usuarios SET status = 'inativo' WHERE id = ?");
$stmt->execute([$id]);

$pdo->prepare("INSERT INTO logs (usuario_id, acao, ip) VALUES (?, ?, ?)")
  ->execute([$_SESSION['usuario_id'], 'desativou_usuario', $_SERVER['REMOTE_ADDR']]);

header('Location: ../../templates/lista_usuarios.php');
