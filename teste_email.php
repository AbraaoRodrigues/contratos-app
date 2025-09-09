<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.zoho.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'sistemas@agudos.digital';
    $mail->Password   = 'Mion@03122022';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('sistemas@agudos.digital', 'Sistemas PMA');
    $mail->addAddress('eder@ederbatera.com.br', 'Eder Machado');

    $mail->isHTML(true);
    $mail->Subject = 'Teste de envio';
    $mail->Body    = '<b>Olá!</b> Este é um e-mail de teste via PHPMailer.';
    $mail->AltBody = 'Olá! Este é um e-mail de teste via PHPMailer.';

    $mail->send();
    echo "✅ E-mail enviado com sucesso!";
} catch (Exception $e) {
    echo "❌ Erro ao enviar: {$mail->ErrorInfo}";
}
