<?php
session_start();
require_once '../config/db.php';
require_once '../templates/includes/verifica_login.php';


if ($_SESSION['nivel_acesso'] !== 'admin') {
  exit('Acesso restrito.');
}


// Verifica se está editando ou criando novo
$usuario = null;
if (isset($_GET['id'])) {
  $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
  $stmt->execute([$_GET['id']]);
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>


<?php include '../templates/includes/header.php'; ?>


<div class="container">
  <h2><?= $usuario ? 'Editar Usuário' : 'Cadastrar Novo Usuário' ?></h2>


  <form action="../api/usuarios/salvar_usuario.php" method="post">
    <?php if ($usuario): ?>
      <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
    <?php endif; ?>


    <label>Nome:
      <input type="text" name="nome" required value="<?= $usuario['nome'] ?? '' ?>">
    </label>


    <label>Email:
      <input type="email" name="email" required value="<?= $usuario['email'] ?? '' ?>">
    </label>


    <?php if (!$usuario): ?>
      <label>Senha:
        <input type="password" name="senha" required>
      </label>
    <?php endif; ?>


    <label>Nível de Acesso:
      <select name="nivel_acesso" required>
        <option value="user" <?= ($usuario['nivel_acesso'] ?? '') === 'user' ? 'selected' : '' ?>>Usuário</option>
        <option value="admin" <?= ($usuario['nivel_acesso'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador</option>
      </select>
    </label>


    <button type="submit">Salvar</button>
  </form>
</div>


<?php include '../templates/includes/footer.php'; ?>
