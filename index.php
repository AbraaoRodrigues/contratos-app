<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: templates/login.php');
  exit;
}
require_once 'config/db.php';
include 'templates/includes/header.php';


$contratosStmt = $pdo->query("SELECT COUNT(*) as total,
SUM(CASE WHEN data_fim >= CURDATE() THEN 1 ELSE 0 END) AS ativos,
SUM(CASE WHEN data_fim < CURDATE() THEN 1 ELSE 0 END) AS vencidos
FROM contratos");
$dados = $contratosStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" href="assets/css/style.css">
  <title>Dashboard</title>
</head>

<body class="<?= $modoEscuro ? 'dark' : '' ?>">
  <main style="padding: 2rem">
    <div class="logo">Sistema de Contratos - Agudos/SP</div>
    <h2>Resumo de Contratos</h2>
    <ul>
      <li>Total: <?= $dados['total'] ?></li>
      <li>Ativos: <?= $dados['ativos'] ?></li>
      <li>Vencidos: <?= $dados['vencidos'] ?></li>
    </ul>
  </main>
</body>

</html>
