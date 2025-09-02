<?php
require_once '../../config/db.php';
$pdo = Conexao::getInstance();

require '../../vendor/autoload.php';


use Mpdf\Mpdf;


$filtro = $_GET['filtro'] ?? '';
$data_de = $_GET['de'] ?? '';
$data_ate = $_GET['ate'] ?? '';


$query = "SELECT * FROM contratos WHERE 1=1";
$params = [];


if ($filtro) {
  $query .= " AND (numero LIKE ? OR orgao LIKE ?)";
  $params[] = "%$filtro%";
  $params[] = "%$filtro%";
}


if ($data_de && $data_ate) {
  $query .= " AND data_fim BETWEEN ? AND ?";
  $params[] = $data_de;
  $params[] = $data_ate;
}


$query .= " ORDER BY data_fim ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);


$html = '<h1>Relatório de Contratos</h1><table border="1" cellspacing="0" cellpadding="5" width="100%">';
$html .= '<thead><tr><th>Número</th><th>Processo</th><th>Órgão</th><th>Início</th><th>Fim</th><th>Valor</th></tr></thead><tbody>';


foreach ($contratos as $c) {
  $html .= '<tr>';
  $html .= '<td>' . $c['numero'] . '</td>';
  $html .= '<td>' . $c['processo'] . '</td>';
  $html .= '<td>' . $c['orgao'] . '</td>';
  $html .= '<td>' . date('d/m/Y', strtotime($c['data_inicio'])) . '</td>';
  $html .= '<td>' . date('d/m/Y', strtotime($c['data_fim'])) . '</td>';
  $html .= '<td>R$ ' . number_format($c['valor_total'], 2, ',', '.') . '</td>';
  $html .= '</tr>';
}
$html .= '</tbody></table>';


$mpdf = new Mpdf();
$mpdf->WriteHTML($html);
$mpdf->Output('relatorio_contratos.pdf', 'I');
