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

$id = $_GET['id'] ?? null;
if (!$id) {
  echo "ID inválido.";
  exit;
}


$stmt = $pdo->prepare("SELECT * FROM contratos WHERE id = ?");
$stmt->execute([$id]);
$contrato = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$contrato) {
  echo "Contrato não encontrado.";
  exit;
}

// Buscar arquivos já salvos para o contrato
$stmt = $pdo->prepare("SELECT * FROM contrato_arquivos WHERE contrato_id = ? AND status='ativo'");
$stmt->execute([$contrato['id']]);
$arquivosExistentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" href="../assets/css/style.css">
  <title>Editar Contrato</title>
</head>

<body class="<?= $_SESSION['modo_escuro'] ? 'dark' : '' ?>">
  <main style="padding:2rem; max-width:700px; margin:auto;">

    <h2>Editar Contrato</h2>
    <form action="../api/contratos/atualizar.php" method="post" class="form-box" enctype="multipart/form-data" id="form-contrato">
      <input type="hidden" name="id" value="<?= $contrato['id'] ?>">

      <div class="form-row">
        <label>Número:<br><input type="text" name="numero" value="<?= $contrato['numero'] ?>" required></label><br><br>
        <label>Processo:<br><input type="text" name="processo" value="<?= $contrato['processo'] ?>" required></label><br><br>
      </div>
      <div class="form-row">
        <label>Fornecedor:
          <input type="text" name="fornecedor" value="<?= htmlspecialchars($contrato['fornecedor']) ?>"></label>
        <label>Responsável:
          <input type="text" name="responsavel" value="<?= htmlspecialchars($contrato['responsavel']) ?>"></label>
      </div>
      <div class="form-row">
        <label>Valor Total:<br>
          <input type="text" name="valor_total" id="valor_total" value="<?= number_format($contrato['valor_total'], 2, ',', '.') ?>" required>
        </label><br><br>
        <label>Data Início:<br><input type="date" name="data_inicio" value="<?= $contrato['data_inicio'] ?>" required></label><br><br>
      </div>
      <div class="form-row">
        <label>Data Fim:<br><input type="date" name="data_fim" value="<?= $contrato['data_fim'] ?>" required></label><br><br>
        <label>Data Assinatura:<br><input type="date" name="data_assinatura" value="<?= $contrato['data_assinatura'] ?>" required></label><br><br>
        <label>Local Arquivo:<br>
          <select name="local_arquivo">
            <option value="Digital" <?= $contrato['local_arquivo'] === 'Digital' ? 'selected' : '' ?>>Digital</option>
            <option value="Físico" <?= $contrato['local_arquivo'] === 'Físico' ? 'selected' : '' ?>>Físico</option>
          </select>
        </label><br>
      </div>

      <div class="form-row">
        <!-- Campo: Prorrogável -->
        <label>
          Prorrogável:
          <select name="prorrogavel" id="prorrogavel">
            <option value="Não" <?= $contrato['prorrogavel'] === 'Não' ? 'selected' : '' ?>>Não</option>
            <option value="Sim" <?= $contrato['prorrogavel'] === 'Sim' ? 'selected' : '' ?>>Sim</option>
          </select>
        </label>
        <!-- Campos extras, mostrados só se for prorrogável -->
        <div id="campos-prorrogaveis" style="display: none; gap: 1rem; flex-wrap: wrap;">
          <label>
            Prazo Máximo (em anos):
            <input type="number" name="prazo_maximo" value="<?= htmlspecialchars($contrato['prazo_maximo'] ?? 10) ?>">
          </label>

          <label>
            Data do Último Aditivo:
            <input type="date" name="data_ultimo_aditivo" value="<?= htmlspecialchars($contrato['data_ultimo_aditivo'] ?? '') ?>">
          </label>
        </div>
      </div>
      <div class="form-row">
        <label>Observações:<br>
          <textarea name="observacoes"><?= $contrato['observacoes'] ?></textarea>
        </label>
      </div>
      <div class="form-row">
        <label>Objeto:<br>
          <textarea name="objeto"><?= $contrato['objeto'] ?></textarea>
        </label>
      </div>
      <?php if (!empty($contrato['arquivo_pdf'])): ?>
        <p>Contrato atual:
          <a href="/<?= htmlspecialchars($contrato['arquivo_pdf']) ?>" target="_blank">📎 Ver contrato</a>
        </p>
      <?php endif; ?>
      <?php if (!empty($arquivosExistentes)): ?>
        <h4>Arquivos vinculados</h4>
        <table class="table" style="margin-bottom: 2rem;">
          <thead>
            <tr>
              <th>Nome</th>
              <th>Tipo</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($arquivosExistentes as $arq): ?>
              <tr>
                <td><?= htmlspecialchars($arq['nome_arquivo']) ?></td>
                <td><?= htmlspecialchars($arq['tipo']) ?></td>
                <td>
                  <button type="button" onclick="abrirModalExcluirArquivo(<?= $arq['id'] ?>)" class="btn-link excluir">Excluir</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <!-- Upload de arquivos -->
      <h4>Anexar arquivos</h4>
      <div class="custom-file-wrapper">
        <input type="file" id="arquivo_pdf" class="input-arquivo" accept="application/pdf">
        <label for="arquivo_pdf" class="btn-custom-file">📎 Escolher Arquivo</label>
        <span id="nome-arquivo" class="nome-arquivo">Nenhum arquivo selecionado</span>
        <select id="tipo_arquivo">
          <option value="Contrato">Contrato</option>
          <option value="Aditivo">Aditivo</option>
          <option value="Outro">Outro</option>
        </select>
        <button type="button" onclick="adicionarArquivo()" style="background: blue">Adicionar</button>
      </div>

      <table id="lista-arquivos" style="margin-top: 1rem;">
        <thead>
          <tr>
            <th>Nome</th>
            <th>Tipo</th>
            <th>Ação</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
      <button type="submit" class="btn-link editar">Salvar Alterações</button>
    </form>

  </main>
  <div id="modal-excluir-arquivo" class="modal" style="display:none;">
    <div class="modal-content">
      <h3>Justifique a exclusão</h3>
      <form method="post" action="../api/contratos/excluir_arquivo.php" onsubmit="return validarJustificativa()">
        <input type="hidden" name="arquivo_id" id="arquivo_id_modal">
        <textarea name="justificativa" id="justificativa_modal" required placeholder="Descreva o motivo"></textarea>
        <br>
        <button type="submit">Confirmar Exclusão</button>
        <button type="button" onclick="fecharModal()">Cancelar</button>
      </form>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const input = document.getElementById('valor_total');

      input.addEventListener('input', function() {
        let v = input.value.replace(/[\\.]/g, '').replace(',', '');
        v = (parseFloat(v) / 100).toFixed(2);
        v = v.replace('.', ',');
        v = v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        input.value = v;
      });

      // Evita erro se o valor estiver vazio
      if (input.value && !input.value.includes(',')) {
        let raw = parseFloat(input.value).toFixed(2);
        raw = raw.replace('.', ',');
        input.value = raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      }
    });

    document.addEventListener('DOMContentLoaded', () => {
      toggleCamposProrrogavel(); // Inicializa campos prorrogáveis
      document.querySelector('#prorrogavel').addEventListener('change', toggleCamposProrrogavel);
    });

    function toggleCamposProrrogavel() {
      const prorrogavel = document.getElementById('prorrogavel').value;
      const extras = document.getElementById('campos-prorrogaveis');
      extras.style.display = prorrogavel === 'Sim' ? 'flex' : 'none';
    }

    const arquivosAdicionados = [];

    function adicionarArquivo() {
      const input = document.getElementById('arquivo_pdf');
      const tipo = document.getElementById('tipo_arquivo').value;
      const file = input.files[0];

      if (!file || file.type !== "application/pdf") {
        alert("Selecione um arquivo PDF.");
        return;
      }

      arquivosAdicionados.push({
        file,
        tipo
      });
      atualizarTabela();
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

    // Enviar formulário com arquivos
    document.querySelector('#form-contrato').addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);

      arquivosAdicionados.forEach(item => {
        formData.append('arquivos[]', item.file);
        formData.append('tipos[]', item.tipo);
      });

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

    function abrirModalExcluirArquivo(id) {
      document.getElementById('arquivo_id_modal').value = id;
      document.getElementById('modal-excluir-arquivo').style.display = 'flex'; // importante: "flex" por causa do CSS
    }

    function fecharModal() {
      document.getElementById('modal-excluir-arquivo').style.display = 'none';
      document.getElementById('justificativa_modal').value = '';
    }

    function validarJustificativa() {
      const justificativa = document.getElementById('justificativa_modal').value.trim();
      if (justificativa === '') {
        alert("Por favor, insira a justificativa.");
        return false;
      }
      return true;
    }

    //estilo para o adicionar arquivos
    document.getElementById('arquivo_pdf').addEventListener('change', function() {
      const nome = this.files.length > 0 ? this.files[0].name : 'Nenhum arquivo selecionado';
      document.getElementById('nome-arquivo').textContent = nome;
    });
  </script>

</body>

</html>
