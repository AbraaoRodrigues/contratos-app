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

$id = $_GET['id'] ?? null;
if (!$id) {
  echo "ID inválido.";
  exit;
}


$stmt = $pdo->prepare("SELECT * FROM contratos WHERE id = ?");
$stmt->execute([$id]);
$contrato = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$contrato) {
  echo "Contrato não encontrado.";
  exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" href="../assets/css/style.css">
  <title>Editar Contrato</title>
</head>

<body class="<?= $_SESSION['modo_escuro'] ? 'dark' : '' ?>">
  <main style="padding:2rem; max-width:700px; margin:auto;">

    <h2>Editar Contrato</h2>
    <form action="../api/contratos/atualizar.php" method="post" class="form-box" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= $contrato['id'] ?>">

      <div class="form-row">
        <label>Número:<br><input type="text" name="numero" value="<?= $contrato['numero'] ?>" required></label><br><br>
        <label>Processo:<br><input type="text" name="processo" value="<?= $contrato['processo'] ?>" required></label><br><br>
        <label>Fornecedor:<br><input type="text" name="fornecedor" value="<?= $contrato['fornecedor'] ?>" required></label><br><br>
        <label>Valor Total:<br>
          <input type="text" name="valor_total" id="valor_total" value="<?= number_format($contrato['valor_total'], 2, ',', '.') ?>" required>
        </label><br><br>
        <label>Data Início:<br><input type="date" name="data_inicio" value="<?= $contrato['data_inicio'] ?>" required></label><br><br>
        <label>Data Fim:<br><input type="date" name="data_fim" value="<?= $contrato['data_fim'] ?>" required></label><br><br>
        <label>Data Assinatura:<br><input type="date" name="data_assinatura" value="<?= $contrato['data_assinatura'] ?>" required></label><br><br>
        <label>Local Arquivo:<br>
          <select name="local_arquivo">
            <option value="Digital" <?= $contrato['local_arquivo'] === 'Digital' ? 'selected' : '' ?>>Digital</option>
            <option value="Físico" <?= $contrato['local_arquivo'] === 'Físico' ? 'selected' : '' ?>>Físico</option>
          </select>
        </label><br>
      </div>
      <div class="form-row">
        <label>Observações:<br>
          <textarea name="observacoes"><?= $contrato['observacoes'] ?></textarea>
      </div></label>
      <div class="form-row">
        <label>Objeto:<br>
          <textarea name="objeto"><?= $contrato['objeto'] ?></textarea>
      </div></label>
      <?php if (!empty($contrato['arquivo_pdf'])): ?>
        <p>Contrato atual:
          <a href="/<?= htmlspecialchars($contrato['arquivo_pdf']) ?>" target="_blank">📎 Ver contrato</a>
        </p>
      <?php endif; ?>

      <label>Substituir contrato (PDF):
        <input type="file" name="contrato_pdf" accept="application/pdf">
      </label>
      <button type="submit">Salvar Alterações</button>
    </form>
  </main>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const input = document.getElementById('valor_total');

      input.addEventListener('input', function() {
        let v = input.value.replace(/[\\.]/g, '').replace(',', '');
        v = (parseFloat(v) / 100).toFixed(2);
        v = v.replace('.', ',');
        v = v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        input.value = v;
      });

      // Evita erro se o valor estiver vazio
      if (input.value && !input.value.includes(',')) {
        let raw = parseFloat(input.value).toFixed(2);
        raw = raw.replace('.', ',');
        input.value = raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      }
    });
  </script>

</body>

</html>
