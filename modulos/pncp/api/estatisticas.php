<?php
require_once __DIR__ . '/../../../config/db_precos.php';
$pdo = ConexaoPrecos::getInstance();

header('Content-Type: application/json; charset=utf-8');

// Totais de falhas (cache_pncp_falhas)
$totaisFalhas = $pdo->query("
  SELECT status, COUNT(*) AS total
  FROM cache_pncp_falhas
  GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Totais de processos (cache_pncp_processos)
$totaisProcessos = $pdo->query("
  SELECT status, COUNT(*) AS total
  FROM cache_pncp_processos
  GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Falhas por tipo (normalização no próprio SQL)
$falhasTipos = $pdo->query("
  SELECT
    CASE
      WHEN motivo LIKE 'Falha ao consultar itens%' THEN 'Falha ao consultar itens'
      WHEN motivo LIKE '%cURL%' THEN 'FALHA_CURL'
      ELSE motivo
    END AS motivo,
    COUNT(*) AS total
  FROM cache_pncp_falhas
  GROUP BY
    CASE
      WHEN motivo LIKE 'Falha ao consultar itens%' THEN 'Falha ao consultar itens'
      WHEN motivo LIKE '%cURL%' THEN 'FALHA_CURL'
      ELSE motivo
    END
  ORDER BY total DESC;
")->fetchAll(PDO::FETCH_ASSOC);

// Consolida
function somaTotais(...$arrays)
{
  $resultado = [];
  foreach ($arrays as $arr) {
    foreach ($arr as $k => $v) {
      $resultado[$k] = ($resultado[$k] ?? 0) + (int)$v;
    }
  }
  return $resultado;
}

$totaisConsolidados = somaTotais($totaisFalhas, $totaisProcessos);

echo json_encode([
  'totais' => $totaisConsolidados,
  'falhas_tipos' => $falhasTipos,
]);
