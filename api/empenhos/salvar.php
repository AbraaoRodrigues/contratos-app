<?php
session_start();
require_once '../../config/db.php';
$pdo = Conexao::getInstance();

if (!isset($_SESSION['usuario_id'])) {
  header('Location: ../../templates/login.php');
  exit;
}

// Função para converter valor com máscara (R$)
function parseValorReal($valor)
{
  return str_replace(',', '.', str_replace('.', '', $valor));
}

// Dados do formulário
$contrato_id       = $_POST['contrato_id'] !== '0' ? (int) $_POST['contrato_id'] : null;
$numero_empenho    = $_POST['numero_empenho'];
$valor_empenhado   = parseValorReal($_POST['valor_empenhado']);
$data_empenho      = $_POST['data_empenho'];
$data_fim_previsto = $_POST['data_fim_previsto'];
$objeto            = $_POST['objeto'] ?? null;
$fornecedor        = $_POST['fornecedor'] ?? null;
$observacoes       = $_POST['observacoes'] ?? null;

// Inserir no banco
$stmt = $pdo->prepare("
  INSERT INTO empenhos
    (contrato_id, numero_empenho, valor_empenhado, data_empenho, data_fim_previsto, objeto, fornecedor, observacoes)
  VALUES
    (?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
  $contrato_id,
  $numero_empenho,
  $valor_empenhado,
  $data_empenho,
  $data_fim_previsto,
  $objeto,
  $fornecedor,
  $observacoes
]);

// Registrar log
$acao = $contrato_id ? 'cadastrar_empenho_contrato' : 'cadastrar_empenho_direto';

$detalhes = "Número: $numero_empenho | Valor: R$ " . number_format($valor_empenhado, 2, ',', '.') .
  " | Data: $data_empenho | Fim Previsto: $data_fim_previsto" .
  ($contrato_id ? " | Contrato ID: $contrato_id" : "") .
  ($fornecedor ? " | Fornecedor: $fornecedor" : "") .
  ($objeto ? " | Objeto: $objeto" : "") .
  ($observacoes ? " | Obs: $observacoes" : "");

$stmtLog = $pdo->prepare("INSERT INTO logs (usuario_id, acao, ip, criado_em)
                          VALUES (?, ?, ?, NOW())");

$stmtLog->execute([
  $_SESSION['usuario_id'],
  $detalhes, // entra aqui como 'acao'
  $_SERVER['REMOTE_ADDR']
]);


header('Location: ../../templates/empenhos.php?msg=Empenho salvo com sucesso');
exit;
