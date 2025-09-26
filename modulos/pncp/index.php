<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: /templates/login.php');
  exit;
}
include 'includes/header.php';
require_once '../../templates/includes/verifica_login.php';
require_once '../../config/db.php';
$pdo = Conexao::getInstance();

require_once __DIR__ . '/../../config/db_precos.php';
$pdo2 = ConexaoPrecos::getInstance();

$modoEscuro = $_SESSION['modo_escuro'] ?? false;

// Estatísticas
$totalProc = $pdo2->query("SELECT COUNT(*) FROM cache_pncp_processos")->fetchColumn();
$totalItens = $pdo2->query("SELECT COUNT(*) FROM cache_pncp_itens")->fetchColumn();
$falhasPend = $pdo2->query("SELECT COUNT(*) FROM cache_pncp_falhas WHERE status='pendente'")->fetchColumn();
$ultimo = $pdo2->query("SELECT MAX(atualizado_em) FROM cache_pncp_processos")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Dashboard PNCP</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: var(--bg);
      color: var(--fg);
    }

    .cards {
      display: flex;
      gap: 20px;
      margin: 20px;
    }

    .card {
      flex: 1;
      padding: 20px;
      border-radius: 12px;
      background: #f9f9f9;
      box-shadow: 0 2px 4px rgba(0, 0, 0, .1);
    }

    body.dark .card {
      background: #333;
      color: #fff;
    }

    .card h2 {
      margin: 0;
      font-size: 2em;
    }

    .actions {
      margin: 20px;
    }

    button {
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
    }

    .btn-primary {
      background: #007bff;
      color: #fff;
    }

    .btn-secondary {
      background: #6c757d;
      color: #fff;
    }
  </style>
</head>

<body class="<?= $modoEscuro ? 'dark' : '' ?>">
  <h1>📊 Dashboard PNCP</h1>

  <div class="cards">
    <div class="card">
      <h2><?= $totalProc ?></h2>
      <p>Processos salvos</p>
    </div>
    <div class="card">
      <h2><?= $totalItens ?></h2>
      <p>Itens salvos</p>
    </div>
    <div class="card">
      <h2><?= $falhasPend ?></h2>
      <p>Falhas pendentes</p>
    </div>
    <div class="card">
      <h2><?= $ultimo ?: '-' ?></h2>
      <p>Última atualização</p>
    </div>
  </div>

  <div class="actions">
    <form method="POST" action="admin_pncp.php">
      <label>Atualizar base (últimos X dias):
        <input type="number" name="dias" value="7" min="1" max="365">
      </label>
      <button class="btn-primary" name="acao" value="atualizar">Atualizar</button>
      <button class="btn-secondary" name="acao" value="reprocessar">Reprocessar falhas</button>
    </form>
  </div>
</body>

</html>
