<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/db_precos.php';
$pdo = ConexaoPrecos::getInstance();

/**
 * Parâmetros
 */
$palavra       = trim($_GET['palavra'] ?? '');
$uf            = trim($_GET['uf'] ?? '');
$modalidadeTxt = trim($_GET['modalidade'] ?? '');
$statusTxt     = trim($_GET['status'] ?? '');
$periodoDias   = max(1, min(365, (int)($_GET['periodoDias'] ?? 365)));
$incluirItens  = !empty($_GET['incluirItens']);
$page          = max(1, (int)($_GET['page'] ?? 1));
$pageSize      = max(10, min(200, (int)($_GET['pageSize'] ?? 50)));
$offset        = ($page - 1) * $pageSize;

$wheres = [];
$params = [];

/**
 * Janela de tempo (dataPublicacao) – assume formato DATE na tabela
 */
$wheres[] = "dataPublicacao >= CURDATE() - INTERVAL :dias DAY";
$params[':dias'] = $periodoDias;

/**
 * UF
 */
if ($uf !== '') {
  $wheres[] = "uf = :uf";
  $params[':uf'] = $uf;
}

/**
 * Status / Modalidade – no cache guardamos status textual.
 * Vamos combinar 'status' com LIKE e modalidade textual também via LIKE no campo status.
 * (Se você armazenou modalidade em coluna separada, troque para a coluna correta.)
 */
if ($statusTxt !== '') {
  $wheres[] = "status LIKE :statusTxt";
  $params[':statusTxt'] = "%$statusTxt%";
}
if ($modalidadeTxt !== '') {
  $wheres[] = "status LIKE :modalidadeTxt";
  $params[':modalidadeTxt'] = "%$modalidadeTxt%";
}

/**
 * Palavra-chave
 * Sem incluir itens: busca em objeto + orgao
 * Com incluir itens: faz EXISTS em cache_pncp_itens.descricao
 */
if ($palavra !== '') {
  if ($incluirItens) {
    $wheres[] = "(
      objeto LIKE :p OR orgao LIKE :p
      OR EXISTS (
        SELECT 1 FROM cache_pncp_itens i
        WHERE i.numeroControlePNCP = p.numeroControlePNCP
          AND i.descricao LIKE :p
      )
    )";
    $params[':p'] = "%$palavra%";
  } else {
    $wheres[] = "(objeto LIKE :p OR orgao LIKE :p)";
    $params[':p'] = "%$palavra%";
  }
}

$whereSql = $wheres ? ('WHERE ' . implode(' AND ', $wheres)) : '';

/**
 * Total
 */
$sqlCount = "SELECT COUNT(*) AS total FROM cache_pncp_processos p $whereSql";
$st = $pdo->prepare($sqlCount);
$st->execute($params);
$total = (int)$st->fetchColumn();

/**
 * Página
 */
$sql = "SELECT
          p.numeroControlePNCP,
          p.objeto,
          p.orgao,
          p.uf,
          p.status,
          p.dataPublicacao,
          p.dataAbertura
        FROM cache_pncp_processos p
        $whereSql
        ORDER BY p.dataAbertura DESC
        LIMIT :lim OFFSET :off";
$st = $pdo->prepare($sql);

/** bind params de forma segura */
foreach ($params as $k => $v) {
  $st->bindValue($k, $v);
}
$st->bindValue(':lim', $pageSize, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();

$rows = $st->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
  'ok' => true,
  'total' => $total,
  'page' => $page,
  'pageSize' => $pageSize,
  'data' => $rows
], JSON_UNESCAPED_UNICODE);
