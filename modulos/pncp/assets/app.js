// Datas padrão (últimos 30 dias)
(function initDates(){
  const end = new Date();
  const start = new Date();
  start.setDate(end.getDate() - 30);
  document.querySelector('[name="dataInicial"]').value = start.toISOString().slice(0,10);
  document.querySelector('[name="dataFinal"]').value   = end.toISOString().slice(0,10);
})();

const tblProcBody = document.querySelector('#tblProcessos tbody');
const tblItensBody= document.querySelector('#tblItens tbody');
const alertBox    = document.getElementById('alert');
const countInfo   = document.getElementById('countInfo');
const form        = document.getElementById('formConsulta');
const btnPrev     = document.getElementById('btnPrev');
const btnNext     = document.getElementById('btnNext');
const pageInfo    = document.getElementById('pageInfo');
const tblSelBody  = document.querySelector('#tblSelecionados tbody'); // tabela de itens selecionados
const tblFinalBody = document.querySelector('#tblFinal tbody');

let currentPage = 1;
let pageSize    = 50;
// Array global onde vamos armazenar todos os itens selecionados
const selectedItems = [];

function showAlert(msg){ alertBox.textContent = msg; alertBox.style.display = 'block'; }
function clearAlert(){ alertBox.style.display = 'none'; alertBox.textContent = ''; }
function normalize(s){ return (s||'').normalize('NFD').replace(/\p{Diacritic}/gu,'').toLowerCase(); }

function agruparStatus(row){
  const nome = (row.statusNome || row.situacaoCompraNome || '').toLowerCase();
  const ab = row.dataAberturaProposta ? new Date(row.dataAberturaProposta) : null;
  const en = row.dataEncerramentoProposta ? new Date(row.dataEncerramentoProposta) : null;
  const now = new Date();
  if (ab && en) {
    if (now >= ab && now <= en) return 'RECEBENDO';
    if (now > en) return 'JULGAMENTO';
  }
  if (/(encerr|homolog|adjudic|conclu|finaliz)/.test(nome)) return 'ENCERRADA';
  if (/julg/.test(nome)) return 'JULGAMENTO';
  if (/(abert|receb)/.test(nome)) return 'RECEBENDO';
  return '';
}

if (btnPrev) btnPrev.addEventListener('click', ()=>{ if (currentPage>1){ currentPage--; submitWithPage(); }});
if (btnNext) btnNext.addEventListener('click', ()=>{ currentPage++; submitWithPage(); });

function submitWithPage(){
  const fd = new FormData(form);
  fd.set('pagina', currentPage);
  fd.set('tamanhoPagina', pageSize);
  executeQuery(fd);
}

if (form) {
  form.addEventListener('submit', (e)=>{
    e.preventDefault();
    currentPage = 1;
    const fd = new FormData(form);
    fd.set('pagina', currentPage);
    fd.set('tamanhoPagina', pageSize);
    executeQuery(fd);
  });
}

