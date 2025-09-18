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

  let currentPage = 1;
  let lastQuery = null;
  let totalFound = 0;
  let pageSize = 50;
  let lastData = [];

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

    countInfo.textContent = `Exibindo ${lastData.length} de ${totalFound} registros`;
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

      tr.innerHTML = `
        <td><b>${mark(obj, palavra)}</b><div class="muted">#PNCP: ${row.numeroControlePNCP}</div></td>
        <td>${mark(org, palavra)}<div class="muted">${uf}</div></td>
        <td><span class="pill">${st||'-'}</span><div class="muted">${pub}</div></td>
        <td>
          <button class="btn btn-mini" data-id="${row.numeroControlePNCP}">Ver itens</button>
        </td>
      `;
      tblProcBody.appendChild(tr);
    });

    tblProcBody.querySelectorAll('button[data-id]').forEach(btn=>{
      btn.addEventListener('click', ()=> carregarItens(btn.getAttribute('data-id')));
    });

    // Export links (PDF/Word/Excel) – ajuste para seus endpoints de exportação
    const baseExp = '/modulos/pncp/exportar.php?'+params.toString();
    document.getElementById('expPdf').href = baseExp + '&tipo=pdf';
    document.getElementById('expWord').href = baseExp + '&tipo=docx';
    document.getElementById('expExcel').href = baseExp + '&tipo=xlsx';
  }

  async function carregarItens(numeroControlePNCP){
    tblItensBody.innerHTML = '<tr><td colspan="4">Carregando itens...</td></tr>';
    itensAviso.style.display = 'none';
    try{
      const r = await fetch('api/itens_cache.php?numeroControlePNCP='+encodeURIComponent(numeroControlePNCP));
      const j = await r.json();
      if(!j.ok) throw new Error(j.msg || 'Falha ao obter itens.');
      const itens = j.itens || [];
      if(!Array.isArray(itens) || itens.length===0){
        tblItensBody.innerHTML = '<tr><td colspan="4">Sem itens para este processo.</td></tr>';
        return;
      }
      tblItensBody.innerHTML = '';
      itens.forEach(it=>{
        const v = it.valorUnit != null
          ? 'R$ ' + Number(it.valorUnit).toLocaleString('pt-BR', {minimumFractionDigits:2})
          : (it.valorUnitarioHomologado!=null || it.valorUnitarioEstimado!=null
              ? 'R$ ' + Number(it.valorUnitarioHomologado ?? it.valorUnitarioEstimado).toLocaleString('pt-BR',{minimumFractionDigits:2})
              : 'SIGILOSO');
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${it.numeroItem ?? '-'}</td>
          <td>${it.descricao || '-'}</td>
          <td>${it.quantidade ?? '-'}</td>
          <td>${v}</td>
        `;
        tblItensBody.appendChild(tr);
      });
    }catch(err){
      itensAviso.innerHTML = 'Não foi possível carregar itens do cache.';
      itensAviso.style.display = 'block';
      tblItensBody.innerHTML = '';
    }
  }

  // primeira carga
  executeQuery();
})();
