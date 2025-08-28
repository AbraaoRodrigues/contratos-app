<?php
// Removido session_start() daqui
if (!isset($_SESSION['usuario_id'])) {
  header('Location: ../templates/login.php');
  exit;
}

require_once __DIR__ . '/../../config/db.php';
$modoEscuro = $_SESSION['modo_escuro'] ?? false;
$nomeUsuario = $_SESSION['nome'] ?? 'Usuário';
$nivelAcesso = $_SESSION['nivel_acesso'] ?? null;
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
    <?php if (!empty($_SESSION['nivel_acesso']) && $_SESSION['nivel_acesso'] === 'admin'): ?>
      <li class="dropdown">
        <a href="#">Usuários ▾</a>
        <ul class="dropdown-menu">
          <li><a href="/contratos-app/templates/usuarios.php">Cadastrar</a></li>
          <li><a href="/contratos-app/templates/lista_usuarios.php">Listar</a></li>
        </ul>
      </li>
    <?php endif; ?>
    <a href="/contratos-app/api/auth/logout.php">Sair</a>
  </nav>
  <div class="usuario-logado">
    <span><?= htmlspecialchars($nomeUsuario) ?></span>
    <a href="/contratos-app/templates/configuracoes.php">
      <img src="/contratos-app/assets/avatars/<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="avatar">
    </a>
  </div>
</header>
