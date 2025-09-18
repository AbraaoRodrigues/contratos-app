<?php

/**
 * CRON para atualizar cache PNCP (últimos X dias)
 * Rodar semanalmente (ex.: 7 dias)
 */

require_once __DIR__ . '/../../../config/db_precos.php';
require_once __DIR__ . '/funcoes_pncp.php';

$pdo = ConexaoPrecos::getInstance();

// === LOG ===
$logFile = __DIR__ . '/logs/cron_full_cache.log'; // mesmo arquivo
function logar($mensagem)
{
  global $logFile;
  $linha = "[" . date('Y-m-d H:i:s') . "] " . $mensagem . "\n";
  echo $linha;
  file_put_contents($logFile, $linha, FILE_APPEND);
}

// === Configuração ===
$ufs = ['SP'];         // pode expandir para todas ['AC','AL','AM',...]
$modalidades = [6, 7]; // pode expandir se quiser
$tamPagina = 100;

// Quantos dias (CLI ou GET)
$dias = 7;
if (php_sapi_name() === 'cli') {
  $dias = (int)($argv[1] ?? 7);
} else {
  $dias = (int)($_GET['dias'] ?? 7);
}

$dataInicial = new DateTime("-$dias days");
$dataFinal   = new DateTime();

logar("🔄 [ATUALIZAR] Sincronizando PNCP ({$dataInicial->format('Y-m-d')} -> {$dataFinal->format('Y-m-d')})");

foreach ($ufs as $uf) {
  foreach ($modalidades as $mod) {
    $pagina = 1;
    $continuar = true;

    while ($continuar) {
      $url = "https://pncp.gov.br/api/consulta/v1/contratacoes/publicacao?"
        . "dataInicial={$dataInicial->format('Ymd')}&dataFinal={$dataFinal->format('Ymd')}"
        . "&codigoModalidadeContratacao=$mod&uf=$uf"
        . "&pagina=$pagina&tamanhoPagina=$tamPagina";

      $resposta = consultarApi($url);

      if ($resposta === false) {
        logar("❌ Erro cURL/timeout ao consultar página $pagina ($uf/$mod)");
        break;
      }
      if ($resposta === null) {
        logar("⚠️ Janela inválida (HTTP 400) — $uf/$mod");
        break;
      }

      $total = $resposta['totalRegistros'] ?? 0;
      $dados = $resposta['data'] ?? [];
      logar("📄 Página $pagina / ~" . ceil(($total ?: 1) / $tamPagina)
        . " ($uf / mod $mod) → " . count($dados) . " processos");

      if (empty($dados)) {
        logar("ℹ️ Nenhum dado retornado, encerrando $uf/$mod.");
        break;
      }

      foreach ($dados as $proc) {
        try {
          salvarProcesso($pdo, $proc);
          logar("   💾 Processo {$proc['numeroControlePNCP']} salvo/atualizado.");
        } catch (Throwable $e) {
          logar("   ⚠️ Erro ao salvar processo {$proc['numeroControlePNCP']}: " . $e->getMessage());
          registrarFalha($pdo, "ERRO_SALVAR_PROCESSO", $proc['numeroControlePNCP']);
          continue;
        }

        try {
          $okItens = salvarItensDoProcesso($pdo, $proc['numeroControlePNCP']);
          if (!$okItens) {
            registrarFalha($pdo, "ITENS_INDISPONIVEIS", $proc['numeroControlePNCP']);
            logar("   ⚠️ Itens indisponíveis/404 para {$proc['numeroControlePNCP']}");
          }
        } catch (Throwable $e) {
          registrarFalha($pdo, "ERRO_SALVAR_ITENS", $proc['numeroControlePNCP']);
          logar("   ⚠️ Erro ao salvar itens de {$proc['numeroControlePNCP']}: " . $e->getMessage());
        }
      }

      $pagina++;
      $continuar = ($pagina - 1) * $tamPagina < $total;

      // Throttle p/ aliviar API
      usleep(200_000); // 200ms
    }
  }
}

logar("🏁 [ATUALIZAR] Finalizado");
