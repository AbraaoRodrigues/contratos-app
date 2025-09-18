<?php
// Ajusta timezone para não bagunçar os campos de data
date_default_timezone_set('America/Sao_Paulo');

// Aumenta limites de execução
ini_set('max_execution_time', 0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../../../config/db_precos.php';
require_once __DIR__ . '/funcoes_pncp.php';

$pdo = ConexaoPrecos::getInstance();

// LOG
$logFile = __DIR__ . '/../logs/cron_full_cache.log';
function logar($mensagem)
{
  global $logFile;
  $linha = "[" . date('Y-m-d H:i:s') . "] " . $mensagem . "\n";
  echo $linha;
  file_put_contents($logFile, $linha, FILE_APPEND);
}

// Configurações
$hoje = new DateTime();
$umAnoAtras = (clone $hoje)->modify('-365 days');
$tamPagina = 100; // quantidade de registros por página (CONFIGURÁVEL)

// Estados que deseja buscar
$ufs = ['SP'];

// Modalidades
$modalidades = [
  1 => "Leilão",
  3 => "Concurso",
  4 => "Concorrência (1)",
  5 => "Concorrência (2)",
  6 => "Pregão (1)",
  7 => "Pregão (2)",
  8 => "Dispensa",
  9 => "Inexigibilidade",
  10 => "PMI",
  11 => "Pré-qualificação",
  12 => "Credenciamento",
  13 => "Leilão (2)"
];

logar("🔄 Iniciando sincronização FULL PNCP ({$umAnoAtras->format('Y-m-d')} -> {$hoje->format('Y-m-d')})");

// Antes de começar a importar
// === Limpeza de processos antigos (> 365 dias) ===
$limite = (new DateTime())->modify('-365 days')->format('Y-m-d');

// Apagar itens vinculados primeiro (se não houver ON DELETE CASCADE)
$sqlItens = "
    DELETE FROM cache_pncp_itens
    WHERE numeroControlePNCP IN (
        SELECT numeroControlePNCP
        FROM cache_pncp_processos
        WHERE dataAbertura < ?
    )
";
$stmtItens = $pdo->prepare($sqlItens);
$stmtItens->execute([$limite]);
$apagadosItens = $stmtItens->rowCount();

// Agora apagar os processos
$sqlProc = "DELETE FROM cache_pncp_processos WHERE dataAbertura < ?";
$stmtProc = $pdo->prepare($sqlProc);
$stmtProc->execute([$limite]);
$apagadosProc = $stmtProc->rowCount();

logar("🧹 Removidos $apagadosProc processos e $apagadosItens itens anteriores a $limite");

foreach ($ufs as $uf) {
  foreach ($modalidades as $mod => $nomeMod) {

    // Intervalo menor apenas para modalidade 7
    $intervaloDias = ($mod == 7) ? 15 : 60;

    // Divide em janelas de datas
    $di = clone $umAnoAtras;
    while ($di < $hoje) {
      $df = (clone $di)->modify("+{$intervaloDias} days");
      if ($df > $hoje) $df = $hoje;

      logar("📅 Período {$di->format('Ymd')} → {$df->format('Ymd')} | UF=$uf / Mod=$mod ($nomeMod)");

      $pagina = 1;
      $continuar = true;

      while ($continuar) {
        $url = "https://pncp.gov.br/api/consulta/v1/contratacoes/publicacao?"
          . "dataInicial={$di->format('Ymd')}&dataFinal={$df->format('Ymd')}"
          . "&codigoModalidadeContratacao=$mod&uf=$uf"
          . "&pagina=$pagina&tamanhoPagina=$tamPagina";

        $resposta = consultarApi($url);

        if ($resposta === false) {
          logar("❌ Erro cURL/timeout ao consultar página $pagina ({$uf}/{$mod})");
          registrarFalha($pdo, "CURL_FAIL", "{$uf}-{$mod}-{$di->format('Ymd')}-p$pagina");
          break;
        }

        if ($resposta === null) {
          logar("⚠️ Janela inválida (HTTP 400) — pulando período {$di->format('Ymd')} → {$df->format('Ymd')} ($uf/$mod)");
          break; // ignora essa janela e vai pra próxima
        }

        if (empty($resposta['data'])) {
          logar("ℹ️ Página $pagina sem registros ($uf/$mod) [HTTP 204]");
          break; // sem conteúdo → próxima janela
        }

        $total = $resposta['totalRegistros'] ?? 0;
        $dados = $resposta['data'] ?? [];
        logar("✅ Página $pagina OK ($uf/$mod) – " . count($dados) . " registros");

        if (!empty($dados)) {
          foreach ($dados as $proc) {
            try {
              salvarProcesso($pdo, $proc);
              logar("💾 Processo {$proc['numeroControlePNCP']} salvo com sucesso");
            } catch (Throwable $e) {
              logar("⚠️ Erro ao salvar processo {$proc['numeroControlePNCP']}: " . $e->getMessage());
              registrarFalha($pdo, "ERRO_SALVAR_PROCESSO", $proc['numeroControlePNCP']);
              continue;
            }

            try {
              $okItens = salvarItensDoProcesso($pdo, $proc['numeroControlePNCP']);
              if (!$okItens) {
                registrarFalha($pdo, "ITENS_INDISPONIVEIS", $proc['numeroControlePNCP']);
                logar("⚠️ Itens indisponíveis/404 para {$proc['numeroControlePNCP']}");
              } else {
                logar("📦 Itens de {$proc['numeroControlePNCP']} salvos com sucesso");
              }
            } catch (Throwable $e) {
              registrarFalha($pdo, "ERRO_SALVAR_ITENS", $proc['numeroControlePNCP']);
              logar("⚠️ Erro ao salvar itens de {$proc['numeroControlePNCP']}: " . $e->getMessage());
            }
          }
        }

        $pagina++;
        $continuar = ($pagina - 1) * $tamPagina < $total;

        // Throttle entre páginas p/ reduzir 429/timeout
        usleep(200_000); // 200ms
      }

      // Próxima janela de datas
      $df->modify('+1 day'); // move 1 dia à frente
      $di = clone $df;       // define início da próxima janela
    }
  }
}
logar("✅ Finalizado");
