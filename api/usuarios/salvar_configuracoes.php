<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['usuario_id'])) exit('Acesso negado');

$id = $_SESSION['usuario_id'];

// 1. Buscar dados atuais do usuário
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$original = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Coletar novos dados do formulário
$modo_escuro = isset($_POST['modo_escuro']) ? 1 : 0;
$alertas = $_POST['alertas'] ?? [];
$alertas_json = json_encode(array_map('intval', $alertas));

// 3. Atualizar sessão do modo escuro imediatamente
$_SESSION['modo_escuro'] = $modo_escuro;

// 4. Organizar novos valores para comparar com os originais
$novos = [
  'modo_escuro' => $modo_escuro,
  'alertas_config' => $alertas_json
];

// 5. Comparar e gerar descrição do log
$detalhes = gerarLogAlteracoes($original, $novos);

// 6. Atualizar dados no banco
$stmt = $pdo->prepare("UPDATE usuarios SET modo_escuro = ?, alertas_config = ? WHERE id = ?");
$stmt->execute([$modo_escuro, $alertas_json, $id]);

// 7. Registrar log de alterações, se houver
$acao = 'editar_configuracoes: ' . $detalhes;
$stmtLog = $pdo->prepare("INSERT INTO logs (usuario_id, acao, ip, criado_em)
                          VALUES (?, ?, ?, NOW())");
$stmtLog->execute([$id, $acao, $_SERVER['REMOTE_ADDR']]);

// 8. Alterar senha (se solicitado)
if (!empty($_POST['senha_atual']) && !empty($_POST['nova_senha'])) {
  $stmt = $pdo->prepare("SELECT senha_hash FROM usuarios WHERE id = ?");
  $stmt->execute([$id]);
  $senha_hash = $stmt->fetchColumn();

  if (password_verify($_POST['senha_atual'], $senha_hash)) {
    $nova_hash = password_hash($_POST['nova_senha'], PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?")->execute([$nova_hash, $id]);

    // Log de alteração de senha
    $pdo->prepare("INSERT INTO logs (usuario_id, acao, ip, data_hora)
                   VALUES (?, 'alterou senha', ?, NOW())")
      ->execute([$id, $_SERVER['REMOTE_ADDR']]);
  } else {
    echo "Senha atual incorreta.";
    exit;
  }
}

// 9. Upload do avatar (se enviado)
if (!empty($_FILES['avatar']['name'])) {
  $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
  $novoNome = 'avatar_' . $id . '.' . $ext;
  $destino = '../../assets/avatars/' . $novoNome;

  if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destino)) {
    $pdo->prepare("UPDATE usuarios SET avatar = ? WHERE id = ?")->execute([$novoNome, $id]);
  }
}

// 10. Redirecionar de volta para a página de configurações
header('Location: ../../templates/configuracoes.php');
exit;

// Função auxiliar para comparar alterações
function gerarLogAlteracoes(array $original, array $novos): string
{
  $alteracoes = [];

  foreach ($novos as $campo => $novo_valor) {
    if (!array_key_exists($campo, $original)) continue;

    $valor_antigo = $original[$campo];

    if (is_string($valor_antigo)) $valor_antigo = trim($valor_antigo);
    if (is_string($novo_valor)) $novo_valor = trim($novo_valor);

    if (is_bool($valor_antigo)) $valor_antigo = $valor_antigo ? 'Sim' : 'Não';
    if (is_bool($novo_valor)) $novo_valor = $novo_valor ? 'Sim' : 'Não';

    if ($valor_antigo != $novo_valor) {
      $alteracoes[] = "Campo \"$campo\": de \"$valor_antigo\" para \"$novo_valor\"";
    }
  }

  return implode('; ', $alteracoes);
}
