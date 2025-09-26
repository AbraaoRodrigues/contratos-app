<?php

/**
 * CRON — Reprocessar falhas
 */

set_time_limit(0);
ini_set('memory_limit', '512M');
require_once __DIR__ . '/../../../config/db_precos.php';
require_once __DIR__ . '/funcoes_pncp.php';
require_once __DIR__ . '/logger.php';

$pdo = ConexaoPrecos::getInstance();

// Marca início
$inicioExec = microtime(true);
logInicioExec("Reprocessamento de falhas PNCP");

// Busca falhas pendentes
$stmt = $pdo->query("SELECT id, numeroControlePNCP, motivo, tentativas
                     FROM cache_pncp_falhas
                     WHERE status = 'pendente'
                     ORDER BY criado_em ASC
                     LIMIT 50");
$falhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$falhas) {
  logar("ℹ️ Nenhuma falha pendente encontrada.");
  logFimExec($inicioExec);
  exit;
}

foreach ($falhas as $falha) {
  $id = $falha['id'];
  $num = $falha['numeroControlePNCP'];
  $tentativas = (int)$falha['tentativas'];

  logar("🔄 Reprocessando falha ID=$id — Processo $num (tentativas: $tentativas)");

  try {
    $processo = baixarJson("https://pncp.gov.br/api/consulta/v1/contratacoes/$num");
    if (!$processo) {
      throw new Exception("Processo $num não encontrado");
    }

    salvarProcesso($pdo, $processo);
    $okItens = salvarItensDoProcesso($pdo, $num);

    if (!$okItens) {
      throw new Exception("Itens indisponíveis/404 para $num");
    }

    // Marca como corrigido
    $upd = $pdo->prepare("UPDATE cache_pncp_falhas
                              SET status='corrigido', corrigido_em=NOW()
                              WHERE id=?");
    $upd->execute([$id]);

    logar("✅ Falha corrigida: $num");
  } catch (Throwable $e) {
    $tentativas++;
    $upd = $pdo->prepare("UPDATE cache_pncp_falhas
                              SET tentativas=?, motivo=?, status=?
                              WHERE id=?");
    $status = $tentativas >= 5 ? 'irrecuperavel' : 'pendente';
    $upd->execute([$tentativas, $e->getMessage(), $status, $id]);

    logar("⚠️ Erro ao reprocessar $num — " . $e->getMessage());
  }

  usleep(200_000);
}

// Marca fim
logFimExec($inicioExec);
