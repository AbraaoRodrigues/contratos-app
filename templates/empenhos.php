<?php
session_start();
require_once '../config/db.php';
$pdo = Conexao::getInstance();

require_once 'includes/verifica_login.php';

// Pega os contratos
$stmt = $pdo->query("SELECT id, numero, objeto, fornecedor, observacoes, valor_total FROM contratos");
$contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lista todos os empenhos (com ou sem contrato)
$stmtEmp = $pdo->query("SELECT e.*, c.numero AS contrato_numero
  FROM empenhos e
  LEFT JOIN contratos c ON e.contrato_id = c.id
  ORDER BY e.data_empenho DESC");
$empenhos = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);

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

  <?php include 'includes/header.php'; ?>

  <main style="padding:2rem; max-width:900px; margin:auto;">
    <h2>Empenhos</h2>

    <?php if ($msg): ?>
      <p style="color:green; font-weight:bold;"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>

    <form action="../api/empenhos/salvar.php" method="post" class="form-box">
      <div class="form-row">
        <label>Contrato:
          <select name="contrato_id" id="contrato_id" required>
            <option value="0">Sem vínculo</option>
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

        <label>Número do Empenho:
          <input type="text" name="numero_empenho" required>
        </label>

        <label>Valor Empenhado:
          <input type="text" name="valor_empenhado" id="valor_empenhado" required>
        </label>

        <label>Data do Empenho:
          <input type="date" name="data_empenho" required>
        </label>

        <label>Fim Previsto:
          <input type="date" name="data_fim_previsto" required>
        </label>

        <label>Objeto:
          <textarea name="objeto" id="objeto" rows="2" readonly></textarea>
        </label>

        <label>Fornecedor:
          <input type="text" name="fornecedor" id="fornecedor" readonly>
        </label>

        <label>Observações:
          <textarea name="observacoes" id="observacoes" rows="2" readonly></textarea>
        </label>
      </div>

      <button type="submit">Salvar Empenho</button>
    </form>

    <hr>
    <h3>Lista de Empenhos</h3>
    <table class="tabela-usuarios">
      <thead>
        <tr>
          <th>Contrato</th>
          <th>Número</th>
          <th>Valor Empenhado</th>
          <th>Data Empenho</th>
          <th>Fim Previsto</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($empenhos as $e): ?>
          <tr>
            <td><?= $e['contrato_numero'] ?? 'Sem vínculo' ?></td>
            <td><?= htmlspecialchars($e['numero_empenho'] ?? '-') ?></td>
            <td>R$ <?= number_format($e['valor_empenhado'], 2, ',', '.') ?></td>
            <td><?= date('d/m/Y', strtotime($e['data_empenho'])) ?></td>
            <td><?= date('d/m/Y', strtotime($e['data_fim_previsto'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </main>

  <!-- JSON com dados dos contratos -->
  <script>
    const contratosInfo = <?= json_encode(array_column($contratos, null, 'id')) ?>;
  </script>

  <!-- JS para preencher os campos -->
  <script>
    const contratoSelect = document.getElementById('contrato_id');
    const objetoInput = document.getElementById('objeto');
    const fornecedorInput = document.getElementById('fornecedor');
    const observacoesInput = document.getElementById('observacoes');

    contratoSelect.addEventListener('change', () => {
      const contratoId = contratoSelect.value;

      if (contratoId === '0') {
        objetoInput.value = '';
        fornecedorInput.value = '';
        observacoesInput.value = '';
        objetoInput.removeAttribute('readonly');
        fornecedorInput.removeAttribute('readonly');
        observacoesInput.removeAttribute('readonly');
      } else {
        const info = contratosInfo[contratoId];
        objetoInput.value = info.objeto || '';
        fornecedorInput.value = info.fornecedor || '';
        observacoesInput.value = info.observacoes || '';
        objetoInput.setAttribute('readonly', true);
        fornecedorInput.setAttribute('readonly', true);
        observacoesInput.setAttribute('readonly', true);
      }
    });

    // Força atualização dos campos ao carregar a página
    contratoSelect.dispatchEvent(new Event('change'));
  </script>

  <!-- Máscara de moeda -->
  <script>
    const inputValor = document.getElementById('valor_empenhado');
    inputValor.addEventListener('input', function() {
      let v = inputValor.value.replace(/\D/g, '');
      v = (parseFloat(v) / 100).toFixed(2) + '';
      v = v.replace('.', ',');
      v = v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      inputValor.value = v;
    });
  </script>

</body>

</html>
