<?php
$original = $stmt->fetch(PDO::FETCH_ASSOC);


$novos = [
  'nome' => $nome,
  'email' => $email,
  'nivel_acesso' => $nivel
];


$detalhes = gerarLogAlteracoes($original, $novos);


$stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, nivel_acesso = ? WHERE id = ?");
$stmt->execute([$nome, $email, $nivel, $id]);


if (!empty($detalhes)) {
  $stmtLog = $pdo->prepare("INSERT INTO logs (usuario_id, acao, detalhes, data_hora)
VALUES (?, 'editar_usuario', ?, NOW())");
  $stmtLog->execute([$_SESSION['usuario_id'], $detalhes]);
} else {
  // Criação de novo usuário
  $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
  $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, nivel_acesso)
VALUES (?, ?, ?, ?)");
  $stmt->execute([$nome, $email, $senha, $nivel]);


  $novo_id = $pdo->lastInsertId();
  $stmtLog = $pdo->prepare("INSERT INTO logs (usuario_id, acao, detalhes, data_hora)
VALUES (?, 'cadastrar_usuario', ?, NOW())");
  $stmtLog->execute([
    $_SESSION['usuario_id'],
    "Criou usuário $nome ($email), nível $nivel, ID $novo_id"
  ]);
}


header('Location: ../../templates/usuarios.php');
exit;




function gerarLogAlteracoes(array $original, array $novos): string
{
  $alteracoes = [];


  foreach ($novos as $campo => $novo_valor) {
    if (!array_key_exists($campo, $original)) continue;


    $valor_antigo = $original[$campo];


    if (is_string($valor_antigo)) $valor_antigo = trim($valor_antigo);
    if (is_string($novo_valor)) $novo_valor = trim($novo_valor);


    if ($valor_antigo != $novo_valor) {
      $alteracoes[] = "Campo \"$campo\": de \"$valor_antigo\" para \"$novo_valor\"";
    }
  }


  return implode('; ', $alteracoes);
}