// ——— Core com try/catch para respostas HTML ———
async function executeQuery(fd){
  clearAlert();
  tblProcBody.innerHTML = '<tr><td colspan="4">Carregando...</td></tr>';
  tblItensBody.innerHTML = '';
  countInfo.textContent = '';

  const qs = new URLSearchParams(fd).toString();
  const res = await fetch('api/api_pncp.php?acao=listar&'+qs);

  let payload;
  try {
    payload = await res.json();
  } catch (err) {
    const txt = await res.text();
    console.error('Resposta não-JSON:', txt);
    showAlert('O servidor retornou uma resposta inesperada. Veja o console (Network/Response).');
    tblProcBody.innerHTML = '<tr><td colspan="4">—</td></tr>';
    return;
  }

  if (pageInfo) pageInfo.textContent = `Página ${payload.pagina || currentPage}`;
  if (payload.debugUrl) console.log('URL chamada:', payload.debugUrl);

  if (payload.error) {
    showAlert(payload.error.hint || payload.error.message || 'Erro na consulta.');
    tblProcBody.innerHTML = '<tr><td colspan="4">—</td></tr>';
    return;
  }

  const dados = Array.isArray(payload.data) ? payload.data : [];
  const totalApi = dados.length;

  // Enriquecer
  const enriquecidos = dados.map(d => ({
    ...d,
    statusAgrupado: d.statusAgrupado || agruparStatus(d),
    _obj: d.objeto || d.objetoCompra || d.descricaoObjeto || '(sem descrição)',
    _orgao: d.orgao || (d.orgaoEntidade && (d.orgaoEntidade.razaoSocial || d.orgaoEntidade.razao_social)) || '',
    _uf: d.uf || (d.unidadeOrgao && (d.unidadeOrgao.ufSigla || d.unidadeOrgao.ufNome)) || '',
    _statusNome: d.statusNome || d.situacaoCompraNome || ''
  }));

  // Filtros locais
  const palavra = normalize(form.querySelector('[name="palavra"]').value || '');
  const statusLocal = form.querySelector('[name="statusLocal"]').value || '';
  const relaxar = !!form.querySelector('[name="relaxarStatus"]')?.checked;

  const byWord = enriquecidos.filter(r => !palavra || normalize(r._obj).includes(palavra));
  const aposPalavra = byWord.length;

  let resultado = byWord.filter(r => !statusLocal || r.statusAgrupado === statusLocal);
  if (resultado.length === 0 && aposPalavra > 0 && statusLocal && relaxar) {
    resultado = byWord;
    showAlert('Nenhum registro combinou com o status escolhido. Exibindo resultados apenas pela palavra-chave/UF.');
  }
  if (resultado.length === 0 && totalApi > 0) {
    resultado = enriquecidos;
    showAlert('Seus filtros locais zeraram a lista. Exibindo todos os registros retornados pela API.');
  }

  if (resultado.length === 0) {
    tblProcBody.innerHTML = '<tr><td colspan="4">Sem resultados.</td></tr>';
    countInfo.textContent = `(API: ${totalApi} | Após filtros: 0)`;
    return;
  }

  countInfo.textContent = `(API: ${totalApi} | Exibidos: ${resultado.length})`;

  // Render
  tblProcBody.innerHTML = '';
  resultado.forEach(p=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><b>${p._obj}</b><div class="muted">#PNCP: ${p.numeroControlePNCP||''}</div></td>
      <td>${p._orgao||'-'}<div class="muted">${p._uf||''}</div></td>
      <td><span class="pill">${p._statusNome||'-'}</span><div class="muted">${p.dataPublicacao||''}</div></td>
      <td><button class="btn" data-id="${p.numeroControlePNCP||''}" data-link="${p.linkPublico||''}">Ver itens</button></td>
    `;
    tblProcBody.appendChild(tr);
  });

  // Bind itens
  tblProcBody.querySelectorAll('button[data-id]').forEach(btn=>{
    btn.addEventListener('click', () => carregarItens(btn));
  });
}

async function carregarItens(btn){
  tblItensBody.innerHTML = '<tr><td colspan="4">Carregando itens...</td></tr>';

  const id   = btn.getAttribute('data-id');
  const link = btn.getAttribute('data-link');

  const r = await fetch('api/api_pncp.php?acao=itens&numeroControlePNCP='+encodeURIComponent(id));

  let j;
  try { j = await r.json(); }
  catch (e) {
    const txt = await r.text();
    console.error('Resposta não-JSON (itens):', txt);
    showAlert('Resposta inesperada nos itens. Veja console.');
    tblItensBody.innerHTML = '';
    return;
  }

  if (j.error) {
    tblItensBody.innerHTML = '';
    alert(`Não foi possível obter itens via API sem token.\nAbra o processo: ${link}`);
    return;
  }

  if (!Array.isArray(j) || j.length === 0) {
    tblItensBody.innerHTML = '<tr><td colspan="4">Processo sem itens disponíveis via API.</td></tr>';
    return;
  }

  tblItensBody.innerHTML = '';
  j.forEach(it=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${it.descricao||'-'}</td>
      <td>${it.quantidade||'-'}</td>
      <td>${it.valorUnitarioEstimado!=null ? 'R$ '+Number(it.valorUnitarioEstimado).toLocaleString('pt-BR') : '-'}</td>
      <td><button class="btn" data-add>Selecionar</button></td>
    `;

    tr.querySelector('[data-add]').addEventListener('click', ()=>{
      // Adiciona à tabela "Itens Selecionados"
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${it.descricao||'-'}</td>
        <td>${it.quantidade||'-'}</td>
        <td>${it.valorUnitarioEstimado!=null ? 'R$ '+Number(it.valorUnitarioEstimado).toLocaleString('pt-BR') : '-'}</td>
        <td class="muted">${id}</td>
      `;
      tblSelBody.appendChild(row);

      // Armazena no array para consolidação
      selectedItems.push({
        descricao: it.descricao || '',
        quantidade: it.quantidade || null,
        valorUnitarioEstimado: it.valorUnitarioEstimado ?? null,
        origem: id
      });

      console.log("Item adicionado:", it.descricao, "Origem:", id);
    });

    tblItensBody.appendChild(tr);
  });
}

function gerarTabelaFinal(){
  tblFinalBody.innerHTML = '';

  if (selectedItems.length === 0) {
    tblFinalBody.innerHTML = '<tr><td colspan="5">Nenhum item selecionado ainda.</td></tr>';
    return;
  }

  // Agrupar por descrição
  const grupos = {};
  selectedItems.forEach(it=>{
    const chave = (it.descricao || '').trim().toLowerCase();
    if (!grupos[chave]) {
      grupos[chave] = { descricao: it.descricao, valores: [], origens: [] };
    }
    if (it.valorUnitarioEstimado) grupos[chave].valores.push(parseFloat(it.valorUnitarioEstimado));
    if (it.origem) grupos[chave].origens.push(it.origem);
  });

  Object.values(grupos).forEach(g=>{
    const media = g.valores.length > 0 ? (g.valores.reduce((a,b)=>a+b,0)/g.valores.length) : 0;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${g.descricao}</td>
      <td data-valor="${media}">R$ ${media.toLocaleString('pt-BR',{minimumFractionDigits:2})}</td>
      <td><input type="number" min="1" value="1" style="width:70px"></td>
      <td class="valor-total">R$ ${media.toLocaleString('pt-BR',{minimumFractionDigits:2})}</td>
      <td>${[...new Set(g.origens)].join(', ')}</td>
    `;
    const qtdInput = tr.querySelector('input');
    qtdInput.addEventListener('input', ()=>{
      const qtd = parseFloat(qtdInput.value) || 0;
      const val = parseFloat(media) || 0;
      tr.querySelector('.valor-total').textContent = 'R$ '+(qtd*val).toLocaleString('pt-BR',{minimumFractionDigits:2});
    });
    tblFinalBody.appendChild(tr);
  });
}

document.getElementById('btnGerarFinal').addEventListener('click', gerarTabelaFinal);

//Salvar lista
document.getElementById('btnSalvarPesquisa').addEventListener('click', async ()=>{
  const nome = (document.getElementById('nomePesquisa').value || '').trim();
  if (!nome) { alert('Informe um nome para a pesquisa.'); return; }

  const rows = Array.from(tblFinalBody.querySelectorAll('tr'));
  if (rows.length === 0) { alert('Gere a tabela final primeiro.'); return; }

  const itens = rows.map(tr=>{
  const cols = tr.querySelectorAll('td');
  const qtdInput = cols[2].querySelector('input');
  const qtd = parseFloat(qtdInput?.value || cols[2].textContent) || 0;
  const valMedio = parseFloat(cols[1].dataset.valor) || 0;
  const valTotal = qtd * valMedio;
  return {
    descricao: cols[0].textContent.trim(),
    valor_medio: valMedio,
    quantidade: qtd,
    valor_total: valTotal,
    referencias: cols[4].textContent.trim()
  };
});

console.log("Enviando pesquisa:", { nome, itens });

  const r = await fetch('api/salvar_pesquisa.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ nome, itens })
  });
  const j = await r.json();
  if (j.ok) {
    document.getElementById('msgPesquisa').textContent = 'Pesquisa salva com ID #'+j.lista_id;
  } else {
    alert('Erro ao salvar: '+(j.msg || 'desconhecido'));
  }
});


