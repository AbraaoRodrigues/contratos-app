<?php
session_start();
require_once '../../config/db.php';
$pdo = Conexao::getInstance();

if (!isset($_SESSION['usuario_id']) || !isset($_POST['id'])) {
  header('Location: ../../templates/login.php');
  exit;
}

function parseValorReal($valor)
{
  return str_replace(',', '.', str_replace('.', '', $valor));
}

// Variáveis principais
$id = (int)$_POST['id'];
$valor = isset($_POST['valor_total']) ? parseValorReal($_POST['valor_total']) : 0.00;
$arquivo_path = null;

// Verifica se contrato existe antes de atualizar
$stmt = $pdo->prepare("SELECT * FROM contratos WHERE id = ?");
$stmt->execute([$id]);
$contrato_antigo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contrato_antigo) {
  exit("Contrato com ID $id não encontrado.");
}

// Upload de novos arquivos enviados
if (!empty($_FILES['arquivos']['name'][0])) {
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
      $stmt = $pdo->prepare("INSERT INTO contrato_arquivos (contrato_id, nome_arquivo, caminho_arquivo, tipo) VALUES (?, ?, ?, ?)");
      $stmt->execute([
        $id, // já está definido no começo do script como $_POST['id']
        $nomeOriginal,
        'uploads/contratos/' . $nomeFinal,
        $tipo
      ]);
    }
  }
}

// Coleta os campos do formulário
$numero = $_POST['numero'] ?? '';
$processo = $_POST['processo'] ?? '';
$fornecedor = $_POST['fornecedor'] ?? '';
$data_inicio = $_POST['data_inicio'] ?? '';
$data_fim = $_POST['data_fim'] ?? '';
$data_assinatura = $_POST['data_assinatura'] ?? '';
$local_arquivo = $_POST['local_arquivo'] ?? '';
$observacoes = $_POST['observacoes'] ?? '';
$objeto = $_POST['objeto'] ?? '';
$prorrogavel = $_POST['prorrogavel'] ?? 'nao';
$responsavel = $_POST['responsavel'] ?? '';
$prazo_maximo = $_POST['prazo_maximo'] ?? 10;
$dt_ultimo_adt = $_POST['data_ultimo_aditivo'] ?? null;
$contratoId = $_POST['id'];

// Atualiza no banco
$stmt = $pdo->prepare("UPDATE contratos SET
  numero = ?,
  processo = ?,
  fornecedor = ?,
  data_inicio = ?,
  data_fim = ?,
  valor_total = ?,
  local_arquivo = ?,
  observacoes = ?,
  objeto = ?,
  data_assinatura = ?,
  prorrogavel = ?,
  responsavel = ?,
  prorrogavel_max_anos = ?,
  data_ultimo_aditivo = ?
WHERE id = ?");
$stmt->execute([
  $numero,
  $processo,
  $fornecedor,
  $data_inicio,
  $data_fim,
  $valor,
  $local_arquivo,
  $observacoes,
  $objeto,
  $data_assinatura,
  $prorrogavel,
  $responsavel,
  $prazo_maximo,
  $dt_ultimo_adt,
  $id
]);

// Função de log
function gerarLogAlteracoes(array $original, array $novos): string
{
  $alteracoes = [];

  foreach ($novos as $campo => $novo_valor) {
    if (!array_key_exists($campo, $original)) continue;

    $valor_antigo = trim((string) $original[$campo]);
    $novo_valor = trim((string) $novo_valor);

    if (preg_match('/^\d{1,3}(\.\d{3})*,\d{2}$/', $novo_valor)) {
      $valor_antigo = number_format((float)$valor_antigo, 2, ',', '.');
    }

    if ($valor_antigo != $novo_valor) {
      $alteracoes[] = "Campo \"$campo\": de \"{$valor_antigo}\" para \"{$novo_valor}\"";
    }
  }

  return implode('; ', $alteracoes);
}

// Gera log
$detalhes = gerarLogAlteracoes($contrato_antigo, $_POST);

if (!empty($detalhes)) {
  $stmtLog = $pdo->prepare("INSERT INTO logs (usuario_id, acao, ip, criado_em)
                            VALUES (?, 'editar_contrato', ?, NOW())");
  $stmtLog->execute([$_SESSION['usuario_id'], $detalhes]);
}

header('Location: ../../templates/contratos.php?msg=Contrato atualizado com sucesso');
exit;
