<?php

/**
 * CRON para reprocessar falhas do PNCP
 * Executar diariamente/madrugada
 */
date_default_timezone_set('America/Sao_Paulo');
ini_set('max_execution_time', 0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../../../config/db_precos.php';
require_once __DIR__ . '/funcoes_pncp.php';

$pdo = ConexaoPrecos::getInstance();

// LOG unificado
$logFile = __DIR__ . '/../logs/cron_full_cache.log';
function logar($mensagem)
{
  global $logFile;
  $linha = "[" . date('Y-m-d H:i:s') . "] " . $mensagem . "\n";
  echo $linha;
  file_put_contents($logFile, $linha, FILE_APPEND);
}

$maxTentativas = 5;
logar("🔄 [FALHAS] Iniciando reprocessamento...");

// Busca falhas pendentes
$sql = "SELECT * FROM cache_pncp_falhas WHERE status='pendente' ORDER BY criado_em ASC LIMIT 50";
$falhas = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (!$falhas) {
  logar("✅ [FALHAS] Nenhuma falha para reprocessar.");
  exit;
}

foreach ($falhas as $falha) {
  $numero = $falha['numeroControlePNCP'];
  logar("📌 [FALHAS] Reprocessando $numero (tentativa {$falha['tentativas']})...");

  $resultado = importarProcesso($pdo, $numero);

  if ($resultado === true) {
    $pdo->prepare("UPDATE cache_pncp_falhas SET status='corrigido', corrigido_em=NOW() WHERE id=?")
      ->execute([$falha['id']]);
    logar("   ✅ [FALHAS] Corrigido com sucesso: $numero");
  } else {
    $pdo->prepare("UPDATE cache_pncp_falhas SET tentativas = tentativas + 1 WHERE id=?")
      ->execute([$falha['id']]);

    if ($falha['tentativas'] + 1 >= $maxTentativas) {
      $pdo->prepare("UPDATE cache_pncp_falhas SET status='irrecuperavel' WHERE id=?")
        ->execute([$falha['id']]);
      logar("   ❌ [FALHAS] Marcado como irrecuperável ($resultado): $numero");
    } else {
      logar("   ⚠️ [FALHAS] Falha novamente ($resultado), mantido como pendente: $numero");
    }
  }
}

logar("🏁 [FALHAS] Finalizado reprocessamento.");
