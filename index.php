<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: templates/login.php');
  exit;
}
require_once 'config/db.php';
include 'templates/includes/header.php';
require_once 'templates/includes/verifica_login.php';

$contratosStmt = $pdo->query("SELECT COUNT(*) as total,
SUM(CASE WHEN data_fim >= CURDATE() THEN 1 ELSE 0 END) AS ativos,
SUM(CASE WHEN data_fim < CURDATE() THEN 1 ELSE 0 END) AS vencidos
FROM contratos");
$dados = $contratosStmt->fetch(PDO::FETCH_ASSOC);

$dataHoje = new DateTime();

$stmt = $pdo->query("SELECT id, numero, objeto, data_fim FROM contratos WHERE data_fim IS NOT NULL ORDER BY data_fim ASC");

$contratosOrdenados = [];
while ($c = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $dataFim = new DateTime($c['data_fim']);
  $c['dias_restantes'] = (int)$dataHoje->diff($dataFim)->format('%r%a');
  $contratosOrdenados[] = $c;
}
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

  <section class="form-box">
    <h3>📋 Contratos ordenados por vencimento</h3>

    <table class="tabela-usuarios">
      <thead>
        <tr>
          <th>Número</th>
          <th>Objeto</th>
          <th>Data de Vencimento</th>
          <th>Dias Restantes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($contratosOrdenados as $c): ?>
          <tr>
            <td><?= htmlspecialchars($c['numero']) ?></td>
            <td><?= htmlspecialchars($c['objeto']) ?></td>
            <td><?= date('d/m/Y', strtotime($c['data_fim'])) ?></td>
            <td style="font-weight:bold; color: <?= $c['dias_restantes'] <= 30 ? '#d32f2f' : '#333' ?>">
              <?= $c['dias_restantes'] ?> dias
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

</body>

</html>
