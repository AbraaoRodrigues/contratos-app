<?php
if (!isset($_SESSION['usuario_id'])) {
  header('Location: ../templates/login.php');
  exit;
}

require_once __DIR__ . '/../../config/db.php';

$modoEscuro = $_SESSION['modo_escuro'] ?? false;
$nomeUsuario = $_SESSION['nome'] ?? 'Usuário';
$nivelAcesso = $_SESSION['nivel_acesso'] ?? null;
$avatar = 'default-avatar.png';

// Carrega avatar do usuário
$stmt = $pdo->prepare("SELECT avatar FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
if ($usuario && !empty($usuario['avatar'])) {
  $avatar = $usuario['avatar'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Contratos - Prefeitura de Agudos</title>

  <!-- Estilo padrão -->
  <link rel="stylesheet" href="/contratos-app/assets/css/style.css">

</head>

<body>
  <header class="topo">

    <nav class="menu">
      <ul class="menu-principal">
        <li><a href="/contratos-app/index.php">Dashboard</a></li>
        <li><a href="/contratos-app/templates/contratos.php">Contratos</a></li>
        <li><a href="/contratos-app/templates/empenhos.php">Empenhos</a></li>
        <li><a href="/contratos-app/templates/relatorios.php">Relatórios</a></li>

        <?php if (!empty($_SESSION['nivel_acesso']) && $_SESSION['nivel_acesso'] === 'admin'): ?>
          <li class="dropdown">
            <a href="#">Usuários ▾</a>
            <ul class="dropdown-menu">
              <li><a href="/contratos-app/templates/usuarios.php">Cadastrar</a></li>
              <li><a href="/contratos-app/templates/lista_usuarios.php">Listar</a></li>
            </ul>
          </li>
        <?php endif; ?>

        <li><a href="/contratos-app/api/auth/logout.php">Sair</a></li>
      </ul>
    </nav>

    <div class="usuario-logado">
      <span><?= htmlspecialchars($nomeUsuario) ?></span>
      <a href="/contratos-app/templates/configuracoes.php">
        <img src="/contratos-app/assets/avatars/<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="avatar">
      </a>
    </div>
  </header>
