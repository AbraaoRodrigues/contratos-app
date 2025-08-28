<?php
session_start();

require_once '../config/db.php';
require_once '../templates/includes/verifica_login.php';

if ($_SESSION['nivel_acesso'] !== 'admin') {
  exit('Acesso restrito.');
}

// Se vier com ?id= na URL, está editando
$usuario = null;
if (isset($_GET['id'])) {
  $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
  $stmt->execute([$_GET['id']]);
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

  // Corrige possível retorno falso
  if (!$usuario) {
    $usuario = [];
  }
}
?>

<?php include '../templates/includes/header.php'; ?>

<head>
  <link rel="stylesheet" href="/contratos-app/assets/css/style.css">
</head>

<body class="<?= $_SESSION['modo_escuro'] ? 'dark' : '' ?>">
  <div class="container">
    <h2><?= $usuario ? 'Editar Usuário' : 'Cadastrar Novo Usuário' ?></h2>

    <form action="../api/usuarios/salvar_usuario.php" method="post" class="form-box" style="max-width: 500px; margin: 0 auto;">
      <label>Nome:
        <?php if ($usuario): ?>
          <input type="text" name="nome" required value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>">
        <?php endif; ?>
      </label>

      <label>Email:
        <input type="email" name="email" required value="<?= htmlspecialchars($usuario['email'] ?? '') ?>">
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
</body>
