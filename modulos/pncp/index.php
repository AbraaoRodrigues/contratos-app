<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: /templates/login.php');
  exit;
}
require_once '../../config/db.php';
$pdo = Conexao::getInstance();

$modoEscuro = $_SESSION['modo_escuro'] ?? false;
include 'includes/header.php';
require_once '../../templates/includes/verifica_login.php';

?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <title>PNCP – Consulta</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/styles.css">
</head>

<body class="<?= $modoEscuro ? 'dark' : '' ?>">
  <h2>Consulta PNCP (teste funcional)</h2>

  <div id="alert" class="alert" style="display:none"></div>

  <form id="formConsulta" class="grid-form">
    <label>
      <span>Palavra-chave (filtro local)</span>
      <input type="text" name="palavra" placeholder="ex.: computador">
    </label>

    <label>
      <span>UF</span>
      <select name="uf">
        <option value="">Todas</option>
        <?php
        foreach (["AC", "AL", "AM", "AP", "BA", "CE", "DF", "ES", "GO", "MA", "MG", "MS", "MT", "PA", "PB", "PE", "PI", "PR", "RJ", "RN", "RO", "RR", "RS", "SC", "SE", "SP", "TO"] as $uf)
          echo "<option>$uf</option>";
        ?>
      </select>
    </label>

    <label>
      <span>Status (filtro local)</span>
      <select name="statusLocal">
        <option value="">Todos</option>
        <option value="RECEBENDO">Recebendo propostas</option>
        <option value="JULGAMENTO">Em julgamento</option>
        <option value="ENCERRADA">Encerrada</option>
      </select>
    </label>

    <label style="grid-column: span 2;">
      <span>&nbsp;</span>
      <label style="display:flex;gap:8px;align-items:center;">
        <input type="checkbox" name="relaxarStatus" checked>
        Ignorar status quando a busca zerar resultados
      </label>
    </label>

    <label>
      <span>Modalidade (obrigatória)</span>
      <select name="modalidade" required>
        <option value="6">Pregão Eletrônico (6)</option>
        <option value="7">Pregão Presencial (7)</option>
        <option value="4">Concorrência Eletrônica (4)</option>
        <option value="5">Concorrência Presencial (5)</option>
        <option value="8">Dispensa (8)</option>
        <option value="9">Inexigibilidade (9)</option>
      </select>
    </label>

    <label>
      <span>Data inicial</span>
      <input type="date" name="dataInicial" required>
    </label>

    <label>
      <span>Data final</span>
      <input type="date" name="dataFinal" required>
    </label>

    <div class="actions">
      <button class="btn" type="submit">Buscar processos</button>
    </div>
  </form>

  <p class="muted">A API pública exige <b>datas (≤ 365 dias)</b> e <b>modalidade</b>. Palavra-chave e status são filtros locais.</p>

  <div class="columns">
    <section>
      <h3>Processos encontrados <small id="countInfo" class="muted"></small></h3>
      <table id="tblProcessos">
        <thead>
          <tr>
            <th>Objeto</th>
            <th>Órgão/UF</th>
            <th>Status</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
      <div class="pager">
        <button id="btnPrev" class="btn">« Anterior</button>
        <span id="pageInfo" class="muted"></span>
        <button id="btnNext" class="btn">Próxima »</button>
      </div>

      <div style="margin-top:10px;display:flex;gap:10px;align-items:center">
        <input type="text" id="nomeLista" placeholder="Nome da lista (ex.: Pesquisa 10/09)">
        <button id="btnSalvarLista" class="btn">Salvar lista</button>
        <span id="saveMsg" class="muted"></span>
        <div style="margin-left:auto;display:flex;gap:8px">
          <a id="expPdf" class="btn" href="#" target="_blank">PDF</a>
          <a id="expWord" class="btn" href="#" target="_blank">Word</a>
          <a id="expExcel" class="btn" href="#" target="_blank">Excel</a>
        </div>
      </div>

    </section>

    <section>
      <h3>Itens do processo</h3>
      <div id="itensAviso" class="warn" style="display:none"></div>
      <table id="tblItens">
        <thead>
          <tr>
            <th>Descrição</th>
            <th>Qtd</th>
            <th>Valor Unit.</th>
            <th>Ação</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </section>
  </div>

  <section>
    <h3>Itens selecionados</h3>
    <table id="tblSelecionados">
      <thead>
        <tr>
          <th>Descrição</th>
          <th>Qtd</th>
          <th>Valor Unit.</th>
          <th>Origem</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </section>

  <section>
    <h3>Tabela Final (consolidada)</h3>
    <table id="tblFinal">
      <thead>
        <tr>
          <th>Descrição</th>
          <th>Valor Médio</th>
          <th>Quantidade</th>
          <th>Valor Total</th> <!-- 👈 NOVO -->
          <th>Referências</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    <button id="btnGerarFinal" class="btn">Gerar Tabela Final</button>
    <div style="margin-top:10px; display:flex; gap:8px;">
      <button id="btnExportPdf" class="btn">Exportar PDF</button>
      <button id="btnExportWord" class="btn">Exportar Word</button>
      <button id="btnExportExcel" class="btn">Exportar Excel</button>
      <input type="text" id="nomePesquisa" placeholder="Nome da pesquisa (ex.: Referência Café/Açúcar)">
      <button id="btnSalvarPesquisa" class="btn">Salvar Pesquisa</button>
      <span id="msgPesquisa" class="muted"></span>
    </div>

  </section>

  <script src="assets/app.js"></script>

</body>

</html>
