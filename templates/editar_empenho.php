<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: login.php');
  exit;
}
require_once '../config/db.php';
$pdo = Conexao::getInstance();
include 'includes/header.php';
require_once './includes/verifica_login.php';
include './includes/modal_exclusao.php';


$id = $_GET['id'] ?? null;
$empenho = [
  'numero_empenho' => '',
  'valor_empenhado' => '',
  'data_empenho' => '',
  'data_fim_previsto' => '',
  'objeto' => '',
  'fornecedor' => '',
  'observacoes' => '',
  'contrato_id' => ''
];


if ($id) {
  $stmt = $pdo->prepare("SELECT * FROM empenhos WHERE id = ?");
  $stmt->execute([$id]);
  $empenho = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" href="../assets/css/style.css">
  <title>Editar Empenho</title>
</head>

<body class="<?= $_SESSION['modo_escuro'] ? 'dark' : '' ?>">
  <main style="padding:2rem; max-width:700px; margin:auto;">

    <h2><?= $id ? 'Editar' : 'Novo' ?> Empenho</h2>
    <form method="post" action="../api/empenhos/atualizar.php" class="form-box">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="form-row">
        <label>Contrato ID (se houver):<br><input type="number" name="contrato_id" value="<?= $empenho['contrato_id'] ?>"></label><br>
        <label>Nº Empenho:<br><input type="text" name="numero_empenho" value="<?= $empenho['numero_empenho'] ?>" required></label><br>
      </div>
      <div class="form-row">
        <label>Valor:<br><input type="text" name="valor_empenhado" value="<?= $empenho['valor_empenhado'] ?>" required></label><br>
        <label>Data do Empenho:<br><input type="date" name="data_empenho" value="<?= $empenho['data_empenho'] ?>"></label><br>
        <label>Data Fim Previsto:<br><input type="date" name="data_fim_previsto" value="<?= $empenho['data_fim_previsto'] ?>"></label><br>
      </div>
      <div class="form-row">
        <label>Objeto:<br><textarea name="objeto"><?= $empenho['objeto'] ?></textarea></label><br>
      </div>
      <div class="form-row">
        <label>Fornecedor:<br><input type="text" name="fornecedor" value="<?= $empenho['fornecedor'] ?>"></label><br>
      </div>
      <div class="form-row">
        <label>Observações:<br><textarea name="observacoes"><?= $empenho['observacoes'] ?></textarea></label><br>
      </div>

      <button type="submit" class="btn-link editar">Salvar Alterações</button>
    </form>
