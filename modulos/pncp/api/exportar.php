<?php
if (ob_get_length()) ob_end_clean();

require __DIR__ . '/../../../vendor/autoload.php';

use Mpdf\Mpdf;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// --------- Entrada ---------
$formato = strtolower($_GET['formato'] ?? $_GET['tipo'] ?? 'pdf');

// Lê itens do POST JSON
$raw = file_get_contents('php://input');
$body = json_decode($raw, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
$itens = (is_array($body) && isset($body['itens']) && is_array($body['itens'])) ? $body['itens'] : [];
file_put_contents(__DIR__ . '/../logs/debug_input.txt', date('Y-m-d H:i:s') . "\n" . $raw . "\n\n", FILE_APPEND);

// Se não veio pelo POST, tenta lista_id
if (!$itens && isset($_GET['lista_id'])) {
  require_once __DIR__ . '/../../../config/db_precos.php';
  $pdo = ConexaoPrecos::getInstance();

  $listaId = (int)$_GET['lista_id'];
  $sql = "SELECT descricao,
                   quantidade,
                   valor_medio,
                   (quantidade * valor_medio) AS valor_total,
                   referencias
            FROM lista_consolidada
            WHERE lista_id = ?
            ORDER BY descricao";
  $st = $pdo->prepare($sql);
  $st->execute([$listaId]);
  $itens = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Marca como acessado
  $upd = $pdo->prepare("UPDATE lista_consolidada SET acessado_em = NOW() WHERE lista_id = ?");
  $upd->execute([$listaId]);
}

// Validação final
if (!$itens || !is_array($itens)) {
  http_response_code(400);
  exit("Nenhum item para exportar.");
}

// --------- Cabeçalhos padrão ---------
$headers = ['ITEM', 'DESCRIÇÃO', 'QNT', 'VALOR', 'PNCP', 'ACESSO', 'MÉDIA UNI', 'VALOR ESTIMADO'];

// --------- PDF ---------
if ($formato === 'pdf') {
  $html = '<h2 style="margin:0 0 10px 0;">Tabela Final de Referência de Preços</h2>';
  $html .= '<table border="1" cellpadding="6" cellspacing="0" width="100%"><thead><tr>';
  foreach ($headers as $h) $html .= "<th>{$h}</th>";
  $html .= '</tr></thead><tbody>';

  $idx = 1;
  foreach ($itens as $row) {
    $desc  = htmlspecialchars($row['descricao'] ?? '');
    $qtd   = (float)($row['quantidade'] ?? 0);
    $valor = number_format((float)($row['valor_medio'] ?? 0), 2, ',', '.');
    $refs  = htmlspecialchars($row['referencias'] ?? '');
    $total = number_format($qtd * (float)($row['valor_medio'] ?? 0), 2, ',', '.');

    $html .= "<tr>
      <td>{$idx}</td>
      <td>{$desc}</td>
      <td>{$qtd}</td>
      <td>R$ {$valor}</td>
      <td>{$refs}</td>
      <td></td>
      <td>R$ {$valor}</td>
      <td>R$ {$total}</td>
    </tr>";
    $idx++;
  }

  $html .= '</tbody></table>';
  $html .= "<p style='margin-top:12px;font-size:12px;color:#555'>Esse documento foi gerado após consulta via API Pública PNCP.</p>";

  $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
  $mpdf->WriteHTML($html);
  $mpdf->Output('tabela_final.pdf', 'D');
  exit;
}

// --------- WORD (DOCX) ---------
if (in_array($formato, ['word', 'docx'])) {
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
  $idx = 1;
  foreach ($itens as $row) {
    $desc  = (string)($row['descricao'] ?? '');
    $qtd   = (float)($row['quantidade'] ?? 0);
    $valor = number_format((float)($row['valor_medio'] ?? 0), 2, ',', '.');
    $refs  = (string)($row['referencias'] ?? '');
    $total = number_format($qtd * (float)($row['valor_medio'] ?? 0), 2, ',', '.');

    $table->addRow();
    $table->addCell(1000)->addText((string)$idx);
    $table->addCell(6000)->addText($desc);
    $table->addCell(1500)->addText((string)$qtd);
    $table->addCell(2000)->addText('R$ ' . $valor);
    $table->addCell(3000)->addText($refs);
    $table->addCell(1500)->addText(''); // acesso
    $table->addCell(2000)->addText('R$ ' . $valor);
    $table->addCell(2000)->addText('R$ ' . $total);
    $idx++;
  }

  $section->addTextBreak(2);
  $section->addText("Esse documento foi gerado após consulta via API Pública PNCP.", ['italic' => true, 'size' => 10]);

  header("Content-Description: File Transfer");
  header('Content-Disposition: attachment; filename="tabela_final.docx"');
  header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');

  $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
  $writer->save("php://output");
  exit;
}

// --------- EXCEL (XLSX) ---------
if (in_array($formato, ['excel', 'xlsx'])) {
  if (ob_get_length()) ob_clean();
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
  $idx = 1;
  foreach ($itens as $row) {
    $desc  = mb_convert_encoding((string)($row['descricao'] ?? ''), 'UTF-8', 'UTF-8');
    $qtd   = (float)($row['quantidade'] ?? 0);
    $valor = (float)($row['valor_medio'] ?? 0);
    $refs  = mb_convert_encoding((string)($row['referencias'] ?? ''), 'UTF-8', 'UTF-8');
    $total = $qtd * $valor;

    $sheet->setCellValueByColumnAndRow(1, $rowNum, $idx);
    $sheet->setCellValueByColumnAndRow(2, $rowNum, $desc);
    $sheet->setCellValueByColumnAndRow(3, $rowNum, $qtd);
    $sheet->setCellValueByColumnAndRow(4, $rowNum, $valor);
    $sheet->setCellValueByColumnAndRow(5, $rowNum, $refs);
    $sheet->setCellValueByColumnAndRow(6, $rowNum, ''); // acesso
    $sheet->setCellValueByColumnAndRow(7, $rowNum, $valor);
    $sheet->setCellValueByColumnAndRow(8, $rowNum, $total);

    $rowNum++;
    $idx++;
  }

  // Rodapé
  $sheet->setCellValue("A{$rowNum}", "Esse documento foi gerado após consulta via API Pública PNCP.");
  $sheet->mergeCells("A{$rowNum}:E{$rowNum}");
  $sheet->getStyle("A{$rowNum}")->getFont()->setItalic(true)->setSize(10);

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment;');
  header('Cache-Control: max-age=0');

  $writer = new Xlsx($spreadsheet);
  $writer->save("php://output");
  exit;
}

// --------- Formato inválido ---------
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(['ok' => false, 'msg' => 'Formato inválido.']);
