<?php

/**
 * Logger centralizado para os crons PNCP
 */

$logDir = __DIR__ . '/../logs';   // sempre na pasta logs acima de crontab
if (!is_dir($logDir)) {
  mkdir($logDir, 0777, true);
}

// nome único com data/hora
$logFile = $logDir . '/cron_' . date('Ymd_His') . '.log';

// cria/abre arquivo atual
file_put_contents($logFile, "");
$maxSize = 5 * 1024 * 1024; // 5 MB

// Nome base do log
$nomeBase = basename($_SERVER['SCRIPT_NAME'], '.php'); // ex: cron_full_cache_mod9
$logFile = $logDir . "/{$nomeBase}.log";

// Rotaciona se arquivo ficar grande
if (file_exists($logFile) && filesize($logFile) > $maxSize) {
  $backup = $logDir . '/' . $nomeBase . '_' . date('Ymd_His') . '.log';
  rename($logFile, $backup);
  // Cria novo vazio para continuar logs em tempo real
  file_put_contents($logFile, '');
}

/**
 * Loga mensagem em console e arquivo
 */
function logar(string $msg): void
{
  global $logFile;
  $line = "[" . date('Y-m-d H:i:s') . "] $msg\n";
  echo $line; // mostra no terminal/navegador
  file_put_contents($logFile, $line, FILE_APPEND); // grava no arquivo
}

/**
 * Marca início de execução (com cabeçalho e reset visual)
 */
function logInicioExec(string $titulo = "Execução CRON"): void
{
  logar("============================");
  logar("🚀 {$titulo} iniciada");
  logar("============================");
}

/**
 * Marca fim de execução com duração total
 */
function logFimExec(float $inicioExec): void
{
  $tempoTotal = (int) round(microtime(true) - $inicioExec);
  $horas   = intdiv($tempoTotal, 3600);
  $minutos = intdiv($tempoTotal % 3600, 60);
  $segundos = $tempoTotal % 60;

  logar(sprintf("🏁 Finalizado em %02dh %02dm %02ds", $horas, $minutos, $segundos));
}
