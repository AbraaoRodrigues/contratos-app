<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: login.php');
  exit;
}

require_once '../config/db.php';
$pdo = Conexao::getInstance();
include 'includes/header.php';
require_once '../templates/includes/verifica_login.php';
include './includes/modal_exclusao.php';

// Inicializa variáveis
$contratoId = $_GET['contrato_id'] ?? null;
$where = 'status != "excluido"'; // garante que só mostra empenhos ativos
$params = [];

if ($contratoId) {
  $where .= " AND contrato_id = ?";
  $params[] = $contratoId;
}

$stmt = $pdo->prepare("SELECT * FROM empenhos WHERE $where ORDER BY data_empenho DESC");
$stmt->execute($params);
$empenhos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Relatórios</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="<?= $_SESSION['modo_escuro'] ? 'dark' : '' ?>">
  <main style="padding:2rem; max-width:1100px; margin:auto;">
    <h2>Empenhos <?= $contratoId ? "do Contrato #$contratoId" : '' ?></h2>

    <div class="empenho-card">
      <?php foreach ($empenhos as $emp): ?><br><br>
        =======
        <!--<div class="form-box" style="max-width: 800px; margin: auto;">-->
        <p><strong>Nº Empenho:</strong> <?= htmlspecialchars($emp['numero_empenho']) ?></p>
        <p><strong>Valor:</strong> <?= number_format($emp['valor_empenhado'], 2, ',', '.') ?></p>
        <p><strong>Data:</strong> <?= date('d/m/Y', strtotime($emp['data_empenho'])) ?></p>
        <p><strong>Fim Previsto:</strong> <?= date('d/m/Y', strtotime($emp['data_fim_previsto'])) ?></p>
        <p><strong>Fornecedor:</strong> <?= htmlspecialchars($emp['fornecedor']) ?></p>
        <p><strong>Objeto:</strong> <?= htmlspecialchars($emp['objeto']) ?></p>
        <p><strong>Observações:</strong> <?= htmlspecialchars($emp['observacoes']) ?></p>
        <div class="acoes-empenho">
          <a href="editar_empenho.php?id=<?= $emp['id'] ?>" class="btn-link editar">✏️Editar</a>
          <a href="#" onclick="abrirModalExclusao(<?= $emp['id'] ?>, '../api/empenhos/excluir.php', 'Excluir Empenho'); return false;" class="btn-link excluir">🗑️Excluir</a>
        </div>

      <?php endforeach; ?>

    </div>
