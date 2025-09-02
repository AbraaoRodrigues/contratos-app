<?php
require_once '../../config/db.php';
$pdo = Conexao::getInstance();



function obterSaldoContrato($contrato_id)
{
  global $pdo;


  $stmt = $pdo->prepare("SELECT valor_total FROM contratos WHERE id = ?");
  $stmt->execute([$contrato_id]);
  $contrato = $stmt->fetch(PDO::FETCH_ASSOC);


  $stmt = $pdo->prepare("SELECT SUM(valor_empenhado) as total_empenhado FROM empenhos WHERE contrato_id = ?");
  $stmt->execute([$contrato_id]);
  $empenhado = $stmt->fetchColumn();


  $empenhado = $empenhado ?: 0;
  $saldo = $contrato['valor_total'] - $empenhado;


  return number_format($saldo, 2, ',', '.');
}
