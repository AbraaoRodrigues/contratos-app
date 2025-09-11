<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: /templates/login.php');
  exit;
}
require_once '../../config/db.php';
$pdo = Conexao::getInstance();

$listaId = (int)($_GET['id'] ?? 0);
if (!$listaId) {
  die("ID da lista não informado.");
}
$modoEscuro = $_SESSION['modo_escuro'] ?? false;
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <title>Histórico da Lista</title>
  <link rel="stylesheet" href="assets/styles.css">
</head>

<body class="<?= $modoEscuro ? 'dark' : '' ?>">
  <?php include 'includes/header.php'; ?>

  <main class="container">
    <h2>Histórico da Lista #<?php echo $listaId; ?></h2>
    <table id="tblHistorico">
      <thead>
        <tr>
          <th>Descrição</th>
          <th>Qtd</th>
          <th>Valor Unit.</th>
          <th>Valor Total</th>
          <th>Referências</th>
          <th>Data</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td colspan="6">Carregando...</td>
        </tr>
      </tbody>
    </table>
  </main>

  <script>
    async function carregarHistorico() {
      const params = new URLSearchParams(window.location.search);
      const id = params.get('id');
      if (!id) return;

      const res = await fetch('api/listar_logs.php?lista_id=' + id);
      const data = await res.json();

      const tbody = document.querySelector('#tblHistorico tbody');
      tbody.innerHTML = '';

      if (!data.ok || !data.logs.length) {
        tbody.innerHTML = '<tr><td colspan="6">Nenhum histórico encontrado.</td></tr>';
        return;
      }

      data.logs.forEach(log => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${log.descricao}</td>
          <td>${log.quantidade}</td>
          <td>R$ ${parseFloat(log.valor_unitario).toFixed(2).replace('.', ',')}</td>
          <td>R$ ${parseFloat(log.valor_total).toFixed(2).replace('.', ',')}</td>
          <td>${log.referencias || '-'}</td>
          <td>${log.criado_em}</td>
        `;
        tbody.appendChild(tr);
      });
    }

    carregarHistorico();
  </script>
</body>

</html>
