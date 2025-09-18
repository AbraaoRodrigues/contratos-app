<?php
// Ajuste o caminho conforme sua estrutura:
$logFile = realpath(__DIR__ . '/logs/cron_full_cache.log');

function no_cache_headers()
{
  header('Content-Type: text/plain; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
}

// Limpar via POST (ou GET com ?clear=1)
if (isset($_GET['clear'])) {
  if (!$logFile) {
    http_response_code(404);
    echo "NOT_FOUND";
    exit;
  }
  // segurança mínima: só aceitar POST
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "METHOD_NOT_ALLOWED";
    exit;
  }
  file_put_contents($logFile, '');
  echo "CLEARED";
  exit;
}

// Download completo
if (isset($_GET['download'])) {
  if (!$logFile || !file_exists($logFile)) {
    http_response_code(404);
    echo "Arquivo de log não encontrado.";
    exit;
  }
  header('Content-Type: text/plain; charset=utf-8');
  header('Content-Disposition: attachment; filename="cron_full_cache.log"');
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
