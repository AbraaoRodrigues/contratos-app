<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: login.php');
  exit;
}
require_once '../config/db.php';
$pdo = Conexao::getInstance();

include 'includes/header.php';
require_once '../templates/includes/verifica_login.php';
include './includes/modal_exclusao.php';

// Filtros
$filtro = $_GET['filtro'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM contratos WHERE numero LIKE ? OR fornecedor LIKE ? ORDER BY data_fim ASC");
$stmt->execute(["%$filtro%", "%$filtro%"]);
$contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" href="/assets/css/style.css">
  <title>Contratos</title>
</head>

<body class="<?= $_SESSION['modo_escuro'] ? 'dark' : '' ?>">
  <main style="padding:2rem; max-width:1100px; margin:auto;">
    <h2>Contratos</h2>


    <?php if ($msg): ?><p style="color:green;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>

    <hr>
    <h3>Cadastrar novo contrato</h3>
    <form action="../api/contratos/salvar.php" method="post" class="form-box" enctype="multipart/form-data">
      <div class="form-row">
        <label>Número do Contrato:
          <input type="text" name="numero" required></label>
        <label>Número do Processo:
          <input type="text" name="processo" required></label>
      </div>

      <div class="form-row">
        <label>Fornecedor:
          <input type="text" name="fornecedor"></label>
        <label>Responsável:
          <input type="text" name="responsavel"></label>
      </div>

      <label>Objeto:
        <textarea name="objeto" rows="2"></textarea></label>

      <div class="form-row">
        <label>Data Assinatura:
          <input type="date" name="data_assinatura"></label>
        <label>Início Vigência:
          <input type="date" name="data_inicio" required></label>
        <label>Fim Vigência:
          <input type="date" name="data_fim" required></label>
      </div>

      <div class="form-row">
        <label>Valor Total:<br>
          <input type="text" name="valor_total" id="valor_total" required></label>
        <label>Prorrogável:
          <select name="prorrogavel">
            <option value="nao" <?= isset($contrato) && $contrato['prorrogavel'] === 'nao' ? 'selected' : '' ?>>Não</option>
            <option value="sim" <?= isset($contrato) && $contrato['prorrogavel'] === 'sim' ? 'selected' : '' ?>>Sim</option>
          </select>
        </label>

        <div id="campos-prorrogaveis" class="form-row" style="display: none; gap: 1rem;">
          <label>
            Prazo Máximo (em anos):
            <input type="number" name="prazo_maximo" value="<?= htmlspecialchars($contrato['prazo_maximo'] ?? '10') ?>">
          </label>

          <label>
            Data do Último Aditivo:
            <input type="date" name="data_ultimo_aditivo" value="<?= $contrato['data_ultimo_aditivo'] ?? '' ?>">
          </label>
        </div>

        <label>Local Arquivo:
          <select name="local_arquivo">
            <option value="Digital">Digital</option>
            <option value="Físico">Físico</option>
          </select></label>
      </div>

      <label>Observações:
        <textarea name="observacoes" rows="2"></textarea></label>

      <label>Anexar arquivos do contrato:</label>
      <div id="file-upload-area">
        <input type="file" id="arquivo_pdf" accept="application/pdf">
        <select id="tipo_arquivo">
          <option value="contrato">Contrato</option>
          <option value="aditivo">Aditivo</option>
          <option value="outro">Outro</option>
        </select>
        <button type="button" onclick="adicionarArquivo()">Adicionar</button>
      </div>

      <!-- Lista de arquivos adicionados -->
      <table id="lista-arquivos" style="margin-top:1rem; width:100%; border-collapse:collapse;">
        <thead>
          <tr>
            <th>Nome</th>
            <th>Tipo</th>
            <th>Ação</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>

      <button type="submit">Salvar Contrato</button>
    </form>

  </main>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const input = document.getElementById('valor_total');

      // Mascara valor
      input.addEventListener('input', function() {
        let v = input.value.replace(/\D/g, '');
        v = (parseFloat(v) / 100).toFixed(2) + '';
        v = v.replace('.', ',');
        v = v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        input.value = v;
      });

      // Inicializa campos prorrogáveis
      toggleCamposProrrogavel();

      // Evento de mudança no select
      document.querySelector('[name="prorrogavel"]').addEventListener('change', toggleCamposProrrogavel);
    });

    function toggleCamposProrrogavel() {
      const prorrogavel = document.querySelector('[name="prorrogavel"]').value;
      const extras = document.getElementById('campos-prorrogaveis');
      extras.style.display = prorrogavel.toLowerCase() === 'sim' ? 'flex' : 'none';
    }

    let arquivosAdicionados = [];

    function adicionarArquivo() {
      const input = document.getElementById('arquivo_pdf');
      const tipo = document.getElementById('tipo_arquivo').value;
      const file = input.files[0];

      if (!file) {
        alert("Selecione um arquivo PDF.");
        return;
      }

      if (file.type !== "application/pdf") {
        alert("Somente arquivos PDF são permitidos.");
        return;
      }

      arquivosAdicionados.push({
        file,
        tipo
      });
      atualizarTabela();

      // Limpa o input
      input.value = '';
    }

    function removerArquivo(index) {
      arquivosAdicionados.splice(index, 1);
      atualizarTabela();
    }

    function atualizarTabela() {
      const tbody = document.querySelector('#lista-arquivos tbody');
      tbody.innerHTML = '';

      arquivosAdicionados.forEach((item, index) => {
        const row = `<tr>
        <td>${item.file.name}</td>
        <td>${item.tipo}</td>
        <td><button type="button" onclick="removerArquivo(${index})">Remover</button></td>
      </tr>`;
        tbody.insertAdjacentHTML('beforeend', row);
      });
    }

    // Ao enviar o formulário, adicionamos os arquivos dinamicamente ao FormData
    document.querySelector('form').addEventListener('submit', function(e) {
      const formData = new FormData(this);

      arquivosAdicionados.forEach((item, index) => {
        formData.append(`arquivos[]`, item.file);
        formData.append(`tipos[]`, item.tipo);
      });

      e.preventDefault(); // evita envio padrão
      fetch(this.action, {
        method: 'POST',
        body: formData
      }).then(res => res.text()).then(data => {
        alert("Contrato salvo com sucesso!");
        window.location.href = 'contratos.php';
      }).catch(err => {
        console.error(err);
        alert("Erro ao salvar contrato.");
      });
    });
  </script>
  <?php include 'includes/footer.php'; ?>
</body>

</html>
