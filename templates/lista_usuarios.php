<?php
session_start();
require_once '../config/db.php';
require_once '../templates/includes/verifica_login.php';


if ($_SESSION['nivel_acesso'] !== 'admin') {
  exit('Acesso restrito.');
}


$stmt = $pdo->query("SELECT * FROM usuarios ORDER BY nome ASC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);


include '../templates/includes/header.php';
?>


<div class="container">
  <h2>Usuários Cadastrados</h2>


  <table>
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
          <td><?= $u['nivel_acesso'] ?></td>
          <td><?= $u['ativo'] ? 'Ativo' : 'Desativado' ?></td>
          <td>
            <a href="usuarios.php?id=<?= $u['id'] ?>" class="link-acao link-editar">Editar</a>
            <?php if ($u['id'] != $_SESSION['usuario_id']): ?>
              |
              <a href="../api/usuarios/excluir.php?id=<?= $u['id'] ?>" onclick="return confirm('Tem certeza que deseja excluir ou desativar este usuário?')" class="link-acao link-excluir">Excluir/Desativar</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>


<?php include '../templates/includes/footer.php'; ?>
