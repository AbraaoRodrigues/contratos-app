<?php
session_start();
require_once '../../config/db.php';


if (!isset($_SESSION['usuario_id'])) exit('Acesso negado');


$id = $_SESSION['usuario_id'];
$modo_escuro = isset($_POST['modo_escuro']) ? 1 : 0;
$alertas = $_POST['alertas'] ?? [];
$alertas_json = json_encode(array_map('intval', $alertas));


$stmt = $pdo->prepare("UPDATE usuarios SET modo_escuro = ?, alertas_config = ? WHERE id = ?");
$stmt->execute([$modo_escuro, $alertas_json, $id]);


if (!empty($_POST['senha_atual']) && !empty($_POST['nova_senha'])) {
  $stmt = $pdo->prepare("SELECT senha_hash FROM usuarios WHERE id = ?");
  $stmt->execute([$id]);
  $senha_hash = $stmt->fetchColumn();


  if (password_verify($_POST['senha_atual'], $senha_hash)) {
    $nova_hash = password_hash($_POST['nova_senha'], PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?")->execute([$nova_hash, $id]);
    $pdo->prepare("INSERT INTO logs (usuario_id, acao, ip) VALUES (?, 'alterou senha', ?)")->execute([$id, $_SERVER['REMOTE_ADDR']]);
  } else {
    echo "Senha atual incorreta.";
    exit;
  }
}

$pdo->prepare("INSERT INTO logs (usuario_id, acao, ip) VALUES (?, 'alterou configuracoes', ?)")->execute([$id, $_SERVER['REMOTE_ADDR']]);

if (!empty($_FILES['avatar']['name'])) {
  $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
  $novoNome = 'avatar_' . $id . '.' . $ext;
  $destino = '../../assets/avatars/' . $novoNome;
  move_uploaded_file($_FILES['avatar']['tmp_name'], $destino);
  $pdo->prepare("UPDATE usuarios SET avatar = ? WHERE id = ?")->execute([$novoNome, $id]);
}

header('Location: ../../templates/configuracoes.php');
