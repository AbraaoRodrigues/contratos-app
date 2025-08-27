<?php
// Removido session_start() daqui
if (!isset($_SESSION['usuario_id'])) {
  header('Location: ../templates/login.php');
  exit;
}


require_once __DIR__ . '/../../config/db.php';
$modoEscuro = $_SESSION['modo_escuro'] ?? false;
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
$avatar = 'default-avatar.png';


$stmt = $pdo->prepare("SELECT avatar FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
if ($usuario && !empty($usuario['avatar'])) {
  $avatar = $usuario['avatar'];
}
?>


<header class="topo">

  <nav class="menu">
    <a href="/contratos-app/index.php">Dashboard</a>
    <a href="/contratos-app/templates/contratos.php">Contratos</a>
    <a href="/contratos-app/templates/empenhos.php">Empenhos</a>
    <a href="/contratos-app/templates/relatorios.php">Relatórios</a>
    <?php if ($_SESSION['nivel_acesso'] ?? '' === 'admin') : ?>
      <a href="/templates/usuarios.php">Usuários</a>
    <?php endif; ?>
    <a href="/contratos-app/templates/configuracoes.php">Configurações</a>
    <a href="/api/auth/logout.php">Sair</a>
  </nav>
  <div class="usuario-logado">
    <span><?= htmlspecialchars($nomeUsuario) ?></span>
    <a href="/contratos-app/templates/configuracoes.php">
      <img src="/contratos-app/assets/avatars/<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="avatar">
    </a>
  </div>
</header>
