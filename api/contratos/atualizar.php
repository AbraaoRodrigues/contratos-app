<?php
session_start();
require_once '../../config/db.php';


if (!isset($_SESSION['usuario_id']) || !isset($_POST['id'])) {
  header('Location: ../../templates/login.php');
  exit;
}

function parseValorReal($valor)
{
  return str_replace(',', '.', str_replace('.', '', $valor));
}

$valor = isset($_POST['valor_total']) ? parseValorReal($_POST['valor_total']) : 0.00;

$stmt = $pdo->prepare("SELECT * FROM contratos WHERE id = ?");
$stmt->execute([$id]);
$contrato_antigo = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("UPDATE contratos SET numero = ?, processo = ?, fornecedor = ?, data_inicio = ?, data_fim = ?, valor_total = ?, local_arquivo = ?, observacoes = ?, objeto = ?, data_assinatura = ?, prorrogavel = ?, responsavel = ? WHERE id = ?");
$stmt->execute([
  $_POST['numero'],
  $_POST['processo'],
  $_POST['fornecedor'],
  $_POST['data_inicio'],
  $_POST['data_fim'],
  $valor,
  $_POST['local_arquivo'],
  $_POST['observacoes'] ?? null,
  $_POST['objeto'] ?? null,
  $_POST['data_assinatura'],
  $_POST['prorrogavel'],
  $_POST['responsavel'],
  $_POST['id']
]);

function gerarLogAlteracoes(array $original, array $novos): string
{
  $alteracoes = [];

  foreach ($novos as $campo => $novo_valor) {
    if (!array_key_exists($campo, $original)) {
      continue; // ignora campos que não existiam antes
    }

    $valor_antigo = $original[$campo];

    // Normaliza valores para comparação (remove espaços extras)
    if (is_string($valor_antigo)) $valor_antigo = trim($valor_antigo);
    if (is_string($novo_valor)) $novo_valor = trim($novo_valor);

    // Converte valores monetários para comparação
    if (preg_match('/^\\d{1,3}(\\.\\d{3})*,\\d{2}$/', $novo_valor)) {
      $valor_antigo = number_format((float)$valor_antigo, 2, ',', '.');
      $novo_valor = $novo_valor;
    }

    if ($valor_antigo != $novo_valor) {
      $alteracoes[] = "Campo \"$campo\": de \"{$valor_antigo}\" para \"{$novo_valor}\"";
    }
  }

  return implode('; ', $alteracoes);
}

$detalhes = gerarLogAlteracoes($contrato_antigo, $_POST);

if (!empty($detalhes)) {
  $stmtLog = $pdo->prepare("INSERT INTO logs (usuario_id, acao, detalhes, data_hora)
                            VALUES (?, 'editar_contrato', ?, NOW())");
  $stmtLog->execute([$_SESSION['usuario_id'], $detalhes]);
}

header('Location: ../../templates/contratos.php?msg=Contrato atualizado com sucesso');
