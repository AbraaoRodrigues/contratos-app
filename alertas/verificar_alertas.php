<?php
require_once '../../config/db.php';
require '../../vendor/autoload.php'; // PHPMailer via Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$dataHoje = new DateTime();
$alertasPossiveis = [120, 90, 75, 60, 45, 30, 20, 10, 5];
$alertasDisparados = 0;

// Buscar todos os usuários ativos
$usuarios = $pdo->query("SELECT id, email, alertas_config FROM usuarios WHERE ativo = 1")->fetchAll(PDO::FETCH_ASSOC);

// Buscar contratos com data de fim futura
$contratos = $pdo->query("SELECT * FROM contratos WHERE data_fim >= CURDATE()")->fetchAll(PDO::FETCH_ASSOC);

foreach ($contratos as $contrato) {
  $dataFim = new DateTime($contrato['data_fim']);
  $diasRestantes = (int)$dataHoje->diff($dataFim)->format('%r%a');

  if (!in_array($diasRestantes, $alertasPossiveis)) continue;

  foreach ($usuarios as $usuario) {
    $config = json_decode($usuario['alertas_config'], true) ?? [];

    if (!in_array($diasRestantes, $config)) continue;

    // Enviar e-mail
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
      $mail->addAddress($usuario['email']);

      $mail->isHTML(true);
      $mail->Subject = "[Alerta] Contrato vence em $diasRestantes dias";
      $mail->Body = "
        <p>O contrato nº <strong>{$contrato['numero']}</strong> vence em <strong>$diasRestantes dias</strong>.</p>
        <p><strong>Processo:</strong> {$contrato['processo']}<br>
        <strong>Data fim:</strong> {$contrato['data_fim']}<br>
        <strong>Local arquivo:</strong> {$contrato['local_arquivo']}</p>
      ";

      $mail->send();
      $alertasDisparados++;
    } catch (Exception $e) {
      error_log("Erro ao enviar alerta para {$usuario['email']}: " . $mail->ErrorInfo);
    }
  }
}

echo "Alertas enviados: $alertasDisparados";
