<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: login.php');
  exit;
}
require_once '../config/db.php';
include '../templates/includes/header.php';
require_once '../templates/includes/verifica_login.php';

$id = $_SESSION['usuario_id'];
$stmt = $pdo->prepare("SELECT alertas_config, modo_escuro FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);


$alertas = json_decode($user['alertas_config'] ?? '[]', true);
$modo_escuro = $user['modo_escuro'] ?? false;
$intervalos = [120, 90, 75, 60, 45, 30, 20, 10, 5];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Configurações</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="<?= $modo_escuro ? 'dark' : '' ?>">
  <main style="padding:2rem; max-width:600px; margin:auto">
    <form action="../api/usuarios/salvar_configuracoes.php" method="post" enctype="multipart/form-data">
      <h1>Configurações do Usuário</h1>
      <h2>Modo Escuro</h2>
      <label><input type="checkbox" name="modo_escuro" <?= $modo_escuro ? 'checked' : '' ?>> Ativar</label>


      <h2>Alertas por e-mail</h2>
      <?php foreach ($intervalos as $dias): ?>
        <label>
          <input type="checkbox" name="alertas[]" value="<?= $dias ?>" <?= in_array($dias, $alertas ?? $intervalos) ? 'checked' : '' ?>> <?= $dias ?> dias antes
        </label><br>
      <?php endforeach; ?>


      <h2>Alterar Senha</h2>
      <input type="password" name="senha_atual" placeholder="Senha atual">
      <input type="password" name="nova_senha" placeholder="Nova senha"><br>

      <h2>Avatar</h2>
      <input type="file" name="avatar"><br><br>

      <button type="submit">Salvar Configurações</button>
    </form>
  </main>
</body>

</html>
