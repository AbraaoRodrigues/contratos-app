<?php
require __DIR__ . '/../../../vendor/autoload.php';

use Mpdf\Mpdf;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// --------- Entrada ---------
$formato = strtolower($_GET['formato'] ?? $_GET['tipo'] ?? 'pdf'); // aceita formato ou tipo
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
$itens = is_array($body) ? ($body['itens'] ?? []) : [];

// Caso queira ainda manter lista_id como fallback
if ((!$itens || !is_array($itens)) && isset($_GET['lista_id'])) {
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
}

// validação final
if (!$itens || !is_array($itens)) {
  header('Content-Type: application/json; charset=UTF-8');
  http_response_code(400);
  echo json_encode(['ok' => false, 'msg' => 'Nenhum item enviado para exportação.']);
  exit;
}

// --------- Cabeçalhos padrão ---------
$headers = ['ITEM', 'DESCRIÇÃO', 'QNT', 'VALOR', 'PNCP', 'ACESSO', 'MÉDIA UNI', 'VALOR ESTIMADO'];

// Geração de timestamp para nome do arquivo
$ts = date('Ymd_His');

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
  $html .= "<p style='margin-top:12px;font-size:12px;color:#555'>Documento gerado automaticamente via sistema PNCP.</p>";

  $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4-L']); // paisagem
  $mpdf->WriteHTML($html);
  $mpdf->Output("tabela_final_{$ts}.pdf", 'D');
  exit;
}

// --------- WORD (DOCX) ---------
if ($formato === 'word' || $formato === 'docx') {
  $phpWord = new PhpWord();
  $section = $phpWord->addSection(['orientation' => 'landscape']);
  $section->addText("Tabela Final de Referência de Preços", ['bold' => true, 'size' => 14]);

  $table = $section->addTable([
    'borderSize' => 6,
    'borderColor' => '999999',
    'cellMargin' => 80
  ]);

  // Cabeçalhos
  $table->addRow();
  foreach ($headers as $h) $table->addCell(2000)->addText($h, ['bold' => true]);

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

  header("Content-Description: File Transfer");
  header('Content-Disposition: attachment; filename="tabela_final_' . $ts . '.docx"');
  header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');

  $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
  $writer->save("php://output");
  exit;
}

// --------- EXCEL (XLSX) ---------
if ($formato === 'excel' || $formato === 'xlsx') {
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
    $desc  = (string)($row['descricao'] ?? '');
    $qtd   = (float)($row['quantidade'] ?? 0);
    $valor = (float)($row['valor_medio'] ?? 0);
    $refs  = (string)($row['referencias'] ?? '');
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
  $sheet->setCellValue("A{$rowNum}", "Documento gerado automaticamente via sistema PNCP.");
  $sheet->mergeCells("A{$rowNum}:H{$rowNum}");
  $sheet->getStyle("A{$rowNum}")->getFont()->setItalic(true)->setSize(10);

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="tabela_final_' . $ts . '.xlsx"');
  header('Cache-Control: max-age=0');

  $writer = new Xlsx($spreadsheet);
  $writer->save("php://output");
  exit;
}

// formato inválido
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(['ok' => false, 'msg' => 'Formato inválido.']);
