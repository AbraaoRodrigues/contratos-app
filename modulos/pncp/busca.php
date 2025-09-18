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
          <option value="">Todas</option>
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
      <div class="pager">
        <button id="btnPrev" class="btn" disabled>« Anterior</button>
        <span id="pageInfo" class="muted"></span>
        <button id="btnNext" class="btn" disabled>Próxima »</button>
      </div>
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
      </div>
    </div>

    <div class="bar">
      <div class="muted">Exportar (lista atual)</div>
      <div class="exports">
        <a id="expPdf" class="btn" href="#" target="_blank" title="PDF">PDF</a>
        <a id="expWord" class="btn" href="#" target="_blank" title="Word">Word</a>
        <a id="expExcel" class="btn" href="#" target="_blank" title="Excel">Excel</a>
      </div>
    </div>
  </section>

  <script src="assets/busca.js"></script>
</body>

</html>
