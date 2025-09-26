<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: /templates/login.php');
  exit;
}
require_once __DIR__ . '/../../config/db_precos.php';
$pdo = ConexaoPrecos::getInstance();

include 'includes/header.php';
require_once '../../templates/includes/verifica_login.php';

$modoEscuro = $_SESSION['modo_escuro'] ?? false;
?>
<!doctype html>
<html lang="pt-br" data-theme="<?= $modoEscuro ? 'dark' : 'light' ?>">

<head>
  <meta charset="utf-8">
  <title>PNCP – Busca no Cache</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/busca.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>

<body>
  <header class="topbar">
    <h2>Consulta PNCP (base local)</h2>
    <div class="hint">Dados sincronizados via CRON (cache_pncp_*). Exibe resultados em milissegundos 😉</div>
  </header>

  <div id="alert" class="alert" style="display:none"></div>

  <form id="formBusca" class="filters">
    <div class="field">
      <label>Palavra-chave</label>
      <input type="text" name="palavra" placeholder="ex.: computador, açúcar, reagente...">
      <small>Procura em objeto/órgão. Opcional: buscar também nos itens.</small>
    </div>

    <div class="row">
      <div class="field">
        <label>UF</label>
        <select name="uf">
          <option value="">SP</option>
          <?php
          foreach (["AC", "AL", "AM", "AP", "BA", "CE", "DF", "ES", "GO", "MA", "MG", "MS", "MT", "PA", "PB", "PE", "PI", "PR", "RJ", "RN", "RO", "RR", "RS", "SC", "SE", "SP", "TO"] as $uf) {
            echo "<option value=\"$uf\">$uf</option>";
          }
          ?>
        </select>
      </div>

      <div class="field">
        <label>Modalidade</label>
        <select name="modalidade">
          <option value="">Todas</option>
          <option value="Pregão - Eletrônico">Pregão Eletrônico</option>
          <option value="Pregão - Presencial">Pregão Presencial</option>
          <option value="Concorrência - Eletrônica">Concorrência Eletrônica</option>
          <option value="Concorrência - Presencial">Concorrência Presencial</option>
          <option value="Dispensa">Dispensa</option>
          <option value="Inexigibilidade">Inexigibilidade</option>
        </select>
        <small>(usa texto do status/modalidade salvos no cache)</small>
      </div>

      <div class="field">
        <label>Status</label>
        <select name="status">
          <option value="">Todos</option>
          <option value="Divulgada">Divulgada</option>
          <option value="Em julgamento">Em julgamento</option>
          <option value="Homologado">Homologado</option>
          <option value="Encerrada">Encerrada</option>
        </select>
      </div>

      <div class="field">
        <label>Período (dias)</label>
        <input type="number" name="periodoDias" min="1" max="365" value="365">
      </div>
    </div>

    <div class="row">
      <label class="chk">
        <input type="checkbox" name="incluirItens">
        Buscar também nos itens (pode ficar mais lento)
      </label>

      <div class="spacer"></div>

      <div class="pager-opts">
        <label>Por página
          <select name="pageSize">
            <option>25</option>
            <option selected>50</option>
            <option>100</option>
          </select>
        </label>
        <button class="btn" type="submit">Buscar</button>
      </div>
    </div>
  </form>

  <section class="results">
    <div class="bar">
      <div id="countInfo" class="muted"></div>
    </div>

    <div class="cols">
      <div class="col col-60">
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
          <button id="btnPrev" class="btn" disabled>« Anterior</button>
          <span id="pageInfo" class="muted"></span>
          <button id="btnNext" class="btn" disabled>Próxima »</button>
        </div>
      </div>

      <div class="col col-40">
        <h3>Itens do processo</h3>
        <div id="itensAviso" class="warn" style="display:none"></div>
        <table id="tblItens">
          <thead>
            <tr>
              <th>#</th>
              <th>Descrição</th>
              <th>Qtd</th>
              <th>V. Unit.</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
        <div id="paginacaoItens" class="pager">
          <button id="btnItensPrev" class="btn">« Anterior</button>
          <span id="infoItens" class="muted"></span>
          <button id="btnItensNext" class="btn">Próxima »</button>
        </div>

      </div>
    </div>

    <h3>Itens selecionados</h3>
    <div id="selecionadosBox">
      <table id="tblSelecionados">
        <thead>
          <tr>
            <th></th>
            <th>Descrição</th>
            <th>Qtd</th>
            <th>Valor Unitário</th>
            <th>Origem</th>
            <th></th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
      <button id="btnGerarTabela" class="btn">Gerar Tabela Final</button>
      <div id="previewTabelaFinalBox" style="margin-top:20px; display:none;">
        <h3>Tabela Final de Referência de Preços</h3>
        <table id="previewTabelaFinal" class="grid">
          <thead>
            <tr>
              <th style="width:28px">
                <input type="checkbox" id="chkAll">
              </th>
              <th>Descrição</th>
              <th>Qtd</th>
              <th>Valor Médio</th>
              <th>Referências</th>
              <th>Valor Total</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
        <div style="margin-top:10px;">
          <button id="btnMerge" class="btn">➕ Mesclar selecionados</button>
        </div>
        <div id="exportBtns" style="margin-top:15px; display:none;">
          <a id="expPdf" href="#" title="Exportar PDF"><i class="fas fa-file-pdf fa-lg" style="color:#e74c3c"></i></a>
          <a id="expWord" href="#" title="Exportar Word"><i class="fas fa-file-word fa-lg" style="color:#2a5699"></i></a>
          <a id="expExcel" href="#" title="Exportar Excel"><i class="fas fa-file-excel fa-lg" style="color:#217346"></i></a>
        </div>

      </div>
  </section>

  <script src="assets/busca.js"></script>
</body>

</html>
