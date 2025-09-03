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
if (!empty($_FILES['arquivos']['name'])) {
  foreach ($_FILES['arquivos']['tmp_name'] as $index => $tmp) {
    $nomeOriginal = $_FILES['arquivos']['name'][$index];
    $tipo = $_POST['tipos'][$index] ?? 'outro';

    $extensao = pathinfo($nomeOriginal, PATHINFO_EXTENSION);
    if (strtolower($extensao) !== 'pdf') continue;

    $nomeFinal = uniqid("arquivo_") . ".pdf";
    $pasta = __DIR__ . '/../../uploads/contratos/';
    if (!is_dir($pasta)) mkdir($pasta, 0777, true);
    $destino = $pasta . $nomeFinal;

    if (move_uploaded_file($tmp, $destino)) {
      $stmt = $pdo->prepare("INSERT INTO contrato_arquivos (contrato_id, nome_arquivo, caminho_arquivo, tipo)
                             VALUES (?, ?, ?, ?)");
      $stmt->execute([
        $contratoId, // id do contrato salvo
        $nomeOriginal,
        'uploads/contratos/' . $nomeFinal,
        $tipo
      ]);
    }
  }
}

$valor = isset($_POST['valor_total']) ? parseValorReal($_POST['valor_total']) : 0.00;

$stmt = $pdo->prepare("INSERT INTO contratos (numero, processo, fornecedor, data_inicio, data_fim, valor_total, local_arquivo, observacoes,
 objeto, data_assinatura, prorrogavel, responsavel, prazo_maximo, data_ultimo_aditivo)
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
  $_POST['prazo_maximo'] ?? 10,
  $_POST['data_ultimo_aditivo'] ?? null
]);
$contratoId = $pdo->lastInsertId();

// log
$pdo->prepare("INSERT INTO logs (usuario_id, acao, ip) VALUES (?, 'cadastrou contrato', ?)")
  ->execute([$_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR']]);


header('Location: ../../templates/contratos.php?msg=Contrato salvo com sucesso');
