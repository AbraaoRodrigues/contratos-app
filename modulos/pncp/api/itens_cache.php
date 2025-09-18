<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/db_precos.php';
$pdo = ConexaoPrecos::getInstance();

$nc = $_GET['numeroControlePNCP'] ?? '';
if ($nc === '') {
  echo json_encode(['ok' => false, 'msg' => 'Informe numeroControlePNCP']);
  exit;
}

$sql = "SELECT
          JSON_EXTRACT(json_original, '$.numeroItem')    AS numeroItem,
          descricao,
          quantidade,
          COALESCE(valorUnitarioHomologado, valorUnitarioEstimado) AS valorUnit,
          valorUnitarioEstimado,
          valorUnitarioHomologado
        FROM cache_pncp_itens
        WHERE numeroControlePNCP = ?
        ORDER BY CAST(JSON_EXTRACT(json_original, '$.numeroItem') AS UNSIGNED), descricao";
$st = $pdo->prepare($sql);
$st->execute([$nc]);
$itens = $st->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok' => true, 'itens' => $itens], JSON_UNESCAPED_UNICODE);
