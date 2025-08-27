<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: login.php');
  exit;
}
require_once '../config/db.php';
include 'includes/header.php';


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
    <form action="../api/contratos/atualizar.php" method="post">
      <input type="hidden" name="id" value="<?= $contrato['id'] ?>">


      <label>Número:<br><input type="text" name="numero" value="<?= $contrato['numero'] ?>" required></label><br><br>
      <label>Processo:<br><input type="text" name="processo" value="<?= $contrato['processo'] ?>" required></label><br><br>
      <label>Órgão:<br><input type="text" name="orgao" value="<?= $contrato['orgao'] ?>" required></label><br><br>
      <label>Valor Total:<br><input type="number" step="0.01" name="valor_total" value="<?= $contrato['valor_total'] ?>" required></label><br><br>
      <label>Data Início:<br><input type="date" name="data_inicio" value="<?= $contrato['data_inicio'] ?>" required></label><br><br>
      <label>Data Fim:<br><input type="date" name="data_fim" value="<?= $contrato['data_fim'] ?>" required></label><br><br>
      <label>Local Arquivo:<br>
        <select name="local_arquivo">
          <option value="1Doc" <?= $contrato['local_arquivo'] === '1Doc' ? 'selected' : '' ?>>1Doc</option>
          <option value="Papel" <?= $contrato['local_arquivo'] === 'Papel' ? 'selected' : '' ?>>Papel</option>
        </select>
      </label><br><br>
      <label>Observações:<br>
        <textarea name="observacoes"><?= $contrato['observacoes'] ?></textarea>
      </label><br><br>


      <button type="submit">Salvar Alterações</button>
    </form>
  </main>
</body>

</html>
