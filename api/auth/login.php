<?php
require_once '../../config/db.php';
session_start();


$email = $_POST['email'] ?? '';
$senha = $_POST['senha'] ?? '';


$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);


if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
  $_SESSION['usuario_id'] = $usuario['id'];
  $_SESSION['modo_escuro'] = $usuario['modo_escuro'];
  $_SESSION['nome'] = $usuario['nome'];
  $_SESSION['nivel_acesso'] = $usuario['nivel_acesso'];

  $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?")
    ->execute([$usuario['id']]);

  $pdo->prepare("INSERT INTO logs (usuario_id, acao, ip) VALUES (?, 'login', ?)")
    ->execute([$usuario['id'], $_SERVER['REMOTE_ADDR']]);

  header('Location: ../../index.php');
} else {
  echo "Credenciais inválidas.";
}
