<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: login.php');
  exit;
}
require_once '../config/db.php';
$pdo = Conexao::getInstance();
include 'includes/header.php';
require_once '../templates/includes/verifica_login.php';
include './includes/modal_exclusao.php';

$filtro = $_GET['filtro'] ?? '';
$data_de = $_GET['de'] ?? '';
$data_ate = $_GET['ate'] ?? '';


$query = "SELECT * FROM contratos WHERE status != 'excluido'";
$params = [];


if ($filtro) {
  $query .= " AND (numero LIKE ? OR fornecedor LIKE ?)";
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


    <table class="tabela-relatorio">
      <thead>
        <tr>
          <th>Número</th>
          <th>Fornecedor</th>
          <th>Início</th>
          <th>Fim</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($contratos as $c): ?>
          <?php
          $stmt = $pdo->prepare("SELECT COUNT(*) FROM empenhos WHERE contrato_id = ? AND status ='ativo'");
          $stmt->execute([$c['id']]);
          $temEmpenhos = $stmt->fetchColumn() > 0;
          ?>
          <tr>
            <td><?= htmlspecialchars($c['numero']) ?></td>
            <td><?= htmlspecialchars($c['fornecedor']) ?></td>
            <td><?= date('d/m/Y', strtotime($c['data_inicio'])) ?></td>
            <td><?= date('d/m/Y', strtotime($c['data_fim'])) ?></td>
            <td>
              <a href="editar_contrato.php?id=<?= $c['id'] ?>" class="btn-link editar">✏️Editar</a> |
              <a href="detalhes_contrato.php?id=<?= $c['id'] ?>" target="_blank" class="btn-link detalhes">🔍Detalhes</a> |

              <?php if ($temEmpenhos): ?>
                <a href="visualizar_empenhos.php?contrato_id=<?= $c['id'] ?>" class="btn-link empenhos">📄Empenhos</a>
              <?php else: ?>
                <span style="color: #ccc;" title="Nenhum empenho vinculado">📄Empenhos</span>
              <?php endif; ?>
              |
              <a href="#" onclick="abrirModalExclusao(<?= $c['id'] ?>, '../api/contratos/excluir.php', 'Excluir Contrato'); return false;" class="btn-link excluir">🗑️Excluir</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

  </main>
  <?php include 'includes/footer.php'; ?>
</body>

</html>
