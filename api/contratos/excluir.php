<?php
session_start();
require_once '../../config/db.php';
$pdo = Conexao::getInstance();

$id = $_POST['id'] ?? null;
$justificativa = $_POST['justificativa'] ?? null;

if (!$id || !$justificativa) {
  exit('ID e justificativa são obrigatórios.');
}

$stmt = $pdo->prepare("UPDATE contratos SET status = 'excluido', justificativa_exclusao = ? WHERE id = ?");
$stmt->execute([$justificativa, $id]);

// Marca todos os arquivos vinculados como excluídos
$stmt = $pdo->prepare("UPDATE contrato_arquivos
                      SET status = 'excluido', justificativa_exclusao = 'Contrato excluído.', excluido_em = NOW()
                      WHERE contrato_id = ?");
$stmt->execute([$id]);

// (Opcional) registrar no log
$stmtLog = $pdo->prepare("INSERT INTO logs (usuario_id, acao, ip, criado_em)
                          VALUES (?, 'excluir_contrato', ?, NOW())");
$detalhes = "Contrato ID $id marcado como excluído. Justificativa: $justificativa";
$stmtLog->execute([$_SESSION['usuario_id'], $detalhes]);

echo "Contrato excluído com sucesso.";
