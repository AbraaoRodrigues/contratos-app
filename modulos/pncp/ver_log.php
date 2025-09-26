<?php
// Pega arquivo base da pasta logs ou via parâmetro
$logParam = $_GET['file'] ?? 'cron_full_cache.log';
$logFile = realpath(__DIR__ . '/logs/' . basename($logParam));

function no_cache_headers()
{
  header('Content-Type: text/plain; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
}

// Descarta log (não apaga arquivo)
if (isset($_GET['clear'])) {
  header('Content-Type: text/plain; charset=utf-8');
  echo "DISCARDED";
  exit;
}

// Download
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

// Leitura normal (com tail opcional)
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
