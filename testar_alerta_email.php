<?php
require_once __DIR__ . '/config/db.php';
require __DIR__ . '/vendor/autoload.php';
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['nivel_acesso'] !== 'admin') {
  exit('Acesso restrito.');
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$pdo = Conexao::getInstance();

function enviarEmailTeste($email, $tipo)
{
  $mail = new PHPMailer(true);
  $mail->CharSet = 'UTF-8';

  try {
    $mail->isSMTP();
    $mail->Host = 'mail.agudos.sp.gov.br';
    $mail->SMTPAuth = true;
    $mail->Username = 'abraao.rodrigues@agudos.sp.gov.br';
    $mail->Password = '*1A2b3c45*'; // ← Trocar pela senha real
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('abraao.rodrigues@agudos.sp.gov.br', 'Sistema de Contratos');
    $mail->addAddress($email);

    $mail->isHTML(true);

    if ($tipo === 'saldo') {
      $mail->Subject = '[Teste] Alerta de SALDO baixo';
      $mail->Body = '<p>Este é um <strong>teste</strong> de alerta para <strong>saldo abaixo de 10%</strong>.</p>';
    } else {
      $mail->Subject = '[Teste] Alerta de VENCIMENTO de contrato';
      $mail->Body = '<p>Este é um <strong>teste</strong> de alerta para <strong>contrato próximo do vencimento</strong>.</p>';
    }

    $mail->send();
    return "✅ E-mail de <strong>$tipo</strong> enviado com sucesso para <strong>$email</strong>.";
  } catch (Exception $e) {
    return "❌ Erro ao enviar e-mail: {$mail->ErrorInfo}";
  }
}

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $tipo = $_POST['tipo'] ?? '';
  $stmt = $pdo->query("SELECT email FROM usuarios WHERE nivel_acesso = 'admin' LIMIT 1");
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($usuario && !empty($usuario['email'])) {
    $mensagem = enviarEmailTeste($usuario['email'], $tipo);
  } else {
    $mensagem = "❌ Nenhum e-mail de usuário encontrado.";
  }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <title>Teste de envio de alertas</title>
  <style>
    body {
      font-family: sans-serif;
      padding: 2rem;
    }

    form {
      display: flex;
      gap: 1rem;
      margin-bottom: 2rem;
    }

    button {
      padding: 0.5rem 1rem;
      cursor: pointer;
    }

    .resultado {
      margin-top: 1rem;
      font-weight: bold;
    }
  </style>
</head>

<body>
  <h2>Teste de envio de e-mail (alertas)</h2>
  <form method="post">
    <button type="submit" name="tipo" value="saldo">🔔 Testar Alerta de Saldo</button>
    <button type="submit" name="tipo" value="vencimento">📅 Testar Alerta de Vencimento</button>
  </form>

  <?php if ($mensagem): ?>
    <div class="resultado"><?= $mensagem ?></div>
  <?php endif; ?>
</body>

</html>
