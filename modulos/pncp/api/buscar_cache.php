<?php
if (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/buscar_cache_error.log');
error_reporting(E_ALL);


require_once __DIR__ . '/../../../config/db_precos.php';
$pdo = ConexaoPrecos::getInstance();

/** PARÂMETROS */
$palavra       = trim($_GET['palavra'] ?? '');
$uf            = trim($_GET['uf'] ?? '');
$modalidadeTxt = trim($_GET['modalidade'] ?? '');
$statusTxt     = trim($_GET['status'] ?? '');
$periodoDias   = max(1, min(365, (int)($_GET['periodoDias'] ?? 365)));
$incluirItens  = !empty($_GET['incluirItens']);
$page          = max(1, (int)($_GET['page'] ?? 1));
$pageSize      = max(10, min(200, (int)($_GET['pageSize'] ?? 10)));
$offset        = ($page - 1) * $pageSize;

$wheres = [];
$params = [];

/** FILTROS BASE */
$wheres[] = "p.dataPublicacao >= CURDATE() - INTERVAL :dias DAY";
$params[':dias'] = $periodoDias;

if ($uf !== '') {
  $wheres[] = "p.uf = :uf";
  $params[':uf'] = $uf;
}

if ($statusTxt !== '') {
  $wheres[] = "p.status LIKE :statusTxt";
  $params[':statusTxt'] = "%$statusTxt%";
}

if ($modalidadeTxt !== '') {
  $wheres[] = "p.modalidade_nome LIKE :modalidadeTxt";
  $params[':modalidadeTxt'] = "%$modalidadeTxt%";
}

/** BUSCA POR PALAVRA */
if ($palavra !== '') {
  if ($incluirItens) {
    // 🔎 Busca em processos + itens
    $join = "INNER JOIN cache_pncp_itens i ON i.numeroControlePNCP = p.numeroControlePNCP";
    $wheres[] = "(MATCH(p.objeto, p.orgao) AGAINST(:p IN BOOLEAN MODE)
                  OR MATCH(i.descricao) AGAINST(:p IN BOOLEAN MODE))";
  } else {
    // 🔎 Busca apenas em processos
    $join = "";
    $wheres[] = "MATCH(p.objeto, p.orgao) AGAINST(:p IN BOOLEAN MODE)";
  }
  $params[':p'] = $palavra . '*'; // * = busca parcial
} else {
  $join = "";
}

$whereSql = $wheres ? ('WHERE ' . implode(' AND ', $wheres)) : '';

/** TOTAL */
$sqlCount = "SELECT COUNT(DISTINCT p.numeroControlePNCP) AS total
             FROM cache_pncp_processos p
             $join
             $whereSql";
$st = $pdo->prepare($sqlCount);
$st->execute($params);

$total = (int)$st->fetchColumn();

/** RESULTADOS */
$sql = "SELECT DISTINCT
          p.numeroControlePNCP,
          p.objeto,
          p.orgao,
          p.uf,
          p.modalidade_nome,
          p.status,
          p.dataPublicacao,
          p.dataAbertura
        FROM cache_pncp_processos p
        $join
        $whereSql
        ORDER BY p.dataAbertura DESC
        LIMIT :lim OFFSET :off";
$st = $pdo->prepare($sql);
foreach ($params as $k => $v) {
  $st->bindValue($k, $v);
}
$st->bindValue(':lim', $pageSize, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$out = ob_get_clean();
if ($out) {
  echo "<pre>DEBUG OUTPUT:\n$out</pre>";
  exit;
}

echo json_encode([
  'ok' => true,
  'total' => $total,
  'page' => $page,
  'pageSize' => $pageSize,
  'data' => $rows
], JSON_UNESCAPED_UNICODE);

ob_end_flush(); // envia buffer de saída
exit;
