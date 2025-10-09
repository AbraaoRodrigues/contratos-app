<?php
require __DIR__ . '/../../../vendor/autoload.php';

use Mpdf\Mpdf;

$mpdf = new Mpdf([
  'tempDir' => __DIR__ . '/../../../tmp/mpdf'  // aponta para a pasta criada
]);

$html = "<h1>Teste PDF</h1><p>Gerado em " . date('d/m/Y H:i:s') . "</p>";
$mpdf->WriteHTML($html);
$mpdf->Output("teste.pdf", "D");
exit;
