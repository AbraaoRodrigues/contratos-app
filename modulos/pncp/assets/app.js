// === Inicializa datas (últimos 30 dias por padrão) ===
(function initDates(){
  const end = new Date();
  const start = new Date(); start.setDate(end.getDate() - 30);
  document.querySelector('[name="dataInicial"]').value = start.toISOString().slice(0,10);
  document.querySelector('[name="dataFinal"]').value   = end.toISOString().slice(0,10);
})();

// === Elementos globais ===
const tblProcBody = document.querySelector('#tblProcessos tbody');
const tblItensBody= document.querySelector('#tblItens tbody');
const tblSelBody  = document.querySelector('#tblSelecionados tbody');
const itensAviso  = document.getElementById('itensAviso');
const alertBox    = document.getElementById('alert');
const countInfo   = document.getElementById('countInfo');
const pageInfo    = document.getElementById('pageInfo');

let currentPage   = 1;
const cacheItens  = {};   // { numeroControlePNCP: [ {descricao,...} ] }
const selectedItems = []; // itens que o usuário escolheu

// === Funções auxiliares ===
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

function agruparStatus(d){
  const nome = (d.statusNome || d.situacaoCompraNome || '').toLowerCase();
  const ab = d.dataAberturaProposta ? new Date(d.dataAberturaProposta) : null;
  const en = d.dataEncerramentoProposta ? new Date(d.dataEncerramentoProposta) : null;
  let agrup = d.statusAgrupado || '';
  if (!agrup && ab && en) {
    const now = new Date();
    if (now >= ab && now <= en) agrup = 'RECEBENDO';
    else if (now > en) agrup = 'JULGAMENTO';
  }
  if (!agrup && /(encerr|homolog|adjudic|conclu|finaliz)/.test(nome)) agrup = 'ENCERRADA';
  if (!agrup && /julg/.test(nome)) agrup = 'JULGAMENTO';
  if (!agrup && /(abert|receb)/.test(nome)) agrup = 'RECEBENDO';
  return agrup;
}

// === Prefetch de itens para busca refinada ===
async function prefetchItensParaBusca(processos, termos) {
  const ids = processos.map(p => p.numeroControlePNCP).filter(Boolean);
  const pendentes = ids.filter(id => !cacheItens[id]);
  if (pendentes.length === 0) return;

  const maxConc = 6;
  let done = 0;
  const total = pendentes.length;

  for (let i = 0; i < pendentes.length; i += maxConc) {
    const slice = pendentes.slice(i, i + maxConc);

    await Promise.all(slice.map(async (id) => {
      try {
        const r = await fetch('api/api_pncp.php?acao=itens&numeroControlePNCP=' + encodeURIComponent(id));

        let j;
        try {
          j = await r.json();
        } catch (e) {
          const txt = await r.text().catch(()=> '');
          console.warn(`Itens de ${id} não retornaram JSON válido. Resposta:`, txt.slice(0,200));
          j = [];
        }

        if (Array.isArray(j)) {
          cacheItens[id] = j;
        } else {
          cacheItens[id] = [];
        }
      } catch (err) {
        console.error("Erro ao buscar itens do processo", id, err);
        cacheItens[id] = [];
      } finally {
        done++;
        if (pageInfo) pageInfo.textContent = `Carregando itens para busca: ${done}/${total}`;
      }
    }));
  }

  if (pageInfo) pageInfo.textContent = '';
}

// === Submissão do formulário ===
document.getElementById('formConsulta').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  await executeQuery(fd);
});

