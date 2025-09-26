<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

// se não estiver logado
if (!isset($_SESSION['usuario_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'msg' => 'Usuário não autenticado']);
  exit;
}

require __DIR__ . '/../../../config/db_precos.php';
$pdo = ConexaoPrecos::getInstance();

$usuarioId = $_SESSION['usuario_id'];

try {
  $st = $pdo->prepare("SELECT id, nome, criado_em
                       FROM listas
                       WHERE usuario_id = ?
                       ORDER BY criado_em DESC");
  $st->execute([$usuarioId]);
  $listas = $st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok' => true, 'listas' => $listas], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'msg' => 'Erro ao carregar listas', 'det' => $e->getMessage()]);
}
