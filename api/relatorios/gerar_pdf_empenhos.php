<?php
require_once '../../config/db.php';
$pdo = Conexao::getInstance();

require '../../vendor/autoload.php';


use Mpdf\Mpdf;


$contrato = $_GET['contrato'] ?? '';
$data_de = $_GET['de'] ?? '';
$data_ate = $_GET['ate'] ?? '';


$query = "SELECT e.*, c.numero AS contrato_numero FROM empenhos e
JOIN contratos c ON c.id = e.contrato_id WHERE 1=1";
$params = [];


if ($contrato) {
  $query .= " AND c.numero LIKE ?";
  $params[] = "%$contrato%";
}
if ($data_de && $data_ate) {
  $query .= " AND e.data_empenho BETWEEN ? AND ?";
  $params[] = $data_de;
  $params[] = $data_ate;
}


$query .= " ORDER BY e.data_empenho DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$empenhos = $stmt->fetchAll(PDO::FETCH_ASSOC);


$html = '<h1>Relatório de Empenhos</h1><table border="1" cellspacing="0" cellpadding="5" width="100%">';
$html .= '<thead><tr><th>Contrato</th><th>Valor</th><th>Data</th><th>Fim Previsto</th></tr></thead><tbody>';


foreach ($empenhos as $e) {
  $html .= '<tr>';
  $html .= '<td>' . $e['contrato_numero'] . '</td>';
  $html .= '<td>R$ ' . number_format($e['valor_empenhado'], 2, ',', '.') . '</td>';
  $html .= '<td>' . date('d/m/Y', strtotime($e['data_empenho'])) . '</td>';
  $html .= '<td>' . date('d/m/Y', strtotime($e['data_fim_previsto'])) . '</td>';
  $html .= '</tr>';
}
$html .= '</tbody></table>';


$mpdf = new Mpdf();
$mpdf->WriteHTML($html);
$mpdf->Output('relatorio_empenhos.pdf', 'I');
