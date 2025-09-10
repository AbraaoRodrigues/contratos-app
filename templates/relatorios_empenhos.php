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
    <?php if (isset($_GET['msg'])): ?>
      <p style="color:green;">
      <div class="alerta-sucesso"><?= htmlspecialchars($_GET['msg']) ?></div>
      </p>
    <?php endif; ?>

    <form method="get" style="margin-bottom: 2rem;">
      <input type="text" name="filtro" placeholder="Empenho ou fornecedor" value="<?= htmlspecialchars($filtro) ?>">
      <input type="date" name="de" value="<?= $data_de ?>">
      <input type="date" name="ate" value="<?= $data_ate ?>">
      <button type="submit">Filtrar</button>
      <a href="relatorios_empenhos.php" class="link-acao link-editar">Limpar</a>
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
    <div class="modal-content">
      <h3>Justifique a exclusão</h3>
      <form id="formExcluirEmpenho" method="post" action="../api/empenhos/excluir.php">
        <input type="hidden" name="id" id="idEmpenho">

        <!-- Corrigido: ID diferente e name correto -->
        <textarea id="justificativaExclusao" name="justificativa_exclusao" required placeholder="Informe o motivo da exclusão..."></textarea>

        <div class="botoes">
          <button type="submit" class="btn-perigo">Confirmar Exclusão</button>
          <button type="button" onclick="document.getElementById('modalJustificativa').style.display='none'">Cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function abrirModalExclusao(id, action, titulo) {
      const modal = document.getElementById('modalJustificativa');
      const form = document.getElementById('formExcluirEmpenho');

      document.getElementById('idEmpenho').value = id;
      form.action = action;
      modal.querySelector('h3').innerText = titulo;
      modal.style.display = 'block';
      document.getElementById('modalJustificativa').style.display = 'flex';

    }

    document.getElementById('formExcluirEmpenho').addEventListener('submit', function(e) {
      e.preventDefault();

      const id = document.getElementById('idEmpenho').value;
      const justificativa = document.getElementById('justificativaExclusao').value;
      const form = document.getElementById('formExcluirEmpenho');

      if (!id || !justificativa) {
        alert('ID e justificativa são obrigatórios.');
        return;
      }

      const formData = new FormData(form);

      fetch(form.action, {
          method: 'POST',
          body: formData
        })
        .then(res => res.text())
        .then(response => {
          if (response.includes('<!DOCTYPE')) {
            // Está retornando HTML em vez de texto — provavelmente redirecionado para login.php
            alert('Erro: resposta inesperada do servidor.');
            console.log(response); // loga o HTML inteiro
            return;
          }

          alert(response); // Sucesso esperado: "Empenho excluído com sucesso"
          fecharModal();
          location.reload(); // Recarrega a lista
        })
        .catch(err => {
          console.error(err);
          alert('Erro ao excluir empenho.');
        });
    });
  </script>
</body>
