<?php

/**
 * Logger centralizado para os crons PNCP
 */

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
  mkdir($logDir, 0777, true);
}

$maxSize = 5 * 1024 * 1024; // 5 MB

// Nome base do log = nome do script
$nomeBase = basename($_SERVER['SCRIPT_NAME'], '.php');
$logFile  = $logDir . "/{$nomeBase}.log";

// Rotaciona se ficar grande
if (file_exists($logFile) && filesize($logFile) > $maxSize) {
  $backup = $logDir . '/' . $nomeBase . '_' . date('Ymd_His') . '.log';
  rename($logFile, $backup);
  file_put_contents($logFile, ""); // cria novo vazio
}

/**
 * Loga mensagem em console e arquivo
 */
function logar(string $msg): void
{
  global $logFile;
  $line = "[" . date('Y-m-d H:i:s') . "] $msg\n";
  echo $line;
  file_put_contents($logFile, $line, FILE_APPEND);
}

/**
 * Marca início de execução
 */
function logInicioExec(string $titulo = "Execução CRON"): void
{
  logar("============================");
  logar("🚀 {$titulo} iniciada");
  logar("============================");
}

/**
 * Marca fim de execução
 */
function logFimExec(float $inicioExec): void
{
  $tempoTotal = (int) round(microtime(true) - $inicioExec);
  $horas   = intdiv($tempoTotal, 3600);
  $minutos = intdiv($tempoTotal % 3600, 60);
  $segundos = $tempoTotal % 60;

  logar(sprintf("🏁 Finalizado em %02dh %02dm %02ds", $horas, $minutos, $segundos));
}
