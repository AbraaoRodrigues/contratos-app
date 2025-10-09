<?php

ob_clean();           // limpa qualquer buffer pendente
header_remove();      // remove headers antigos
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
ini_set('display_errors', 0);
error_reporting(0);


require_once __DIR__ . '/../../../config/db_precos.php';
$pdo = ConexaoPrecos::getInstance();

$numeroControlePNCP = $_GET['numeroControlePNCP'] ?? '';
if ($numeroControlePNCP === '') {
  echo json_encode(['ok' => false, 'msg' => 'Informe numeroControlePNCP']);
  exit;
}

$page     = max(1, (int)($_GET['page'] ?? 1));
$pageSize = max(1, min(100, (int)($_GET['pageSize'] ?? 25)));
$offset   = ($page - 1) * $pageSize;

$sql = "SELECT SQL_CALC_FOUND_ROWS
          numeroControlePNCP,
          JSON_EXTRACT(json_original, '$.numeroItem') AS numeroItem,
          descricao,
          quantidade,
          COALESCE(valorUnitarioHomologado, valorUnitarioEstimado) AS valorUnit,
          valorUnitarioEstimado,
          valorUnitarioHomologado
        FROM cache_pncp_itens
        WHERE numeroControlePNCP=?
        ORDER BY CAST(JSON_EXTRACT(json_original, '$.numeroItem') AS UNSIGNED), descricao
        LIMIT $pageSize OFFSET $offset";

$st = $pdo->prepare($sql);
$st->execute([$numeroControlePNCP]);
$itens = $st->fetchAll(PDO::FETCH_ASSOC);

$total = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();

echo json_encode([
  'ok' => true,
  'itens' => $itens,
  'total' => (int)$total,
  'page' => $page,
  'pageSize' => $pageSize
], JSON_UNESCAPED_UNICODE);

exit;
