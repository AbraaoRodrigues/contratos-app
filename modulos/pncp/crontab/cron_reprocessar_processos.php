<?php

/**
 * CRON — Reprocessar processos incompletos (sem itens)
 */

set_time_limit(0);
ini_set('memory_limit', '512M');
require_once __DIR__ . '/../../../config/db_precos.php';
require_once __DIR__ . '/funcoes_pncp.php';
require_once __DIR__ . '/logger.php';

$pdo = ConexaoPrecos::getInstance();

// Marca início
$inicioExec = microtime(true);
logInicioExec("Reprocessamento de processos incompletos");

// Busca processos sem itens
$stmt = $pdo->query("SELECT p.numeroControlePNCP
                     FROM cache_pncp_processos p
                     LEFT JOIN cache_pncp_itens i
                     ON p.numeroControlePNCP = i.numeroControlePNCP
                     WHERE i.id IS NULL
                     ORDER BY p.dataPublicacao DESC
                     LIMIT 50");
$processos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$processos) {
  logar("ℹ️ Nenhum processo incompleto encontrado.");
  logFimExec($inicioExec);
  exit;
}

foreach ($processos as $proc) {
  $num = $proc['numeroControlePNCP'];
  logar("🔄 Tentando reprocessar itens do processo $num");

  try {
    $okItens = salvarItensDoProcesso($pdo, $num);

    if (!$okItens) {
      registrarFalha($pdo, "ITENS_INDISPONIVEIS", $num);
      logar("⚠️ Itens indisponíveis/404 para $num");
    } else {
      logar("📦 Itens $num salvos com sucesso");
    }
  } catch (Throwable $e) {
    registrarFalha($pdo, "ERRO_REPROCESSAR_ITENS", $num);
    logar("⚠️ Erro ao reprocessar itens $num — " . $e->getMessage());
  }

  usleep(200_000);
}

// Marca fim
logFimExec($inicioExec);
