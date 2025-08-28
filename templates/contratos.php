<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: login.php');
  exit;
}
require_once '../config/db.php';
include 'includes/header.php';
require_once '../templates/includes/verifica_login.php';

// Filtros
$filtro = $_GET['filtro'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM contratos WHERE numero LIKE ? OR fornecedor LIKE ? ORDER BY data_fim ASC");
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

    <hr>
    <h3>Cadastrar novo contrato</h3>
    <form action="../api/contratos/salvar.php" method="post" class="form-box">
      <div class="form-row">
        <label>Número do Contrato:
          <input type="text" name="numero" required></label>
        <label>Número do Processo:
          <input type="text" name="processo" required></label>
      </div>

      <div class="form-row">
        <label>Fornecedor:
          <input type="text" name="fornecedor"></label>
        <label>Responsável:
          <input type="text" name="responsavel"></label>
      </div>

      <label>Objeto:
        <textarea name="objeto" rows="2"></textarea></label>

      <div class="form-row">
        <label>Data Assinatura:
          <input type="date" name="data_assinatura"></label>
        <label>Início Vigência:
          <input type="date" name="data_inicio" required></label>
        <label>Fim Vigência:
          <input type="date" name="data_fim" required></label>
      </div>

      <div class="form-row">
        <label>Valor Total:<br>
          <input type="text" name="valor_total" id="valor_total" required></label>
        <label>Prorrogável<select name="prorrogavel">
            <option value="0">Não</option>
            <option value="1">Sim</option>
          </select></label>
        <label>Local Arquivo:
          <select name="local_arquivo">
            <option value="1Doc">1Doc</option>
            <option value="Papel">Papel</option>
          </select></label>
      </div>

      <label>Observações:
        <textarea name="observacoes" rows="2"></textarea></label>

      <button type="submit">Salvar Contrato</button>
    </form>

    <form method="get">
      <input type="text" name="filtro" placeholder="Buscar por número ou fornecedor" value="<?= htmlspecialchars($filtro) ?>">
      <button type="submit">Filtrar</button>
      <a href="contratos.php" class="link-acao link-editar">Limpar</a>
    </form>

    <table border="1" cellpadding="5" cellspacing="0" style="margin-top: 1rem; width: 100%;">
      <thead>
        <tr>
          <th>Número</th>
          <th>Processo</th>
          <th>Fornecedor</th>
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
              <td><?= htmlspecialchars($c['fornecedor']) ?></td>
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
                <a href="editar_contrato.php?id=<?= $c['id'] ?>" class="link-acao link-editar">Editar</a> |
                <a href="../api/contratos/excluir.php?id=<?= $c['id'] ?>" onclick="return confirm('Tem certeza que deseja excluir este contrato?')" class="link-acao link-editar">Excluir</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

  </main>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const input = document.getElementById('valor_total');

      input.addEventListener('input', function() {
        let v = input.value.replace(/\D/g, '');
        v = (parseFloat(v) / 100).toFixed(2) + '';
        v = v.replace('.', ',');
        v = v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        input.value = v;
      });
    });
  </script>

</body>

</html>
