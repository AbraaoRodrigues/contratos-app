<?php
session_start();
require_once '../../config/db.php';


if ($_SESSION['nivel_acesso'] !== 'admin') exit('Acesso negado');


$id = $_GET['id'] ?? null;
if (!$id || $id == $_SESSION['usuario_id']) exit('Operação inválida');


// Verifica se o usuário está vinculado a algum log
$stmt = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE usuario_id = ?");
$stmt->execute([$id]);
$vinculado = $stmt->fetchColumn();


if ($vinculado > 0) {
  // Desativa usuário (não pode excluir)
  $pdo->prepare("UPDATE usuarios SET ativo = 0 WHERE id = ?")->execute([$id]);


  $acao = "desativar_usuario";
  $detalhes = "Usuário ID $id desativado por vínculo com logs";
} else {
  // Exclui completamente
  $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);


  $acao = "excluir_usuario";
  $detalhes = "Usuário ID $id excluído do sistema";
}


$pdo->prepare("INSERT INTO logs (usuario_id, acao, detalhes, data_hora)
VALUES (?, ?, ?, NOW())")
  ->execute([$_SESSION['usuario_id'], $acao, $detalhes]);


header('Location: ../../templates/lista_usuarios.php');
exit;
