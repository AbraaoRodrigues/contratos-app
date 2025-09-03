<?php
session_start();
require_once '../../config/db.php';
$pdo = Conexao::getInstance();

$id = $_POST['id'] ?? null;
$justificativa = $_POST['justificativa'] ?? null;

if (!$id || !$justificativa) {
  exit('ID e justificativa são obrigatórios.');
}

$stmt = $pdo->prepare("UPDATE empenhos SET status = 'excluido', justificativa_exclusao = ? WHERE id = ?");
$stmt->execute([$justificativa, $id]);

// Log
$stmtLog = $pdo->prepare("INSERT INTO logs (usuario_id, acao, detalhes, data_hora)
                          VALUES (?, 'excluir_empenho', ?, NOW())");
$detalhes = "Empenho ID $id marcado como excluído. Justificativa: $justificativa";
$stmtLog->execute([$_SESSION['usuario_id'], $detalhes]);

echo "Empenho excluído com sucesso.";
