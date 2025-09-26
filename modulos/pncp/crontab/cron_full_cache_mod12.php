<?php

/**
 * CRON individual — FULL CACHE por modalidade
 */

set_time_limit(0);
ini_set('memory_limit', '512M');
require_once __DIR__ . '/../../../config/db_precos.php';
require_once __DIR__ . '/funcoes_pncp.php';
require_once __DIR__ . '/logger.php';   // 👈 novo include

$pdo = ConexaoPrecos::getInstance();

// Configurações
$uf = 'SP';
$mod = 12;
$nomeMod = "Credenciamento";
$tamPagina = 50;
$intervaloDias = 60;

// Marca início
$inicioExec = microtime(true);
$inicio = new DateTime('-365 days');
$hoje   = new DateTime();

logInicioExec("Sincronização FULL PNCP - Credenciamento");

// Limpa registros antigos
$limite = $inicio->format('Y-m-d');
$delProc = $pdo->prepare("DELETE FROM cache_pncp_processos WHERE dataPublicacao < ?");
$delItens = $pdo->prepare("DELETE FROM cache_pncp_itens WHERE numeroControlePNCP NOT IN (
  SELECT numeroControlePNCP FROM cache_pncp_processos
)");
$delProc->execute([$limite]);
$delItens->execute();
logar("🧹 Removidos processos/itens anteriores a $limite");

// Loop por janelas
$di = clone $inicio;
while ($di < $hoje) {
  $df = (clone $di)->modify("+{$intervaloDias} days");
  if ($df > $hoje) $df = clone $hoje;

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
      logar("❌ Erro cURL/timeout ao consultar página $pagina ($uf/$mod)");
      registrarFalha($pdo, "CURL_FAIL", "$uf-$mod-{$di->format('Ymd')}-p$pagina");
      break;
    }

    if ($resposta === null) {
      logar("⚠️ HTTP 400 — janela inválida: $url");
      break;
    }

    $dados = $resposta['data'] ?? [];
    $total = $resposta['totalRegistros'] ?? 0;

    if (empty($dados)) {
      logar("ℹ️ Página $pagina sem registros ($uf/$mod)");
      break;
    }

    logar("✅ Página $pagina OK ($uf/$mod) – " . count($dados) . " registros");

    foreach ($dados as $proc) {
      try {
        salvarProcesso($pdo, $proc);
      } catch (Throwable $e) {
        registrarFalha($pdo, "ERRO_SALVAR_PROCESSO", $proc['numeroControlePNCP']);
        logar("⚠️ Erro ao salvar processo {$proc['numeroControlePNCP']}: " . $e->getMessage());
        continue;
      }

      try {
        $okItens = salvarItensDoProcesso($pdo, $proc['numeroControlePNCP']);
        if (!$okItens) {
          registrarFalha($pdo, "ITENS_INDISPONIVEIS", $proc['numeroControlePNCP']);
          logar("⚠️ Itens indisponíveis/404 para {$proc['numeroControlePNCP']}");
        } else {
          logar("📦 Itens {$proc['numeroControlePNCP']} salvos");
        }
      } catch (Throwable $e) {
        registrarFalha($pdo, "ERRO_SALVAR_ITENS", $proc['numeroControlePNCP']);
        logar("⚠️ Erro ao salvar itens {$proc['numeroControlePNCP']}: " . $e->getMessage());
      }
    }

    $pagina++;
    $continuar = ($pagina - 1) * $tamPagina < $total;

    usleep(200_000);
  }

  $di = (clone $df)->modify('+1 day');
}

// Marca fim
logFimExec($inicioExec);
