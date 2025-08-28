<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: login.php');
  exit;
}
require_once '../config/db.php';
include 'includes/header.php';
require_once '../templates/includes/verifica_login.php';

$filtro = $_GET['filtro'] ?? '';
$data_de = $_GET['de'] ?? '';
$data_ate = $_GET['ate'] ?? '';


$query = "SELECT * FROM contratos WHERE 1=1";
$params = [];


if ($filtro) {
  $query .= " AND (numero LIKE ? OR orgao LIKE ?)";
  $params[] = "%$filtro%";
  $params[] = "%$filtro%";
}


if ($data_de && $data_ate) {
  $query .= " AND data_fim BETWEEN ? AND ?";
  $params[] = $data_de;
  $params[] = $data_ate;
}


$query .= " ORDER BY data_fim ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Relatórios</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="<?= $_SESSION['modo_escuro'] ? 'dark' : '' ?>">
  <main style="padding:2rem; max-width:1100px; margin:auto;">
    <h2>Relatórios</h2>


    <form method="get" style="margin-bottom: 2rem;">
      <input type="text" name="filtro" placeholder="Contrato ou fornecedor" value="<?= htmlspecialchars($filtro) ?>">
      <input type="date" name="de" value="<?= $data_de ?>">
      <input type="date" name="ate" value="<?= $data_ate ?>">
      <button type="submit">Filtrar</button>
      <a href="relatorios.php" class="link-acao link-editar">Limpar</a>
      <a href="../api/relatorios/gerar_pdf.php?<?= http_build_query($_GET) ?>" target="_blank" class="link-acao link-editar">Exportar PDF</a>
    </form>


    <table border="1" cellpadding="5" cellspacing="0" width="100%">
      <thead>
        <tr>
          <th>Número</th>
          <th>Processo</th>
          <th>Órgão</th>
          <th>Início</th>
          <th>Fim</th>
          <th>Valor</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($contratos)): ?>
          <tr>
            <td colspan="6">Nenhum contrato encontrado.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($contratos as $c): ?>
            <tr>
              <td><?= $c['numero'] ?></td>
              <td><?= $c['processo'] ?></td>
              <td><?= $c['fornecedor'] ?></td>
              <td><?= date('d/m/Y', strtotime($c['data_inicio'])) ?></td>
              <td><?= date('d/m/Y', strtotime($c['data_fim'])) ?></td>
              <td>R$ <?= number_format($c['valor_total'], 2, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </main>
</body>

</html>
