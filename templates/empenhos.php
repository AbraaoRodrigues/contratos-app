<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: login.php');
  exit;
}
require_once '../config/db.php';
include 'includes/header.php';

// Lista de contratos para seleção
$contratos = $pdo->query("SELECT id, numero, valor_total FROM contratos ORDER BY numero ASC")->fetchAll(PDO::FETCH_ASSOC);

// Busca empenhos
$stmt = $pdo->query("SELECT e.*, c.numero AS contrato_numero FROM empenhos e
JOIN contratos c ON c.id = e.contrato_id
ORDER BY e.data_empenho DESC");
$empenhos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" href="../assets/css/style.css">
  <title>Empenhos</title>
</head>

<body class="<?= $_SESSION['modo_escuro'] ? 'dark' : '' ?>">
  <main style="padding:2rem; max-width:1000px; margin:auto">
    <h2>Empenhos</h2>

    <?php if ($msg): ?><p style="color:green;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>

    <h3>Cadastrar novo empenho</h3>
    <form action="../api/empenhos/salvar.php" method="post">
      <label>Contrato:<br>
        <select name="contrato_id" required>
          <?php foreach ($contratos as $c):
            $stmt = $pdo->prepare("SELECT SUM(valor_empenhado) FROM empenhos WHERE contrato_id = ?");
            $stmt->execute([$c['id']]);
            $totalEmp = $stmt->fetchColumn() ?: 0;
            $saldo = $c['valor_total'] - $totalEmp;
          ?>
            <option value="<?= $c['id'] ?>">
              <?= htmlspecialchars($c['numero']) ?> - Saldo: R$ <?= number_format($saldo, 2, ',', '.') ?>
            </option>
          <?php endforeach; ?>
        </select></label><br><br>

      <label>Valor Empenhado:<br>
        <input type="number" step="0.01" name="valor_empenhado" required></label><br><br>

      <label>Data do Empenho:<br>
        <input type="date" name="data_empenho" required></label><br><br>

      <label>Fim Previsto:<br>
        <input type="date" name="data_fim_previsto" required></label><br><br>

      <button type="submit">Salvar Empenho</button>
    </form>

    <hr>
    <h3>Lista de Empenhos</h3>
    <table border="1" cellpadding="5" cellspacing="0" style="width:100%;">
      <thead>
        <tr>
          <th>Contrato</th>
          <th>Valor Empenhado</th>
          <th>Data Empenho</th>
          <th>Fim Previsto</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($empenhos as $e): ?>
          <tr>
            <td><?= htmlspecialchars($e['contrato_numero']) ?></td>
            <td>R$ <?= number_format($e['valor_empenhado'], 2, ',', '.') ?></td>
            <td><?= date('d/m/Y', strtotime($e['data_empenho'])) ?></td>
            <td><?= date('d/m/Y', strtotime($e['data_fim_previsto'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </main>
</body>

</html>
