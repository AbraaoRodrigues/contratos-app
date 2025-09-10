<?php
session_start();
require_once '../../config/db.php';
$pdo = Conexao::getInstance();

if (!isset($_SESSION['usuario_id'])) {
  header('Location: ../../templates/login.php');
  exit;
}

function parseValorReal($valor)
{
  return str_replace(',', '.', str_replace('.', '', $valor));
}

$arquivo_path = null;

// Verifica se foi enviado um arquivo
// Salvar arquivos vinculados
if (!empty($_FILES['arquivos']['name'][0])) {
  foreach ($_FILES['arquivos']['tmp_name'] as $index => $tmp) {
    $nomeOriginal = $_FILES['arquivos']['name'][$index];
    $tipo = $_POST['tipos'][$index] ?? 'outro';

    $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
    if ($extensao !== 'pdf') continue;

    $nomeFinal = uniqid("arquivo_") . ".pdf";
    $caminhoRelativo = 'uploads/contratos/' . $nomeFinal;
    $caminhoAbsoluto = __DIR__ . '/../../' . $caminhoRelativo;

    if (!is_dir(dirname($caminhoAbsoluto))) mkdir(dirname($caminhoAbsoluto), 0777, true);

    if (move_uploaded_file($tmp, $caminhoAbsoluto)) {
      $stmt = $pdo->prepare("INSERT INTO contrato_arquivos (contrato_id, nome_arquivo, caminho_arquivo, tipo)
                             VALUES (?, ?, ?, ?)");
      $stmt->execute([
        $contratoId, // Defina este ID após salvar o contrato
        $nomeOriginal,
        $caminhoRelativo,
        $tipo
      ]);
    }
  }
}

$valor = isset($_POST['valor_total']) ? parseValorReal($_POST['valor_total']) : 0.00;

$stmt = $pdo->prepare("INSERT INTO contratos (numero, processo, fornecedor, data_inicio, data_fim, valor_total, local_arquivo, observacoes,
 objeto, data_assinatura, prorrogavel, responsavel, prorrogavel_max_anos, data_ultimo_aditivo)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

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
  $_POST['prorrogavel'] ?? 'nao',
  $_POST['responsavel'],
  $_POST['prorrogavel_max_anos'] ?? 10,
  $_POST['data_ultimo_aditivo'] ?? null
]);
$contratoId = $pdo->lastInsertId();

// log
$pdo->prepare("INSERT INTO logs (usuario_id, acao, ip, criado_em) VALUES (?, 'cadastrou contrato', ?, NOW()))")
  ->execute([$_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR']]);

header('Location: ../../templates/contratos.php?msg=Contrato salvo com sucesso');
