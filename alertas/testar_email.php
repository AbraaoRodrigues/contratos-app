<?php
require_once '../config/db.php';
require '../vendor/autoload.php'; // PHPMailer via Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

if (!isset($_SESSION['usuario_id'])) {
  exit('Acesso negado.');
}

$usuarioId = $_SESSION['usuario_id'];

$stmt = $pdo->prepare("SELECT email, nome FROM usuarios WHERE id = ?");
$stmt->execute([$usuarioId]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
  exit('Usuário não encontrado.');
}

$mail = new PHPMailer(true);

try {
  $mail->isSMTP();
  $mail->Host = 'mail.agudos.sp.gov.br'; // ajuste conforme necessário
  $mail->SMTPAuth = true;
  $mail->Username = 'abraao.rodrigues@agudos.sp.gov.br'; // ajuste conforme necessário
  $mail->Password = '*1A2b3c45*'; // ajuste conforme necessário
  $mail->SMTPSecure = 'starttls';
  $mail->Port = 587;

  $mail->CharSet = 'UTF-8'; // 👈 isso corrige os acentos
  $mail->setFrom('abraao.rodrigues@agudos.sp.gov.br', 'Sistema de Contratos');
  $mail->addAddress($usuario['email'], $usuario['nome']);

  $mail->isHTML(true);
  $mail->Subject = 'Teste de envio de e-mail';
  $mail->Body    = "<h3>Olá, {$usuario['nome']}!</h3><p>Este é um e-mail de teste enviado com sucesso.</p>";
  $mail->AltBody = "Olá, {$usuario['nome']}!\n\nEste é um e-mail de teste enviado com sucesso.";

  $mail->send();
  echo "✅ E-mail enviado para <strong>{$usuario['email']}</strong>";
} catch (Exception $e) {
  echo "❌ Erro ao enviar e-mail: {$mail->ErrorInfo}";
}
