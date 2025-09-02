<?php
require_once '../../config/db.php';
$pdo = Conexao::getInstance();

session_start();

$email = $_POST['email'] ?? '';
$senha = $_POST['senha'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND status = 'ativo'");
$stmt->execute([$email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
  $_SESSION['usuario_id'] = $usuario['id'];
  $_SESSION['nome'] = $usuario['nome'];
  $_SESSION['nivel_acesso'] = $usuario['nivel_acesso'];
  $_SESSION['modo_escuro'] = $usuario['modo_escuro'];

  $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?")->execute([$usuario['id']]);
  $pdo->prepare("INSERT INTO logs (usuario_id, acao, ip) VALUES (?, 'login', ?)")->execute([$usuario['id'], $_SERVER['REMOTE_ADDR']]);

  header('Location: ../../index.php');
  exit;
} else {
  $_SESSION['erro_login'] = "Credenciais inválidas ou usuário inativo.";
  header('Location: ../../templates/login.php');
  exit;
}
