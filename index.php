<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: templates/login.php');
  exit;
}
require_once 'config/db.php';
require_once 'templates/includes/verifica_login.php';

$modoEscuro = $_SESSION['modo_escuro'] ?? false;
include 'templates/includes/header.php';
?>

<body class="<?= $modoEscuro ? 'dark' : '' ?>">
  <main style="padding: 2rem">
    <div class="logo">Sistema de Contratos - Agudos/SP</div>
    <?php include 'templates/dashboard.php'; ?>
  </main>

  <?php include 'templates/includes/footer.php'; ?>
</body>

</html>
