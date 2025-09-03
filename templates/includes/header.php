<?php
if (!isset($_SESSION['usuario_id'])) {
  header('Location: ../templates/login.php');
  exit;
}

require_once __DIR__ . '/../../config/db.php';
$pdo = Conexao::getInstance();


$modoEscuro = $_SESSION['modo_escuro'] ?? false;
$nomeUsuario = $_SESSION['nome'] ?? 'Usuário';
$nivelAcesso = $_SESSION['nivel_acesso'] ?? null;
$avatar = 'default-avatar.png';

// Carrega avatar do usuário
$stmt = $pdo->prepare("SELECT avatar FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$dadosAvatar = $stmt->fetch(PDO::FETCH_ASSOC);
if ($dadosAvatar && !empty($dadosAvatar['avatar'])) {
  $avatar = $dadosAvatar['avatar'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Contratos - Prefeitura de Agudos</title>

  <!-- Estilo padrão -->
  <link rel="stylesheet" href="/assets/css/style.css">

</head>

<body>
  <header class="topo">

    <nav class="menu">
      <ul class="menu-principal">
        <li><a href="/index.php">Dashboard</a></li>
        <li><a href="/templates/contratos.php">Contratos</a></li>
        <li><a href="/templates/empenhos.php">Empenhos</a></li>
        <li class="dropdown">
          <a href="#">Relatórios ▾</a>
          <ul class="dropdown-menu">
            <li><a href="/templates/relatorios.php">Contratos</a></li>
            <li><a href="/templates/relatorios_empenhos.php">Empenhos</a></li>
          </ul>
        </li>

        <?php if (!empty($_SESSION['nivel_acesso']) && $_SESSION['nivel_acesso'] === 'admin'): ?>
          <li class="dropdown">
            <a href="#">Usuários ▾</a>
            <ul class="dropdown-menu">
              <li><a href="/templates/usuarios.php">Cadastrar</a></li>
              <li><a href="/templates/lista_usuarios.php">Listar</a></li>
            </ul>
          </li>
        <?php endif; ?>

        <li><a href="/api/auth/logout.php">Sair</a></li>
      </ul>
    </nav>

    <div class="usuario-logado">
      <span><?= htmlspecialchars($nomeUsuario) ?></span>
      <a href="/templates/configuracoes.php">
        <img src="/assets/avatars/<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="avatar">
      </a>
    </div>
  </header>
