<?php
header('Content-Type: application/json; charset=UTF-8');
require __DIR__ . '/api/db.php';

$listaId = (int)($_GET['lista_id'] ?? 0);
if (!$listaId) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'msg' => 'Informe lista_id']);
  exit;
}

$sql = "SELECT id, descricao, quantidade, valor_unitario, valor_total, referencias, criado_em
        FROM lista_logs
        WHERE lista_id = ?
        ORDER BY criado_em ASC";
$st = $pdo->prepare($sql);
$st->execute([$listaId]);
$logs = $st->fetchAll();

echo json_encode(['ok' => true, 'logs' => $logs], JSON_UNESCAPED_UNICODE);
