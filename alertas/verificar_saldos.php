<?php
require_once '../config/db.php';
$pdo = Conexao::getInstance();

require '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


// Consulta contratos com saldo < 10% do valor total
$stmt = $pdo->query("SELECT c.*, u.email
FROM contratos c
JOIN usuarios u ON u.nivel_acesso = 'usuario'
LEFT JOIN (
SELECT contrato_id, SUM(valor_empenhado) AS total_empenhado
FROM empenhos
GROUP BY contrato_id
) e ON e.contrato_id = c.id
WHERE (c.valor_total - IFNULL(e.total_empenhado, 0)) < (c.valor_total * 0.1)");


$enviados = 0;
while ($c = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $saldo = $c['valor_total'] - ($c['total_empenhado'] ?? 0);


  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host = 'smtp.agudos.sp.gov.br';
    $mail->SMTPAuth = true;
    $mail->Username = 'sistema@agudos.sp.gov.br';
    $mail->Password = 'senha';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;


    $mail->setFrom('sistema@agudos.sp.gov.br', 'Sistema de Contratos');
    $mail->addAddress($c['email']);


    $mail->isHTML(true);
    $mail->Subject = "[Alerta] Saldo insuficiente para contrato nº {$c['numero']}";
    $mail->Body = "<p>O contrato nº <strong>{$c['numero']}</strong> está com saldo abaixo de 10%.<br>
Processo: {$c['processo']}<br>
Órgão: {$c['orgao']}<br>
Saldo atual: R$ " . number_format($saldo, 2, ',', '.') . "</p>";
    $mail->send();
    $enviados++;
  } catch (Exception $e) {
    error_log("Erro saldo insuficiente: {$mail->ErrorInfo}");
  }
}


echo "Alertas de saldo enviados: $enviados";
