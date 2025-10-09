<?php
$logDir = __DIR__ . '/logs';


// === LISTAR LOGS DISPONÍVEIS ===
if (isset($_GET['list'])) {
  header('Content-Type: application/json; charset=utf-8');
  $files = glob($logDir . '/cron_*.log');
  if (!$files) {
    echo json_encode([]);
    exit;
  }
  usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
  $files = array_slice($files, 0, 15); // só os 15 mais recentes
  echo json_encode(array_map('basename', $files));
  exit;
}

// se veio ?file=, usa esse; caso contrário, pega o mais recente
if (!empty($_GET['file'])) {
  $logFile = realpath($logDir . '/' . basename($_GET['file']));
} else {
  $files = glob($logDir . '/cron_*.log');
  if (!$files) {
    $logFile = null;
  } else {
    // ordena por data de modificação, mais novo primeiro
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
    $logFile = $files[0];
  }
}

function no_cache_headers()
{
  header('Content-Type: text/plain; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
}

// === DESCARTAR (não apaga arquivo, só responde) ===
if (isset($_GET['clear'])) {
  header('Content-Type: text/plain; charset=utf-8');
  echo "DISCARDED";
  exit;
}

// === DOWNLOAD COMPLETO ===
if (isset($_GET['download'])) {
  if (!$logFile || !file_exists($logFile)) {
    http_response_code(404);
    echo "Arquivo não encontrado.";
    exit;
  }
  header('Content-Type: text/plain; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . basename($logFile) . '"');
  header('Content-Length: ' . filesize($logFile));
  readfile($logFile);
  exit;
}

// === LEITURA NORMAL / TAIL ===
no_cache_headers();
if (!$logFile || !file_exists($logFile)) {
  echo "Nenhum log encontrado.";
  exit;
}

$tailKb = isset($_GET['tailKb']) ? max(1, (int)$_GET['tailKb']) : 0;
$size = filesize($logFile);

if ($tailKb > 0 && $size > $tailKb * 1024) {
  $fp = fopen($logFile, 'rb');
  fseek($fp, -1 * $tailKb * 1024, SEEK_END);
  $out = stream_get_contents($fp);
  fclose($fp);
  echo $out;
} else {
  readfile($logFile);
}
