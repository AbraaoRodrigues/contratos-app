<?php
session_start();
require_once '../config/db.php';
$pdo = Conexao::getInstance();
require_once './includes/verifica_login.php';

if ($_SESSION['nivel_acesso'] !== 'admin') {
  exit('Acesso restrito.');
}

$stmt = $pdo->query("SELECT * FROM usuarios ORDER BY nome");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include './includes/header.php'; ?>

<head>
  <link rel="stylesheet" href="/contratos-app/assets/css/style.css">
</head>

<body class="<?= $_SESSION['modo_escuro'] ? 'dark' : '' ?>">
  <div class="container">
    <h2>Usuários Cadastrados</h2>

    <table class="tabela-usuarios">
      <thead>
        <tr>
          <th>Nome</th>
          <th>Email</th>
          <th>Nível</th>
          <th>Status</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($usuarios as $u): ?>
          <tr>
            <td><?= htmlspecialchars($u['nome']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= $u['nivel_acesso'] === 'admin' ? 'Administrador' : 'Usuário' ?></td>
            <td class="<?= $u['status'] === 'ativo' ? 'status-ativo' : 'status-inativo' ?>">
              <?= ucfirst($u['status']) ?>
            </td>
            <td>
              <a href="usuarios.php?id=<?= $u['id'] ?>" class="btn-editar">Editar</a>
              <?php if ($u['id'] != $_SESSION['usuario_id']): ?>
                |
                <?php if ($u['status'] === 'ativo'): ?>
                  <a href="../api/usuarios/desativar.php?id=<?= $u['id'] ?>" class="btn-desativar">Desativar</a>
                <?php else: ?>
                  <a href="../api/usuarios/ativar.php?id=<?= $u['id'] ?>" class="btn-ativar">Ativar</a>
                <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>


  <?php include './includes/footer.php'; ?>
</body>
