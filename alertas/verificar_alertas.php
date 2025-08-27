<?php
require_once '../../config/db.php';
require '../../vendor/autoload.php'; // PHPMailer via Composer


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


$dataHoje = new DateTime();


// Buscar contratos que vencem nos próximos 120 dias
$stmt = $pdo->query("SELECT c.*, u.email, u.alertas_config
FROM contratos c
JOIN usuarios u ON u.nivel_acesso = 'usuario'
WHERE c.data_fim >= CURDATE()");


$alertasDisparados = 0;
while ($linha = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $dataFim = new DateTime($linha['data_fim']);
  $diasRestantes = $dataHoje->diff($dataFim)->days;


  $config = json_decode($linha['alertas_config'] ?? '[]', true);
  if (!in_array($diasRestantes, $config)) continue;


  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host = 'smtp.agudos.sp.gov.br'; // ajustar
    $mail->SMTPAuth = true;
    $mail->Username = 'sistema@agudos.sp.gov.br'; // ajustar
    $mail->Password = 'senha'; // ajustar
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;


    $mail->setFrom('sistema@agudos.sp.gov.br', 'Sistema de Contratos');
    $mail->addAddress($linha['email']);


    $mail->isHTML(true);
    $mail->Subject = "[Alerta] Contrato vence em $diasRestantes dias";
    $mail->Body = "<p>O contrato nº <strong>{$linha['numero']}</strong> vence em <strong>$diasRestantes dias</strong>.<br>
Processo: {$linha['processo']}<br>
Órgão: {$linha['orgao']}<br>
Data fim: {$linha['data_fim']}<br></p>";
    $mail->send();
    $alertasDisparados++;
  } catch (Exception $e) {
    error_log("Erro ao enviar alerta: {$mail->ErrorInfo}");
  }
}


echo "Alertas enviados: $alertasDisparados";
