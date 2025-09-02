<?php
session_start();
require_once '../../config/db.php';
$pdo = Conexao::getInstance();

require_once '../../templates/includes/verifica_login.php';

if ($_SESSION['nivel_acesso'] !== 'admin') {
  exit('Acesso restrito.');
}

$id = $_POST['id'] ?? null;
$nome = trim($_POST['nome']);
$email = trim($_POST['email']);
$nivel = $_POST['nivel_acesso'];
$senha = $_POST['senha'] ?? null;

if (!$nome || !$email || !$nivel) {
  exit('Dados incompletos.');
}

if ($id) {
  // Atualizar usuário existente
  $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
  $stmt->execute([$id]);
  $original = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$original) exit('Usuário não encontrado.');

  $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, nivel_acesso = ? WHERE id = ?");
  $stmt->execute([$nome, $email, $nivel, $id]);

  // Registrar log de alterações
  $detalhes = gerarLogAlteracoes($original, ['nome' => $nome, 'email' => $email, 'nivel_acesso' => $nivel]);
  if (!empty($detalhes)) {
    $acao = "editar_usuario: $detalhes";
    $pdo->prepare("INSERT INTO logs (usuario_id, acao, ip) VALUES (?, ?, ?)")
      ->execute([$_SESSION['usuario_id'], $acao, $_SERVER['REMOTE_ADDR']]);
  }
} else {
  // Criar novo usuário
  if (!$senha) exit('Senha obrigatória.');

  $hash = password_hash($senha, PASSWORD_DEFAULT);
  $status = 'ativo';

  $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, nivel_acesso, status)
                         VALUES (?, ?, ?, ?, ?)");
  $stmt->execute([$nome, $email, $hash, $nivel, $status]);

  $pdo->prepare("INSERT INTO logs (usuario_id, acao, ip) VALUES (?, ?, ?)")
    ->execute([$_SESSION['usuario_id'], 'criou_usuario', $_SERVER['REMOTE_ADDR']]);
}

header('Location: ../../templates/lista_usuarios.php');
exit;


// 🔧 Função auxiliar
function gerarLogAlteracoes(array $original, array $novos): string
{
  $alteracoes = [];

  foreach ($novos as $campo => $novo_valor) {
    $valor_antigo = $original[$campo] ?? '';

    if (is_string($valor_antigo)) $valor_antigo = trim($valor_antigo);
    if (is_string($novo_valor)) $novo_valor = trim($novo_valor);

    if ($valor_antigo != $novo_valor) {
      $alteracoes[] = "Campo \"$campo\": de \"$valor_antigo\" para \"$novo_valor\"";
    }
  }

  return implode('; ', $alteracoes);
}
