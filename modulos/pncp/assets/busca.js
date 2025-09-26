(function(){
  const form = document.getElementById('formBusca');
  const alertBox = document.getElementById('alert');
  const tblProcBody = document.querySelector('#tblProcessos tbody');
  const tblItensBody = document.querySelector('#tblItens tbody');
  const itensAviso = document.getElementById('itensAviso');
  const countInfo = document.getElementById('countInfo');
  const pageInfo = document.getElementById('pageInfo');
  const btnPrev = document.getElementById('btnPrev');
  const btnNext = document.getElementById('btnNext');
  const btnItensPrev = document.getElementById('btnItensPrev');
  const btnItensNext = document.getElementById('btnItensNext');
  const infoItens    = document.getElementById('infoItens');

  let currentPage = 1;
  let lastQuery = null;
  let totalFound = 0;
  let pageSize = 50;
  let lastData = [];

  let currentItemPage = 1;
  let itemsPageSize = 25;
  let ultimoProcCarregado = null;
  let totalItemPages = 1;

  function showAlert(msg){
    alertBox.textContent = msg;
    alertBox.style.display = 'block';
  }
  function clearAlert(){
    alertBox.style.display = 'none';
    alertBox.textContent = '';
  }
  function normalize(s){
    return (s||'').normalize('NFD').replace(/\p{Diacritic}/gu,'').toLowerCase();
  }
  function mark(text, term){
    if(!term) return text;
    const rx = new RegExp('('+term.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')','ig');
    return text.replace(rx,'<mark>$1</mark>');
  }

  form.addEventListener('submit', (e)=>{
    e.preventDefault();
    currentPage = 1;
    executeQuery();
  });

  btnPrev.addEventListener('click', ()=>{
    if(currentPage>1){ currentPage--; executeQuery(lastQuery); }
  });
  btnNext.addEventListener('click', ()=>{
    const maxPage = Math.ceil(totalFound / pageSize);
    if(currentPage < maxPage){ currentPage++; executeQuery(lastQuery); }
  });

  async function executeQuery(){
    clearAlert();
    tblProcBody.innerHTML = '<tr><td colspan="4">Carregando...</td></tr>';
    tblItensBody.innerHTML = '';
    itensAviso.style.display = 'none';

    const fd = new FormData(form);
    const params = new URLSearchParams();
    for (const [k,v] of fd.entries()) {
      if (v!=='' && k!=='relaxarStatus') params.append(k,v);
    }
    params.set('page', currentPage.toString());
    pageSize = parseInt(fd.get('pageSize') || '50', 10);
    params.set('pageSize', pageSize.toString());

    lastQuery = params;

    const url = 'api/buscar_cache.php?' + params.toString();
    let resp, json;
    try{
      resp = await fetch(url);
      if(!resp.ok) throw new Error('HTTP '+resp.status);
      json = await resp.json();
    }catch(err){
      tblProcBody.innerHTML = '<tr><td colspan="4">Erro ao consultar.</td></tr>';
      showAlert('Erro ao consultar a base local: '+ err.message);
      return;
    }

    if(!json.ok){
      tblProcBody.innerHTML = '<tr><td colspan="4">—</td></tr>';
      showAlert(json.msg || 'Erro.');
      return;
    }

    totalFound = json.total || 0;
    lastData = json.data || [];
    const palavra = normalize(fd.get('palavra')||'');

    // Calcula início e fim da faixa
const inicio = (currentPage - 1) * pageSize + 1;
const fim = Math.min(currentPage * pageSize, totalFound);

// Atualiza texto
countInfo.textContent = `Exibindo ${inicio} – ${fim} de ${totalFound} registros`;

    const maxPage = Math.max(1, Math.ceil(totalFound / pageSize));
    pageInfo.textContent = `Página ${json.page} / ${maxPage}`;
    btnPrev.disabled = (currentPage<=1);
    btnNext.disabled = (currentPage>=maxPage);

    if(lastData.length===0){
      tblProcBody.innerHTML = '<tr><td colspan="4">Sem resultados.</td></tr>';
      return;
    }

    tblProcBody.innerHTML = '';

    lastData.forEach(row=>{
      const tr = document.createElement('tr');

      const obj = row.objeto || '(sem descrição)';
      const org = row.orgao || '';
      const uf  = row.uf || '';
      const st  = row.status || '';
      const pub = row.dataPublicacao || '';
      const abertura = row.dataAbertura || '';

      tr.innerHTML = `
        <td><b>${mark(obj, palavra)}</b><div class="muted">#PNCP: ${row.numeroControlePNCP}</div></td>
        <td>${mark(org, palavra)}<div class="muted">${uf}</div></td>
        <td><span class="pill">${st||'-'}</span><div class="muted">${pub}</div>
        <div class="muted">📂 Abertura: ${abertura || '-'}</div></td>
        <td>
          <button class="btn btn-mini" data-id="${row.numeroControlePNCP}">Ver itens</button>
        </td>
      `;
      tblProcBody.appendChild(tr);
    });

    tblProcBody.querySelectorAll('button[data-id]').forEach(btn=>{
      btn.addEventListener('click', ()=> carregarItens(btn.getAttribute('data-id')));
    });

  }

async function carregarItens(numeroControlePNCP) {
  if (ultimoProcCarregado !== numeroControlePNCP) {
    currentItemPage = 1; // 🔄 sempre volta para página 1 ao trocar de processo
  }
  // guarda qual processo está aberto
  ultimoProcCarregado = numeroControlePNCP;

  tblItensBody.innerHTML = '<tr><td colspan="5">Carregando itens...</td></tr>';
  itensAviso.style.display = 'none';
  document.querySelectorAll("tr.ativo").forEach(tr => tr.classList.remove("ativo"));

  const linha = document.querySelector(`button[data-id='${numeroControlePNCP}']`)?.closest("tr");
  if (linha) linha.classList.add("ativo");

  try {
    const url = `api/itens_cache.php?numeroControlePNCP=${encodeURIComponent(numeroControlePNCP)}&page=${currentItemPage}&pageSize=${itemsPageSize}`;
    const r = await fetch(url);
    const j = await r.json();
    if (!j.ok) throw new Error(j.msg || 'Falha ao obter itens.');

    const itens = j.itens || [];
    totalItemPages = Math.max(1, Math.ceil((j.total || 0) / j.pageSize));

    if (!Array.isArray(itens) || itens.length === 0) {
      tblItensBody.innerHTML = '<tr><td colspan="5">Sem itens para este processo.</td></tr>';
      return;
    }

    tblItensBody.innerHTML = '';
    itens.forEach(it => {
      const v = it.valorUnit != null
        ? 'R$ ' + Number(it.valorUnit).toLocaleString('pt-BR', { minimumFractionDigits: 2 })
        : (it.valorUnitarioHomologado != null || it.valorUnitarioEstimado != null
          ? 'R$ ' + Number(it.valorUnitarioHomologado ?? it.valorUnitarioEstimado).toLocaleString('pt-BR', { minimumFractionDigits: 2 })
          : 'SIGILOSO');
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${it.numeroItem ?? '-'}</td>
        <td>${it.descricao || '-'}</td>
        <td>${it.quantidade ?? '-'}</td>
        <td>${v}</td>
        <td><button class="btn btn-mini" data-add='${JSON.stringify(it)}'>Selecionar Item</button></td>
      `;
      tblItensBody.appendChild(tr);
    });

    // Atualiza paginação
    const inicio = (currentItemPage - 1) * itemsPageSize + 1;
const fim = Math.min(currentItemPage * itemsPageSize, j.total || 0);
infoItens.textContent = `Exibindo ${inicio} – ${fim} de ${j.total || 0} itens (Página ${currentItemPage} / ${totalItemPages})`;

    btnItensPrev.disabled = currentItemPage <= 1;
    btnItensNext.disabled = currentItemPage >= totalItemPages;

  } catch (err) {
    itensAviso.innerHTML = 'Não foi possível carregar itens do cache.';
    itensAviso.style.display = 'block';
    tblItensBody.innerHTML = '';
  }
}

// Botões de paginação de itens
btnItensPrev.addEventListener("click", () => {
  if (currentItemPage > 1) {
    currentItemPage--;
    carregarItens(ultimoProcCarregado);
  }
});

btnItensNext.addEventListener("click", () => {
  if (currentItemPage < totalItemPages) {
    currentItemPage++;
    carregarItens(ultimoProcCarregado);
  }
});

  // primeira carga
  executeQuery();
})();

let itensSelecionados = [];

document.addEventListener('click', (e) => {
  if (e.target.matches('button[data-add]')) {
    const item = JSON.parse(e.target.getAttribute('data-add'));
    itensSelecionados.push(item);
    renderSelecionados();
  }

  if (e.target.matches('button[data-remove]')) {
    const idx = e.target.dataset.remove;
    itensSelecionados.splice(idx, 1);
    renderSelecionados();
  }
});

function renderSelecionados() {
  const tbody = document.querySelector('#tblSelecionados tbody');
  tbody.innerHTML = '';
  itensSelecionados.forEach((it, i) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${it.descricao}</td>
      <td>${it.quantidade ?? '-'}</td>
      <td>${it.valorUnit ?? '-'}</td>
      <td>${it.numeroControlePNCP ?? '-'}</td>
      <td><button data-remove="${i}">❌</button></td>
    `;
    tbody.appendChild(tr);
  });
}

// util: normaliza p/ chave de agrupamento
function chave(desc) {
  return (desc || '').normalize('NFD').replace(/\p{Diacritic}/gu,'')
           .toLowerCase().replace(/\s+/g,' ').trim();
}

// === GERA A TABELA FINAL A PARTIR DE itensSelecionados ===
function gerarTabelaFinal() {
  if (!Array.isArray(itensSelecionados) || itensSelecionados.length === 0) {
    alert('Nenhum item selecionado!');
    return;
  }

  const box    = document.getElementById('previewTabelaFinalBox');
  const tabela = document.getElementById('previewTabelaFinal');
  const tbody  = tabela.querySelector('tbody');

  // mostra a box (estava escondida por default)
  box.style.display = 'block';

  // Recalcula total quando mudar quantidade
  tbody.querySelectorAll('.qtdInput').forEach(input => {
    input.addEventListener('input', () => {
      const tr    = input.closest('tr');
      const media = parseFloat(tr.querySelector('.media').dataset.media) || 0;
      const qtd   = parseFloat(input.value) || 0;
      tr.querySelector('.total').textContent =
        'R$ ' + (media * qtd).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
    });
  });

  // Agrupar por descrição normalizada (somando valores e contando ocorrências)
  const mapa = new Map();
  itensSelecionados.forEach((it) => {
    const desc = (it.descricao || '(sem descrição)').trim();
    const k    = chave(desc);
    const v    = Number(it.valorUnit ?? it.valorUnitarioHomologado ?? it.valorUnitarioEstimado ?? 0);
    const ref  = it.numeroControlePNCP || '';

    if (!mapa.has(k)) {
      mapa.set(k, { descOriginal: desc, soma: 0, cont: 0, refs: new Set() });
    }
    const obj = mapa.get(k);
    obj.soma += isFinite(v) ? v : 0;
    obj.cont += 1;
    if (ref) obj.refs.add(ref);
  });

  // Limpa corpo e renderiza linhas
  tbody.innerHTML = '';
  mapa.forEach((obj, k) => {
    const media = obj.cont ? (obj.soma / obj.cont) : 0;

    const tr = document.createElement('tr');
    tr.dataset.key   = k;
    tr.dataset.soma  = String(obj.soma);
    tr.dataset.cont  = String(obj.cont);

    tr.innerHTML = `
      <td><input type="checkbox" class="chkRow"></td>
      <td class="desc">${obj.descOriginal}</td>
      <td>
        <input type="number" class="qtdInput" min="1" step="1" value="1">
      </td>
      <td class="media" data-media="${media}">
        R$ ${media.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
      </td>
      <td class="refs">${[...obj.refs].join(', ')}</td>
      <td class="total">
        R$ ${media.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
      </td>
    `;
    tbody.appendChild(tr);
  });

  // Ativa botões de exportação
  const expBox = document.getElementById('exportBtns');
  expBox.style.display = 'block';

  // Prepara dados da tabela final para enviar ao servidor
  const linhas = [...tbody.querySelectorAll('tr')].map(tr => {
    const desc  = tr.querySelector('.desc').textContent.trim();
    const qtd   = parseFloat(tr.querySelector('.qtdInput').value) || 0;
    const media = parseFloat(tr.querySelector('.media').dataset.media) || 0;
    const refs  = tr.querySelector('.refs').textContent.trim();
    const total = media * qtd;
    return { descricao: desc, quantidade: qtd, valor_medio: media, valor_total: total, referencias: refs };
  });

  const payload = JSON.stringify({ itens: linhas });

  // Liga os botões de exportação para chamar via POST
  document.getElementById('expPdf').onclick = () => {
    baixarArquivo('/modulos/pncp/api/exportar.php?formato=pdf', payload, 'tabela_final_' + Date.now() + '.pdf');
  };
  document.getElementById('expWord').onclick = () => {
    baixarArquivo('/modulos/pncp/api/exportar.php?formato=word', payload, 'tabela_final_' + Date.now() + '.docx');
  };
  document.getElementById('expExcel').onclick = () => {
    baixarArquivo('/modulos/pncp/api/exportar.php?formato=excel', payload, 'tabela_final_' + Date.now() + '.xlsx');
  };
}

  // Selecionar todos
  const chkAll = document.getElementById('chkAll');
  chkAll.checked = false;
  chkAll.onchange = () => {
    tbody.querySelectorAll('.chkRow').forEach(chk => chk.checked = chkAll.checked);
  };


  // Botão mesclar (bind único por render)
  const btnMerge = document.getElementById('btnMerge');
  btnMerge.onclick = mesclarSelecionados;


// === MESCLA AS LINHAS MARCADAS (média recomputada) ===
function mesclarSelecionados() {
  const tabela = document.getElementById('previewTabelaFinal');
  const tbody  = tabela.querySelector('tbody');

  const selecionadas = [...tbody.querySelectorAll('tr')]
    .filter(tr => tr.querySelector('.chkRow')?.checked);

  if (selecionadas.length < 2) {
    alert('Selecione ao menos 2 linhas para mesclar.');
    return;
  }

  if (!confirm(`Deseja realmente mesclar ${selecionadas.length} linhas selecionadas?`)) {
    return;
  }

  // alvo = primeira selecionada
  const alvo = selecionadas[0];
  let somaAlvo = parseFloat(alvo.dataset.soma) || 0;
  let contAlvo = parseInt(alvo.dataset.cont || '0', 10);
  const refsAlvo = new Set(alvo.querySelector('.refs').textContent.split(',')
                      .map(s => s.trim()).filter(Boolean));

  // mescla as demais no alvo
  for (let i = 1; i < selecionadas.length; i++) {
    const tr = selecionadas[i];
    somaAlvo += parseFloat(tr.dataset.soma) || 0;
    contAlvo += parseInt(tr.dataset.cont || '0', 10);

    tr.querySelector('.refs').textContent.split(',').forEach(r => {
      r = r.trim();
      if (r) refsAlvo.add(r);
    });

    tr.remove(); // remove linha mesclada
  }

  // Atualiza alvo
  alvo.dataset.soma = String(somaAlvo);
  alvo.dataset.cont = String(contAlvo);

  const media = contAlvo ? (somaAlvo / contAlvo) : 0;
  const qtd   = parseFloat(alvo.querySelector('.qtdInput').value) || 0;

  alvo.querySelector('.media').dataset.media = String(media);
  alvo.querySelector('.media').textContent   =
    'R$ ' + media.toLocaleString('pt-BR', { minimumFractionDigits: 2 });

  alvo.querySelector('.refs').textContent    = [...refsAlvo].join(', ');
  alvo.querySelector('.total').textContent   =
    'R$ ' + (media * qtd).toLocaleString('pt-BR', { minimumFractionDigits: 2 });

  // limpa seleção
  document.getElementById('chkAll').checked = false;
  tbody.querySelectorAll('.chkRow').forEach(chk => chk.checked = false);
}

// botão principal
document.getElementById('btnGerarTabela')
  .addEventListener('click', gerarTabelaFinal);


document.getElementById('btnSalvarLista').addEventListener('click', async () => {
  const nome = document.getElementById('nomeLista').value.trim();
  const listaId = document.getElementById('listaExistente').value;

  if (!nome && !listaId) {
    alert('Informe um nome para a lista ou selecione uma existente!');
    return;
  }

  // 1) Coletar TABELA CONSOLIDADA (preview)
  const consolidados = [];
  document.querySelectorAll('#previewTabelaFinal tbody tr').forEach(tr => {
    const descricao = tr.cells[0].textContent.trim();
    const qtdInput  = tr.querySelector('.qtdInput');
    const qtd       = parseFloat(qtdInput.value) || 0;
    const media     = parseFloat(qtdInput.dataset.media);
    const origens   = tr.cells[3].textContent.trim();

    consolidados.push({
      descricao,
      quantidade: qtd,
      valor_medio: media,
      referencias: origens
    });
  });

  if (consolidados.length === 0) {
    alert('Nenhum item consolidado para salvar!');
    return;
  }

  // 2) Coletar ITENS BRUTOS selecionados (para recalcular médias)
  //    Ajuste conforme o shape do seu itensSelecionados
  const itens_raw = (window.itensSelecionados || []).map(it => ({
    descricao: it.descricao,
    quantidade: Number(it.quantidade ?? 1),
    valor_unitario: Number(it.valorUnit ?? it.valorUnitarioHomologado ?? it.valorUnitarioEstimado ?? 0),
    origem: it.numeroControlePNCP
  }));

  // Prepara dados da tabela final para enviar ao servidor
const payload = JSON.stringify({ itens: linhas });

  console.log("📤 Payload enviado:", payload);

  try {
    const res = await fetch('api/salvar_pesquisa.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const text = await res.text();
    console.log("📥 Resposta bruta:", text);

    const json = JSON.parse(text);
    if (json.ok) {
      alert(`✅ Lista salva (ID ${json.lista_id})`);
      // opcional: limpar UI
      // document.getElementById('nomeLista').value = '';
      // document.getElementById('previewTabelaFinal').innerHTML = '';
      // window.itensSelecionados = [];
      // renderSelecionados();
      // recarregar combo:
      // carregarListasExistentes();
    } else {
      alert('⚠️ Erro ao salvar: ' + (json.msg || 'Falha desconhecida'));
    }
  } catch (err) {
    console.error(err);
    alert(`❌ Erro ao salvar lista: ${err.message}`);
  }
});

// === EXPORTAÇÃO (POST JSON + download) ===
const EXPORT_URL = '/modulos/pncp/api/exportar.php';

// === Lê a tabela final (#previewTabelaFinal) e monta o payload ===
function coletarItensConsolidados() {
  const linhas = [];
  document.querySelectorAll('#previewTabelaFinal tbody tr').forEach(tr => {
    const desc  = tr.querySelector('.desc')?.textContent.trim() || tr.cells[1].textContent.trim();
    const qtd   = parseFloat(tr.querySelector('.qtdInput')?.value) || 0;
    const media = parseFloat(tr.querySelector('.media')?.dataset.media) || 0;
    const refs  = tr.querySelector('.refs')?.textContent.trim() || '';
    const total = media * qtd;
    if (desc) linhas.push({ descricao: desc, quantidade: qtd, valor_medio: media, valor_total: total, referencias: refs });
  });
  return linhas;
}

// === Exporta via POST e força download (PDF/DOCX/XLSX) ===
async function exportarTabela(formato) {
  const itens = coletarItensConsolidados();
  if (!itens.length) {
    alert('Nenhum item consolidado para exportar.');
    return;
  }

  try {
    const res = await fetch(`${EXPORT_URL}?formato=${encodeURIComponent(formato)}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ itens })
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);

    // pega o binário retornado pelo PHP (PDF/DOCX/XLSX)
    const blob = await res.blob();

    // monta nome do arquivo com timestamp
    const ts  = new Date().toISOString().slice(0,19).replace(/[:T]/g,'-');
    const ext = (formato === 'pdf') ? 'pdf' : (formato === 'word' ? 'docx' : 'xlsx');
    const filename = `tabela_final_${ts}.${ext}`;

    // cria URL temporária e dispara o download
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);

  } catch (e) {
    console.error(e);
    alert('Erro ao exportar: ' + e.message);
  }
}


// Botão principal
document.getElementById('btnGerarTabela').addEventListener('click', gerarTabelaFinal);

async function baixarArquivo(url, payload, nomeArquivo) {
  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: payload
    });
    if (!res.ok) throw new Error("Erro HTTP " + res.status);

    const blob = await res.blob();
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = nomeArquivo;
    document.body.appendChild(link);
    link.click();
    link.remove();
  } catch (err) {
    alert("Erro ao exportar: " + err.message);
  }
}

async function carregarListasExistentes() {
  try {
    const res = await fetch('/modulos/pncp/api/listas_existentes.php');
    if (!res.ok) throw new Error("HTTP " + res.status);
    const j = await res.json();
    if (!j.ok) throw new Error(j.msg || "Falha ao carregar listas.");

    const sel = document.getElementById('listaExistente');
    sel.innerHTML = '<option value="">-- Nova Lista --</option>';
    j.listas.forEach(l => {
      const opt = document.createElement('option');
      opt.value = l.id;
      opt.textContent = `#${l.id} - ${l.nome}`;
      sel.appendChild(opt);
    });
  } catch (err) {
    console.error("❌ Erro ao carregar listas existentes:", err);
  }
}

// chama assim que a tela carrega
carregarListasExistentes();
