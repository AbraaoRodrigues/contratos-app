<?php
require __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

header('Content-Type: application/json; charset=UTF-8');

// Captura JSON do frontend
$body = json_decode(file_get_contents('php://input'), true);
$formato = strtolower($_GET['formato'] ?? 'pdf');
$itens   = $body['itens'] ?? [];

if (!$itens || !is_array($itens)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'msg' => 'Nenhum item enviado para exportação.']);
  exit;
}

$headers = ['Descrição', 'Valor Médio', 'Quantidade', 'Valor Total', 'Referências'];

// ===== PDF (mPDF) =====
if ($formato === 'pdf') {
  $html = '<h2>Tabela Final de Referência de Preços</h2>';
  $html .= '<table border="1" cellpadding="6" cellspacing="0" width="100%"><thead><tr>';
  foreach ($headers as $h) $html .= "<th>$h</th>";
  $html .= '</tr></thead><tbody>';
  foreach ($itens as $row) {
    $html .= '<tr>';
    $html .= '<td>' . htmlspecialchars($row['descricao']) . '</td>';
    $html .= '<td>R$ ' . number_format((float)$row['valor_medio'], 2, ',', '.') . '</td>';
    $html .= '<td>' . $row['quantidade'] . '</td>';
    $html .= '<td>R$ ' . number_format((float)$row['valor_total'], 2, ',', '.') . '</td>';
    $html .= '<td>' . htmlspecialchars($row['referencias']) . '</td>';
    $html .= '</tr>';
  }
  $html .= '</tbody></table>';

  $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
  $mpdf->WriteHTML($html);
  $mpdf->Output('tabela_final.pdf', 'I');
  exit;
}

// ===== Word (DOCX) =====
if ($formato === 'word') {
  $phpWord = new PhpWord();
  $section = $phpWord->addSection();
  $section->addText("Tabela Final de Referência de Preços", ['bold' => true, 'size' => 14]);

  $table = $section->addTable([
    'borderSize' => 6,
    'borderColor' => '999999',
    'cellMargin' => 80
  ]);
  // Cabeçalhos
  $table->addRow();
  foreach ($headers as $h) $table->addCell(2500)->addText($h, ['bold' => true]);

  // Dados
  foreach ($itens as $row) {
    $table->addRow();
    $table->addCell(4000)->addText($row['descricao']);
    $table->addCell(2000)->addText('R$ ' . number_format((float)$row['valor_medio'], 2, ',', '.'));
    $table->addCell(1500)->addText($row['quantidade']);
    $table->addCell(2000)->addText('R$ ' . number_format((float)$row['valor_total'], 2, ',', '.'));
    $table->addCell(3000)->addText($row['referencias']);
  }

  header("Content-Description: File Transfer");
  header('Content-Disposition: attachment; filename="tabela_final.docx"');
  header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');

  $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
  $writer->save("php://output");
  exit;
}

// ===== Excel (XLSX) =====
if ($formato === 'excel') {
  $spreadsheet = new Spreadsheet();
  $sheet = $spreadsheet->getActiveSheet();

  // Cabeçalhos
  $col = 1;
  foreach ($headers as $h) {
    $sheet->setCellValueByColumnAndRow($col, 1, $h);
    $col++;
  }

  // Dados
  $rowNum = 2;
  foreach ($itens as $row) {
    $sheet->setCellValueByColumnAndRow(1, $rowNum, $row['descricao']);
    $sheet->setCellValueByColumnAndRow(2, $rowNum, $row['valor_medio']);
    $sheet->setCellValueByColumnAndRow(3, $rowNum, $row['quantidade']);
    $sheet->setCellValueByColumnAndRow(4, $rowNum, $row['valor_total']);
    $sheet->setCellValueByColumnAndRow(5, $rowNum, $row['referencias']);
    $rowNum++;
  }

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="tabela_final.xlsx"');
  header('Cache-Control: max-age=0');

  $writer = new Xlsx($spreadsheet);
  $writer->save("php://output");
  exit;
}

echo json_encode(['ok' => false, 'msg' => 'Formato inválido.']);