// ========= Exportações =========

// Gera HTML da tabela final atual
function getFinalTableHTML(){
  const rows = Array.from(tblFinalBody.querySelectorAll('tr'));
  if (rows.length === 0) return null;

  let html = '<h3>Tabela Final de Referência de Preços</h3>';
  html += '<table border="1" cellspacing="0" cellpadding="6"><thead><tr>';
  html += '<th>Descrição</th><th>Valor Médio</th><th>Quantidade</th><th>Referências</th>';
  html += '</tr></thead><tbody>';

  rows.forEach(tr=>{
    const cols = tr.querySelectorAll('td');
    html += '<tr>';
    cols.forEach(td=>{
      // Se for input (quantidade), pega o valor
      const input = td.querySelector('input');
      html += '<td>'+(input ? input.value : td.textContent)+'</td>';
    });
    html += '</tr>';
  });
  html += '</tbody></table>';

  return html;
}

// Exporta via backend (PDF, Word, Excel)
async function exportar(formato){
  const rows = Array.from(tblFinalBody.querySelectorAll('tr'));
if (rows.length === 0) { alert('Gere a tabela final primeiro.'); return; }

const itens = rows.map(tr=>{
  const cols = tr.querySelectorAll('td');
  const qtdInput = cols[2].querySelector('input');
  const qtd = parseFloat(qtdInput?.value || cols[2].textContent) || 0;
  const valMedio = parseFloat(cols[1].dataset.valor) || 0;
  const valTotal = qtd * valMedio;
  return {
    descricao: cols[0].textContent.trim(),
    valor_medio: valMedio,
    quantidade: qtd,
    valor_total: valTotal,
    referencias: cols[4].textContent.trim()
  };
});

  const res = await fetch('api/exportar.php?formato='+formato, {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ itens })
  });

  if (!res.ok) { alert('Erro na exportação'); return; }

  // Para download automático
  const blob = await res.blob();
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'tabela_final.'+(formato==='pdf'?'pdf':(formato==='word'?'docx':'xlsx'));
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  window.URL.revokeObjectURL(url);
}

document.getElementById('btnExportPdf').addEventListener('click', ()=>exportar('pdf'));
document.getElementById('btnExportWord').addEventListener('click', ()=>exportar('word'));
document.getElementById('btnExportExcel').addEventListener('click', ()=>exportar('excel'));

