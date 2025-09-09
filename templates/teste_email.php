<?php
require_once '../config/db.php';
$pdo = Conexao::getInstance();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';
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

if (filter_var($usuario['email'], FILTER_VALIDATE_EMAIL)) {
  $mail->addAddress($usuario['email'], $usuario['nome']);
} else {
  exit("❌ E-mail inválido.");
}

$mail = new PHPMailer(true);

try {
  // Configurações do servidor SMTP
  $mail->isSMTP();
  $mail->Host = 'mail.agudos.sp.gov.br';        // 🔁 Altere para seu SMTP
  $mail->SMTPAuth = true;
  $mail->Username = 'abraao.rodrigues@agudos.sp.gov.br';        // 🔁 Altere
  $mail->Password = '*1A2b3c45*';                   // 🔁 Altere
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port       = 587;

  // Remetente e destinatário
  $mail->CharSet = 'UTF-8'; // 👈 isso corrige os acentos
  $mail->setFrom('abraao.rodrigues@agudos.sp.gov.br', 'Sistema de Contratos');
  $mail->addAddress($usuario['email'], $usuario['nome']);

  // Conteúdo
  $mail->isHTML(true);
  $mail->Subject = '🧪 Teste de e-mail via PHPMailer';
  $mail->Body    = '<h3>Funcionou!</h3><p>Este é um teste de envio de e-mail usando PHPMailer.</p>';
  $mail->AltBody = 'Funcionou! Este é um teste de envio de e-mail usando PHPMailer.';

  $mail->send();
  echo '✅ E-mail enviado com sucesso!';
} catch (Exception $e) {
  echo "❌ Erro ao enviar: {$mail->ErrorInfo}";
}
