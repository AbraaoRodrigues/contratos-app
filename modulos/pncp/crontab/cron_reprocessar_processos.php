<?php

/**
 * CRON para reprocessar processos do PNCP
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

logar("🔄 [PROCESSOS] Iniciando reprocessamento de processos pendentes...");

// Busca processos pendentes de reprocessar
$sql = "SELECT * FROM cache_pncp_processos WHERE status='pendente' ORDER BY atualizado_em ASC LIMIT 50";
$processos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (!$processos) {
  logar("✅ [PROCESSOS] Nenhum processo pendente para reprocessar.");
  exit;
}

foreach ($processos as $proc) {
  $numero = $proc['numeroControlePNCP'];
  logar("📌 [PROCESSOS] Reprocessando $numero (tentativa {$proc['tentativas']})...");

  $resultado = importarProcesso($pdo, $numero);

  if ($resultado === true) {
    $pdo->prepare("UPDATE cache_pncp_processos
                       SET status='corrigido', corrigido_em=NOW()
                       WHERE id=?")
      ->execute([$proc['id']]);
    logar("   ✅ [PROCESSOS] Corrigido com sucesso: $numero");
  } else {
    // Incrementa tentativa
    $pdo->prepare("UPDATE cache_pncp_processos
                       SET tentativas = tentativas + 1
                       WHERE id=?")
      ->execute([$proc['id']]);

    if ($proc['tentativas'] + 1 >= $maxTentativas) {
      $pdo->prepare("UPDATE cache_pncp_processos
                           SET status='irrecuperavel'
                           WHERE id=?")
        ->execute([$proc['id']]);
      logar("   ❌ [PROCESSOS] Marcado como irrecuperável ($resultado): $numero");
    } else {
      logar("   ⚠️ [PROCESSOS] Falha novamente ($resultado), mantido como pendente: $numero");
    }
  }
}

logar("🏁 [PROCESSOS] Finalizado reprocessamento.");
