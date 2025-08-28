<?php session_start();

require_once '../templates/includes/verifica_login.php';
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <title>Relatórios de Empenhos</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="<?= isset($_SESSION['modo_escuro']) && $_SESSION['modo_escuro'] ? 'dark' : '' ?>">
  <main style="padding:2rem; max-width:1100px; margin:auto;">
    <h2>Relatórios de Empenhos</h2>
    <form method="get" style="margin-bottom: 2rem;">
      <input type="text" name="contrato" placeholder="Número do contrato" value="<?= htmlspecialchars($contrato) ?>">
      <input type="date" name="de" value="<?= $data_de ?>">
      <input type="date" name="ate" value="<?= $data_ate ?>">
      <button type="submit">Filtrar</button>
      <a href="relatorios_empenhos.php">Limpar</a>
      <a href="../api/relatorios/gerar_pdf_empenhos.php?<?= http_build_query($_GET) ?>" target="_blank">Exportar PDF</a>
    </form>


    <table border="1" cellpadding="5" cellspacing="0" width="100%">
      <thead>
        <tr>
          <th>Contrato</th>
          <th>Valor Empenhado</th>
          <th>Data Empenho</th>
          <th>Fim Previsto</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($empenhos)): ?>
          <tr>
            <td colspan="4">Nenhum empenho encontrado.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($empenhos as $e): ?>
            <tr>
              <td><?= $e['contrato_numero'] ?></td>
              <td>R$ <?= number_format($e['valor_empenhado'], 2, ',', '.') ?></td>
              <td><?= date('d/m/Y', strtotime($e['data_empenho'])) ?></td>
              <td><?= date('d/m/Y', strtotime($e['data_fim_previsto'])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </main>
</body>

</html>
