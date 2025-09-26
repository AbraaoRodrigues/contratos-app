<?php
set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../../../config/db_precos.php';
require_once __DIR__ . '/logger.php';

$pdo = ConexaoPrecos::getInstance();

$inicioExec = microtime(true);
logInicioExec("Correção de modalidades/status/data_abertura em cache_pncp_processos");

$total  = 0;
$limit  = 1000;
$offset = 0;

try {
  while (true) {
    $st = $pdo->prepare("
            SELECT id, json_original
            FROM cache_pncp_processos
            ORDER BY id
            LIMIT :limit OFFSET :offset
        ");
    $st->bindValue(':limit', $limit, PDO::PARAM_INT);
    $st->bindValue(':offset', $offset, PDO::PARAM_INT);
    $st->execute();

    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) break;

    foreach ($rows as $row) {
      $json = json_decode($row['json_original'], true);
      if (!$json) continue;

      $modalidade = $json['modalidadeNome'] ?? null;
      $status     = $json['situacaoCompraNome'] ?? null;
      $abertura   = $json['dataAberturaProposta'] ?? null;

      if ($modalidade || $status || $abertura) {
        $upd = $pdo->prepare("
                    UPDATE cache_pncp_processos
                    SET modalidade_nome = ?, status = ?, dataAbertura = ?
                    WHERE id = ?
                ");
        $upd->execute([$modalidade, $status, $abertura, $row['id']]);

        $total++;
        logar("✔️ Registro #{$row['id']} atualizado: mod=[$modalidade], status=[$status], abertura=[$abertura]");
      }
    }

    $offset += $limit;
    logar("➡️ Processados até $offset registros...");
  }
} catch (Throwable $e) {
  logar("❌ ERRO FATAL: " . $e->getMessage());
} finally {
  logFimExec($inicioExec, "Total de registros atualizados: $total");
}
