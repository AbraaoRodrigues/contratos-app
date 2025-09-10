<?php
session_start();
require_once '../../config/db.php';
$pdo = Conexao::getInstance();

$id = $_POST['arquivo_id'] ?? null;
$justificativa = $_POST['justificativa'] ?? '';

if (!$id || empty($justificativa)) {
  exit('ID do arquivo e justificativa são obrigatórios.');
}

// Atualiza como "excluído" (soft delete)
$stmt = $pdo->prepare("UPDATE contrato_arquivos SET status = 'excluido', justificativa_exclusao = ?, excluido_em = NOW() WHERE id = ?");
$stmt->execute([$justificativa, $id]);

// Log da exclusão
$stmtLog = $pdo->prepare("INSERT INTO logs (usuario_id, acao, ip, criado_em)
                          VALUES (?, 'excluir_arquivo', ?, NOW())");
$detalhes = "Arquivo ID $id excluído. Justificativa: $justificativa";
$stmtLog->execute([$_SESSION['usuario_id'], $detalhes]);

header('Location: ../../templates/contratos.php?msg=Arquivo excluído com sucesso');
