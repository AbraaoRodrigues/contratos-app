<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: ./templates/login.php');
  exit;
}

require_once __DIR__ . '/config/db.php';
$pdo = Conexao::getInstance();
require_once __DIR__ . '/templates/includes/verifica_login.php';

$modoEscuro = $_SESSION['modo_escuro'] ?? false;
require_once __DIR__ . '/templates/includes/header.php';
?>

<body class="<?= $modoEscuro ? 'dark' : '' ?>">
  <main style="padding: 2rem">
    <div class="logo">Sistema de Contratos - Agudos/SP</div>
    <?php require_once __DIR__ . '/templates/dashboard.php'; ?>
  </main>

  <?php require_once __DIR__ . '/templates/includes/footer.php'; ?>
</body>

</html>
