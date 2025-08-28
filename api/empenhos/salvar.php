<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['usuario_id'])) {
  header('Location: ../../templates/login.php');
  exit;
}

// Função para tratar valores em reais
function parseValorReal($valor)
{
  return str_replace(',', '.', str_replace('.', '', $valor));
}

// Dados do formulário
$contratoId = !empty($_POST['contrato_id']) ? $_POST['contrato_id'] : null;
$valor = parseValorReal($_POST['valor_empenhado']);
$dataEmpenho = $_POST['data_empenho'];
$dataFimPrevisto = $_POST['data_fim_previsto'];

// Inserir novo empenho
$stmt = $pdo->prepare("INSERT INTO empenhos (contrato_id, valor_empenhado, data_empenho, data_fim_previsto)
                       VALUES (?, ?, ?, ?)");
$stmt->execute([$contratoId, $valor, $dataEmpenho, $dataFimPrevisto]);

// Registrar log simples
$acao = "Novo empenho cadastrado - valor R$ " . number_format($valor, 2, ',', '.') .
  ", data: $dataEmpenho, fim previsto: $dataFimPrevisto" .
  ($contratoId ? ", contrato ID: $contratoId" : ", sem contrato vinculado");

$log = $pdo->prepare("INSERT INTO logs (usuario_id, acao, ip, criado_em)
                      VALUES (?, ?, ?, NOW())");
$log->execute([$_SESSION['usuario_id'], $acao, $_SERVER['REMOTE_ADDR']]);

// Redirecionar com mensagem
header('Location: ../../templates/empenhos.php?msg=Empenho salvo com sucesso');
exit;
