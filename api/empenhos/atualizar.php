<?php
session_start();
require_once '../../config/db.php';
$pdo = Conexao::getInstance();

if (!isset($_SESSION['usuario_id']) || !isset($_POST['id'])) {
  header('Location: ../../templates/login.php');
  exit;
}

// Função para converter valor com máscara (R$)
/*function parseValorReal($valor)
{
  return str_replace(',', '.', str_replace('.', '', $valor));
}*/

// Variáveis principais
$id = (int)$_POST['id'];
$valor = isset($_POST['valor_empenhado']);
$arquivo_path = null;

// Verifica se empenho existe antes de atualizar
$stmt = $pdo->prepare("SELECT * FROM empenhos WHERE id = ?");
$stmt->execute([$id]);
$empenho_antigo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empenho_antigo) {
  exit("Empenho com ID $id não encontrado.");
}

// Dados do formulário
$contrato_id       = $_POST['contrato_id'] !== '0' ? (int) $_POST['contrato_id'] : null;
$numero_empenho    = $_POST['numero_empenho'];
$valor_empenhado   = $_POST['valor_empenhado'];
$data_empenho      = $_POST['data_empenho'];
$data_fim_previsto = $_POST['data_fim_previsto'];
$objeto            = $_POST['objeto'] ?? null;
$fornecedor        = $_POST['fornecedor'] ?? null;
$observacoes       = $_POST['observacoes'] ?? null;

// Inserir no banco
$stmt = $pdo->prepare("UPDATE empenhos SET contrato_id =?, numero_empenho=?, valor_empenhado=?,
data_empenho=?, data_fim_previsto=?, objeto=?, fornecedor=?, observacoes=? WHERE id = ?");

$stmt->execute([
  $contrato_id,
  $numero_empenho,
  $valor_empenhado,
  $data_empenho,
  $data_fim_previsto,
  $objeto,
  $fornecedor,
  $observacoes,
  $id
]);

// Função de log
function gerarLogAlteracoes(array $original, array $novos): string
{
  $alteracoes = [];

  foreach ($novos as $campo => $novo_valor) {
    if (!array_key_exists($campo, $original)) continue;

    $valor_antigo = trim((string)$original[$campo]);
    $novo_valor = trim((string)$novo_valor);

    if ($valor_antigo != $novo_valor) {
      $alteracoes[] = "Campo \"$campo\": de \"$valor_antigo\" para \"$novo_valor\"";
    }
  }

  return implode('; ', $alteracoes);
}

// Gera log
$detalhes = gerarLogAlteracoes($empenho_antigo, $_POST);

// Registrar log
if (!empty($detalhes)) {
  $stmtLog = $pdo->prepare("INSERT INTO logs (usuario_id, acao, ip, criado_em)
                            VALUES (?, 'editar_empenho', ?, NOW())");
  $stmtLog->execute([$_SESSION['usuario_id'], $detalhes]);
}

header('Location: ../../templates/relatorio_empenhos.php?msg=Empenho atualizado com sucesso');
exit;
