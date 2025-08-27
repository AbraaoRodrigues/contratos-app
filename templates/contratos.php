<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: login.php');
  exit;
}
require_once '../config/db.php';
include 'includes/header.php';


// Filtros
$filtro = $_GET['filtro'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM contratos WHERE numero LIKE ? OR orgao LIKE ? ORDER BY data_fim ASC");
$stmt->execute(["%$filtro%", "%$filtro%"]);
$contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" href="../assets/css/style.css">
  <title>Contratos</title>
</head>

<body class="<?= $_SESSION['modo_escuro'] ? 'dark' : '' ?>">
  <main style="padding:2rem; max-width:1100px; margin:auto;">
    <h2>Contratos</h2>


    <?php if ($msg): ?><p style="color:green;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>


    <form method="get">
      <input type="text" name="filtro" placeholder="Buscar por número ou órgão" value="<?= htmlspecialchars($filtro) ?>">
      <button type="submit">Filtrar</button>
      <a href="contratos.php">Limpar</a>
    </form>


    <table border="1" cellpadding="5" cellspacing="0" style="margin-top: 1rem; width: 100%;">
      <thead>
        <tr>
          <th>Número</th>
          <th>Processo</th>
          <th>Órgão</th>
          <th>Valor</th>
          <th>Saldo Atual</th>
          <th>Início</th>
          <th>Fim</th>
          <th>Arquivo</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($contratos) === 0): ?>
          <tr>
            <td colspan="8">Nenhum contrato encontrado.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($contratos as $c): ?>
            <tr>
              <td><?= htmlspecialchars($c['numero']) ?></td>
              <td><?= htmlspecialchars($c['processo']) ?></td>
              <td><?= htmlspecialchars($c['orgao']) ?></td>
              <td>R$ <?= number_format($c['valor_total'], 2, ',', '.') ?></td>
              <?php
              $stmt = $pdo->prepare("SELECT SUM(valor_empenhado) FROM empenhos WHERE contrato_id = ?");
              $stmt->execute([$c['id']]);
              $empenhado = $stmt->fetchColumn() ?: 0;
              $saldo = $c['valor_total'] - $empenhado;
              ?>
              <td>R$ <?= number_format($saldo, 2, ',', '.') ?></td>
              <td><?= date('d/m/Y', strtotime($c['data_inicio'])) ?></td>
              <td><?= date('d/m/Y', strtotime($c['data_fim'])) ?></td>
              <td><?= $c['local_arquivo'] ?></td>
              <td>
                <a href="editar_contrato.php?id=<?= $c['id'] ?>">Editar</a> |
                <a href="../api/contratos/excluir.php?id=<?= $c['id'] ?>" onclick="return confirm('Tem certeza que deseja excluir este contrato?')">Excluir</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>


    <hr>
    <h3>Cadastrar novo contrato</h3>
    <form action="../api/contratos/salvar.php" method="post">
      <!-- (mesmo formulário de cadastro anterior aqui) -->
      <label>Número do Contrato:<br>
        <input type="text" name="numero" required></label><br><br>
      <label>Número do Processo:<br>
        <input type="text" name="processo" required></label><br><br>
      <label>Órgão:<br>
        <input type="text" name="orgao" required></label><br><br>
      <label>Valor Total:<br>
        <input type="number" name="valor_total" step="0.01" required></label><br><br>
      <label>Data Início:<br>
        <input type="date" name="data_inicio" required></label><br><br>
      <label>Data Fim:<br>
        <input type="date" name="data_fim" required></label><br><br>
      <label>Local do Arquivo:<br>
        <select name="local_arquivo">
          <option value="1Doc">1Doc</option>
          <option value="Papel">Papel</option>
        </select></label><br><br>
      <label>Observações:<br>
        <textarea name="observacoes"></textarea></label><br><br>
      <button type="submit">Salvar Contrato</button>
    </form>
  </main>
</body>

</html>
