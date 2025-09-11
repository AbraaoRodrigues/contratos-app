<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: /templates/login.php');
  exit;
}
require_once '../../config/db_precos.php';
$pdoPrecos = ConexaoPrecos::getInstance();
$modoEscuro = $_SESSION['modo_escuro'] ?? false;

// Busca listas salvas
$stmt = $pdoPrecos->query("SELECT id, nome, criado_em FROM listas ORDER BY criado_em DESC");
$listas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <title>Listas salvas</title>
  <link rel="stylesheet" href="assets/styles.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

</head>

<body class="<?= $modoEscuro ? 'dark' : '' ?>">
  <?php include 'includes/header.php'; ?>

  <main class="container">
    <h2>Listas salvas</h2>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Nome</th>
          <th>Criada em</th>
          <th>Ações</th>
          <th>Exportar</th> <!-- nova coluna -->
        </tr>
      </thead>
      <tbody>
        <?php foreach ($listas as $l): ?>
          <tr>
            <td><?= $l['id'] ?></td>
            <td><?= htmlspecialchars($l['nome']) ?></td>
            <td><?= $l['criado_em'] ?></td>
            <td>
              <a class="btn" href="historico.php?id=<?= $l['id'] ?>">Ver histórico</a>
            </td>
            <td>
              <a href="api/exportar.php?formato=pdf&lista_id=<?= $l['id'] ?>" target="_blank" title="Exportar PDF">
                <i class="fas fa-file-pdf fa-lg" style="color:#e74c3c"></i>
              </a>
              <a href="api/exportar.php?formato=word&lista_id=<?= $l['id'] ?>" target="_blank" title="Exportar Word">
                <i class="fas fa-file-word fa-lg" style="color:#2a5699"></i>
              </a>
              <a href="api/exportar.php?formato=excel&lista_id=<?= $l['id'] ?>" target="_blank" title="Exportar Excel">
                <i class="fas fa-file-excel fa-lg" style="color:#217346"></i>
              </a>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </main>
</body>

</html>
