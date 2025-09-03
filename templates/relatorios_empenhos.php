<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: login.php');
  exit;
}
require_once '../config/db.php';
$pdo = Conexao::getInstance();
include 'includes/header.php';
require_once './includes/verifica_login.php';
include './includes/modal_exclusao.php';

$filtro = $_GET['filtro'] ?? '';
$data_de = $_GET['de'] ?? '';
$data_ate = $_GET['ate'] ?? '';

$query = "SELECT e.*, c.numero AS contrato_numero
          FROM empenhos e
          LEFT JOIN contratos c ON e.contrato_id = c.id
          WHERE e.status != 'excluido'";
$params = [];

if ($filtro) {
  $query .= " AND (e.numero_empenho LIKE ? OR e.fornecedor LIKE ?)";
  $params[] = "%$filtro%";
  $params[] = "%$filtro%";
}

if ($data_de && $data_ate) {
  $query .= " AND e.data_fim_previsto BETWEEN ? AND ?";
  $params[] = $data_de;
  $params[] = $data_ate;
}

$query .= " ORDER BY e.data_fim_previsto ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$empenhos = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Empenhos</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="<?= $_SESSION['modo_escuro'] ? 'dark' : '' ?>">
  <main style="padding:2rem; max-width:1100px; margin:auto;">
    <h2>Relatório de Empenhos</h2>

    <form method="get" style="margin-bottom: 2rem;">
      <input type="text" name="filtro" placeholder="Empenho ou fornecedor" value="<?= htmlspecialchars($filtro) ?>">
      <input type="date" name="de" value="<?= $data_de ?>">
      <input type="date" name="ate" value="<?= $data_ate ?>">
      <button type="submit">Filtrar</button>
      <a href="relatorios.php" class="link-acao link-editar">Limpar</a>
      <a href="../api/relatorios/gerar_pdf.php?<?= http_build_query($_GET) ?>" target="_blank" class="link-acao link-editar">Exportar PDF</a>
    </form>

    <table class="tabela-relatorio">
      <thead>
        <tr>
          <th>Número do Empenho</th>
          <th>Fornecedor</th>
          <th>Valor Empenhado</th>
          <th>Data Fim Previsto</th>
          <th>Contrato Vinculado</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($empenhos as $e): ?>
          <tr>
            <td><?= htmlspecialchars($e['numero_empenho']) ?></td>
            <td><?= htmlspecialchars($e['fornecedor']) ?></td>
            <td>R$ <?= number_format($e['valor_empenhado'], 2, ',', '.') ?></td>
            <td><?= date('d/m/Y', strtotime($e['data_fim_previsto'])) ?></td>
            <td><?= $e['contrato_numero'] ?? '-' ?></td>
            <td>
              <a href="editar_empenho.php?id=<?= $e['id'] ?>" class="link-acao link-editar">Editar</a>
              |
              <a href="#" onclick="abrirModalExclusao(<?= $e['id'] ?>, '../api/empenhos/excluir.php', 'Excluir Empenho'); return false;" class="link-acao link-excluir">Excluir</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </main>

  <?php include 'includes/footer.php'; ?>

  <!-- Modal para justificativa -->
  <div id="modalJustificativa" class="modal" style="display:none">
    <div class="modal-conteudo">
      <h3>Justifique a exclusão</h3>
      <form id="formExcluirEmpenho" method="post" action="../api/empenhos/excluir.php">
        <input type="hidden" name="id" id="idEmpenho">
        <textarea name="justificativa" required placeholder="Informe o motivo da exclusão..."></textarea>
        <div class="botoes">
          <button type="submit">Confirmar Exclusão</button>
          <button type="button" onclick="document.getElementById('modalJustificativa').style.display='none'">Cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function confirmarExclusao(id) {
      document.getElementById('idEmpenho').value = id;
      document.getElementById('modalJustificativa').style.display = 'block';
    }
  </script>
