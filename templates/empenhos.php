<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: login.php');
  exit;
}
require_once '../config/db.php';
include 'includes/header.php';
require_once '../templates/includes/verifica_login.php';

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
    <h3>Cadastrar novo empenho</h3>

    <form action="../api/empenhos/salvar.php" method="post" class="form-box" style="max-width: 600px; margin: 0 auto;">
      <label>Contrato:
        <select name="contrato_id">
          <option value="">-- Sem contrato vinculado --</option>
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
        </select>
      </label>

      <label>Valor Empenhado:
        <input type="text" name="valor_empenhado" required>
      </label>

      <label>Data do Empenho:
        <input type="date" name="data_empenho" required>
      </label>

      <label>Fim Previsto:
        <input type="date" name="data_fim_previsto" required>
      </label>

      <button type="submit">Salvar Empenho</button>
    </form>

    <hr>
    <h3 style="margin-top: 3rem;">Lista de Empenhos</h3>

    <table class="tabela-usuarios"> <!-- ou use tabela-empenhos -->
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
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const valorInput = document.querySelector('input[name="valor_empenhado"]');

      valorInput.addEventListener('input', function() {
        let valor = this.value.replace(/\D/g, '');
        valor = (parseFloat(valor) / 100).toFixed(2);
        valor = valor.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        this.value = valor;
      });
    });
  </script>

</body>

</html>