// === Execução da consulta principal ===
async function executeQuery(fd){
  clearAlert();
  tblProcBody.innerHTML = '<tr><td colspan="4">Carregando...</td></tr>';
  tblItensBody.innerHTML = '';
  itensAviso.style.display = 'none';
  countInfo.textContent = '';

  const qs = new URLSearchParams(fd).toString();

  let payload;
  try {
    const r = await fetch('api/api_pncp.php?acao=listar&' + qs);
    payload = await r.json();
  } catch (e) {
    console.error('Resposta não-JSON:', e);
    showAlert('A API retornou uma resposta inválida (não-JSON).');
    tblProcBody.innerHTML = '<tr><td colspan="4">Erro de resposta da API.</td></tr>';
    return;
  }

  if (pageInfo) pageInfo.textContent = `Página ${payload.pagina || currentPage}`;
  if (payload.debugUrl) console.log('URL chamada:', payload.debugUrl);

  if (payload.error) {
  // Mostra erro mais claro
  const msg = payload.error.message || 'Erro na consulta.';
  const hint = payload.error.hint ? ` (${payload.error.hint})` : '';
  showAlert(`⚠️ ${msg}${hint}`);

  tblProcBody.innerHTML = `
    <tr><td colspan="4" style="color:red">
      Erro da API PNCP: ${msg}${hint ? '<br><small>'+hint+'</small>' : ''}
    </td></tr>`;
  countInfo.textContent = `(Erro da API)`;
  return;
}

  const dados = payload.data || [];
  const totalApi = payload.totalRegistros || (dados.length || 0);
  const pagina = payload.pagina || 1;
  const tamanhoPagina = payload.tamanhoPagina || dados.length;

  const enriquecidos = dados.map(d => {
  const ab = d.dataAberturaProposta ? new Date(d.dataAberturaProposta) : null;
  const en = d.dataEncerramentoProposta ? new Date(d.dataEncerramentoProposta) : null;
  let agrup = d.statusAgrupado || '';

  if (!agrup && ab && en) {
    const now = new Date();
    if (now >= ab && now <= en) agrup = 'RECEBENDO';
    else if (now > en) agrup = 'JULGAMENTO';
  }
  if (!agrup && /(encerr|homolog|adjudic|conclu|finaliz)/i.test(d.situacaoCompraNome || '')) agrup = 'ENCERRADA';
  if (!agrup && /julg/i.test(d.situacaoCompraNome || '')) agrup = 'JULGAMENTO';
  if (!agrup && /(abert|receb)/i.test(d.situacaoCompraNome || '')) agrup = 'RECEBENDO';

  return {
    ...d,
    statusAgrupado: agrup,
    _obj: d.objetoCompra
        || d.objeto
        || d.descricaoObjeto
        || d.informacaoComplementar
        || '(sem descrição)',
    _orgao: (d.orgaoEntidade && (d.orgaoEntidade.razaoSocial || d.orgaoEntidade.razao_social))
        || d.orgao
        || '(órgão não informado)',
    _uf: (d.unidadeOrgao && (d.unidadeOrgao.ufSigla || d.unidadeOrgao.ufNome))
        || d.uf
        || '',
    _statusNome: d.statusNome || d.situacaoCompraNome || ''
  };
});

  // ===== Filtros locais =====
  const palavra = normalize(fd.get('palavra')||'');
  const termos = palavra.split(/\s+/).filter(Boolean);
  const regexes = termos.map(t => new RegExp(t, 'i'));
  const statusLocal = fd.get('statusLocal') || '';
  const relaxar = !!fd.get('relaxarStatus');
  const buscarItens = !!fd.get('buscarItens');

  if (buscarItens && termos.length > 0) {
    if (pageInfo) pageInfo.textContent = 'Carregando itens para busca...';
    await prefetchItensParaBusca(enriquecidos, termos);
  }

const byWord = enriquecidos.filter(r => {
  if (!termos.length) return true;

  // concatena tudo em uma string pesquisável
  let texto = normalize(
    r.numeroControlePNCP + " " +        // ✅ número do processo
    r._obj + " " +                      // objeto
    r._orgao + " " +                    // órgão
    r._statusNome                       // status
  );

  // se temos itens em cache, adiciona descrições
  if (cacheItens[r.numeroControlePNCP]) {
    texto += " " + cacheItens[r.numeroControlePNCP]
      .map(it => normalize(it.descricao))
      .join(" ");
  }

  // procura se todos os termos aparecem em algum lugar
  return regexes.every(rx => rx.test(texto));
});

  const aposPalavra = byWord.length;

  let resultado = byWord.filter(r => !statusLocal || r.statusAgrupado === statusLocal);

  if (resultado.length === 0 && aposPalavra > 0) {
    if (statusLocal && relaxar) {
      resultado = byWord;
      showAlert(`Nenhum registro combinou com o status "${statusLocal}". Exibindo resultados apenas pela palavra-chave/UF.`);
    } else {
      showAlert('Sua palavra-chave não foi encontrada no objeto principal, mas apareceu em outros campos/itens.');
    }
  }

  if (resultado.length === 0 && totalApi > 0) {
    resultado = enriquecidos;
    showAlert(`Nenhum registro combinou com seus filtros. Exibindo todos os ${totalApi} registros retornados pela API.`);
  }

  if (resultado.length === 0) {
    tblProcBody.innerHTML = '<tr><td colspan="4">Sem resultados.</td></tr>';
    countInfo.textContent = `(API: ${totalApi} | Após filtros: 0)`;
    return;
  }

  countInfo.textContent = `(Exibidos: ${resultado.length} | Página ${pagina}, mostrando até ${tamanhoPagina} de ${totalApi} registros encontrados)`;

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

  tblProcBody.querySelectorAll('button[data-id]').forEach(btn=>{
    btn.addEventListener('click', () => carregarItens(btn));
  });
}

// === Carregar itens de um processo (quando clica em "Ver itens") ===
async function carregarItens(btn){
  tblItensBody.innerHTML = '<tr><td colspan="4">Carregando itens...</td></tr>';
  itensAviso.style.display = 'none';
  const id   = btn.getAttribute('data-id');
  const link = btn.getAttribute('data-link');

  let j;
  try {
    const r = await fetch('api/api_pncp.php?acao=itens&numeroControlePNCP='+encodeURIComponent(id));
    j = await r.json();
  } catch (e) {
    const txt = await r.text().catch(()=> '');
    console.error('Resposta não-JSON (itens):', txt);
    showAlert('Resposta inesperada ao carregar itens.');
    tblItensBody.innerHTML = '';
    return;
  }

  if (Array.isArray(j)) cacheItens[id] = j;

  if (j.error) {
    itensAviso.innerHTML = `Não foi possível obter itens via API sem token.
      Abra o processo: <a href="${link}" target="_blank" rel="noopener">ver no PNCP</a>.`;
    itensAviso.style.display = 'block';
    tblItensBody.innerHTML = '';
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
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${it.descricao||'-'}</td>
        <td>${it.quantidade||'-'}</td>
        <td>${it.valorUnitarioEstimado!=null ? 'R$ '+Number(it.valorUnitarioEstimado).toLocaleString('pt-BR') : '-'}</td>
        <td class="muted">${id}</td>
      `;
      tblSelBody.appendChild(row);
      selectedItems.push({
        descricao: it.descricao || '',
        quantidade: it.quantidade ?? null,
        valorUnitarioEstimado: it.valorUnitarioEstimado ?? null,
        origem: id
      });
    });
    tblItensBody.appendChild(tr);
  });
}
