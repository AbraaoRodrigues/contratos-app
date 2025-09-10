<?php
session_start();
require_once '../config/db.php';
$pdo = Conexao::getInstance();
require_once './includes/verifica_login.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  exit("ID inválido.");
}

$id = (int)$_GET['id'];

// Busca contrato
$stmt = $pdo->prepare("SELECT * FROM contratos WHERE id = ?");
$stmt->execute([$id]);
$contrato = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contrato) {
  exit("Contrato não encontrado.");
}

// Busca arquivos vinculados
$stmtArquivos = $pdo->prepare("SELECT * FROM contrato_arquivos WHERE contrato_id = ? AND status='ativo'");
$stmtArquivos->execute([$id]);
$arquivos = $stmtArquivos->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['confirmar_exclusao'])) {
  $idArq = (int)$_POST['arquivo_id'];
  $justificativa = trim($_POST['justificativa']);

  // Buscar caminho
  $stmt = $pdo->prepare("SELECT caminho_arquivo FROM contrato_arquivos WHERE id = ? AND contrato_id = ?");
  $stmt->execute([$idArq, $id]);
  $arquivo = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($arquivo) {
    // Atualizar justificativa
    $pdo->prepare("UPDATE contrato_arquivos SET justificativa_exclusao = ? WHERE id = ?")->execute([$justificativa, $idArq]);

    // Excluir fisicamente
    $caminho = __DIR__ . '/../' . $arquivo['caminho_arquivo'];
    if (file_exists($caminho)) unlink($caminho);

    // Remover do banco
    $pdo->prepare("DELETE FROM contrato_arquivos WHERE id = ?")->execute([$idArq]);

    // Redireciona para evitar reenvio
    header("Location: detalhes_contrato.php?id=$id");
    exit;
  }
}

?>

<?php include './includes/header.php'; ?>

<head>
  <meta charset="UTF-8">
  <title>Detalhes Contratos</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="<?= $_SESSION['modo_escuro'] ? 'dark' : '' ?>">
  <div class="container">
    <h2>📄 Detalhes do Contrato</h2>
    <div class="empenho-card">
      <!--<div class="form-box" style="max-width: 800px; margin: auto;">-->
      <p><strong>Número:</strong> <?= htmlspecialchars($contrato['numero']) ?></p>
      <p><strong>Processo:</strong> <?= htmlspecialchars($contrato['processo']) ?></p>
      <p><strong>Fornecedor:</strong> <?= htmlspecialchars($contrato['fornecedor']) ?></p>
      <p><strong>Objeto:</strong> <?= nl2br(htmlspecialchars($contrato['objeto'])) ?></p>
      <p><strong>Valor Total:</strong> R$ <?= number_format($contrato['valor_total'], 2, ',', '.') ?></p>
      <p><strong>Início:</strong> <?= date('d/m/Y', strtotime($contrato['data_inicio'])) ?></p>
      <p><strong>Fim:</strong> <?= date('d/m/Y', strtotime($contrato['data_fim'])) ?></p>
      <p><strong>Local do Arquivo:</strong> <?= htmlspecialchars($contrato['local_arquivo']) ?></p>
      <p><strong>Prorrogável:</strong> <?= $contrato['prorrogavel'] === 'Sim' ? 'Sim' : 'Não' ?></p>

      <?php if ($contrato['prorrogavel'] === 'Sim'): ?>
        <p><strong>Prazo Máximo:</strong> <?= htmlspecialchars($contrato['prorrogavel_max_anos']) ?> anos</p>
        <p><strong>Data do Aditivo (se houver):</strong>
          <?php
          if (!empty($contrato['data_ultimo_aditivo']) && $contrato['data_ultimo_aditivo'] !== '0000-00-00') {
            echo date('d/m/Y', strtotime($contrato['data_ultimo_aditivo']));
          } else {
            echo '—';
          }
          ?>
        </p>

      <?php endif; ?>

      <p><strong>Responsável:</strong> <?= nl2br(htmlspecialchars($contrato['responsavel'] ?? '')) ?></p>
      <p><strong>Observações:</strong><br><?= nl2br(htmlspecialchars($contrato['observacoes'])) ?></p>

      <hr>
      <h3>📎 Arquivos Anexados</h3>
      <?php if (count($arquivos) > 0): ?>
        <ul>
          <?php foreach ($arquivos as $arquivo): ?>
            <li>
              <?= htmlspecialchars($arquivo['nome_arquivo']) ?> —
              <a href="/<?= htmlspecialchars($arquivo['caminho_arquivo']) ?>" target="_blank" class="btn-link editar">👁 Visualizar</a>
              <button type="button" onclick="abrirModalExclusao(<?= $arq['id'] ?>)" class="btn-link excluir">🗑Excluir</button>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p>Nenhum arquivo anexado.</p>
      <?php endif; ?>

      <a href="relatorios.php" class="btn-link voltar">← Voltar</a>
    </div>
  </div>
  <!-- Modal de exclusão -->
  <div id="modal-exclusao" style="display:none; position:fixed; top:0; left:0; background:#00000088; width:100%; height:100%; justify-content:center; align-items:center;">
    <div style="background:white; padding:2rem; border-radius:8px; max-width:500px; width:90%;">
      <h3>Justificativa para Exclusão</h3>
      <form method="post" action="">
        <input type="hidden" name="arquivo_id" id="arquivo_id_modal">
        <textarea name="justificativa" required rows="4" style="width:100%; margin-bottom:1rem;"></textarea>
        <div style="text-align:right;">
          <button type="submit" name="confirmar_exclusao" style="background:red; color:white;">Excluir</button>
          <button type="button" onclick="document.getElementById('modal-exclusao').style.display='none'">Cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function abrirModalExclusao(id) {
      document.getElementById('arquivo_id_modal').value = id;
      document.getElementById('modal-exclusao').style.display = 'flex';
    }
  </script>


  <?php include './includes/footer.php'; ?>
</body>
