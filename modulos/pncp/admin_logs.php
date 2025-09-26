<?php
// (opcional) se quiser exigir login aqui também, descomente:
// session_start();
// if (!isset($_SESSION['usuario_id'])) { header('Location: /templates/login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <title>Terminal PNCP — Logs</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root {
      color-scheme: dark;
    }

    body {
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
      background: #0b0f14;
      color: #9efc8f;
      margin: 0;
      display: flex;
      flex-direction: column;
      height: 100vh;
    }

    header {
      display: flex;
      gap: 8px;
      align-items: center;
      padding: 10px 12px;
      background: #0f1720;
      border-bottom: 1px solid #203040;
      position: sticky;
      top: 0;
      z-index: 5;
    }

    header .title {
      font-weight: 700;
      margin-right: auto;
      color: #c3f3b7;
    }

    header .muted {
      color: #8fb3a0;
      font-size: 12px;
    }

    button,
    a.btn {
      background: #142533;
      color: #bfffd0;
      border: 1px solid #2a4456;
      padding: 6px 10px;
      border-radius: 8px;
      cursor: pointer;
      text-decoration: none;
    }

    button:hover,
    a.btn:hover {
      filter: brightness(1.1);
    }

    #terminal {
      flex: 1;
      overflow: auto;
      padding: 14px;
      white-space: pre-wrap;
      line-height: 1.4;
    }

    .bar {
      display: flex;
      gap: 8px;
      align-items: center;
    }

    .right {
      margin-left: auto;
      display: flex;
      gap: 8px;
      align-items: center;
    }

    .pill {
      padding: 3px 8px;
      border-radius: 999px;
      border: 1px solid #274b4b;
      color: #a6f2e2;
    }

    .ok {
      color: #9efc8f;
    }

    .warn {
      color: #ffd479;
    }

    .err {
      color: #ff9aa2;
    }
  </style>
</head>

<body>
  <header>
    <div class="title">Terminal PNCP — Logs</div>
    <div class="toolbar">
      <div class="controls">
        <input type="file" id="logFileInput" accept=".log,.txt">
        <button id="btnRefresh">🔄 Atualizar</button>
        <button id="btnToggleAuto">Pausar auto-atualização</button>
        <button id="btnToggleScroll">Pausar auto-scroll</button>
        <button id="btnClear">🧹 Descartar log atual</button>
      </div>
      <div class="right">
        <span class="pill">Atualiza a cada <span id="freq">3s</span></span>
        <span class="pill" id="runtime">Rodando há 0s</span>
      </div>
    </div>

  </header>

  <div id="terminal">Carregando logs...</div>

  <script>
    let LOG_URL = 'ver_log.php?tailKb=256';
    const term = document.getElementById('terminal');
    const btnRefresh = document.getElementById('btnRefresh');
    const btnToggleAuto = document.getElementById('btnToggleAuto');
    const btnToggleScroll = document.getElementById('btnToggleScroll');
    const btnClear = document.getElementById('btnClear');
    const fileInput = document.getElementById('logFileInput');

    let autoRefresh = true;
    let autoScroll = true;
    let logTimer = null;
    const intervalMs = 3000;

    async function carregarLogs() {
      try {
        const res = await fetch(LOG_URL, {
          cache: 'no-store'
        });
        const texto = await res.text();
        term.textContent = texto || 'Nenhum log encontrado.';
        if (autoScroll) term.scrollTop = term.scrollHeight;

        // Detecta inícios
        if (texto.match(/🚀 .*iniciada/) ||
          texto.includes("🔄 [FALHAS] Iniciando reprocessamento") ||
          texto.includes("🔄 [PROCESSOS] Iniciando reprocessamento")) {
          if (!runtimeTimer) iniciarRuntime();
        }

        // Detecta finais
        if (texto.includes("🏁 Finalizado") ||
          texto.includes("🏁 [FALHAS] Finalizado reprocessamento.") ||
          texto.includes("🏁 [PROCESSOS] Finalizado reprocessamento.")) {
          pararRuntime();
        }
      } catch (e) {
        term.textContent = 'Erro ao carregar logs: ' + e;
      }
    }

    // Inicia atualização automática
    function start() {
      if (logTimer) clearInterval(logTimer);
      logTimer = setInterval(() => {
        if (autoRefresh) carregarLogs();
      }, intervalMs);
      carregarLogs();
    }

    btnRefresh.addEventListener('click', carregarLogs);
    btnToggleAuto.addEventListener('click', () => {
      autoRefresh = !autoRefresh;
      btnToggleAuto.textContent = autoRefresh ? 'Pausar auto-atualização' : 'Retomar auto-atualização';
      if (autoRefresh) carregarLogs();
    });
    btnToggleScroll.addEventListener('click', () => {
      autoScroll = !autoScroll;
      btnToggleScroll.textContent = autoScroll ? 'Pausar auto-scroll' : 'Retomar auto-scroll';
      if (autoScroll) term.scrollTop = term.scrollHeight;
    });

    // Clear apenas descarta
    btnClear.addEventListener('click', async () => {
      if (!confirm('Descartar log atual da visualização?')) return;
      try {
        const res = await fetch(LOG_URL + '&clear=1', {
          method: 'POST',
          cache: 'no-store'
        });
        const txt = await res.text();
        if (txt.trim() === 'DISCARDED') {
          term.textContent = '🧹 Log descartado da visualização.\n';
          resetarRuntime();
        } else {
          alert('Não foi possível descartar o log.');
        }
      } catch (e) {
        console.error(e);
        alert('Erro ao descartar log.');
      }
    });

    // Input file → escolhe outro log
    fileInput.addEventListener('change', () => {
      if (fileInput.files.length === 0) return;
      const nome = fileInput.files[0].name;
      LOG_URL = 'ver_log.php?tailKb=256&file=' + encodeURIComponent(nome);
      term.textContent = `📂 Log selecionado: ${nome}\nCarregando...\n`;
      resetarRuntime();
      carregarLogs();
    });

    // Cronômetro
    const runtimeEl = document.getElementById('runtime');
    let startTime = null;
    let runtimeTimer = null;
    let runtimeRunning = false;

    function atualizarRuntime() {
      if (startTime === null) return;
      const diff = Math.floor((Date.now() - startTime) / 1000);
      const horas = Math.floor(diff / 3600);
      const minutos = Math.floor((diff % 3600) / 60);
      const segundos = diff % 60;
      let txt = "";
      if (horas > 0) txt += horas + "h ";
      if (minutos > 0) txt += minutos + "m ";
      txt += segundos + "s";
      runtimeEl.textContent = "Rodando há " + txt.trim();
    }

    function iniciarRuntime() {
      if (runtimeRunning) return;
      if (runtimeTimer) clearInterval(runtimeTimer);
      startTime = Date.now();
      runtimeTimer = setInterval(atualizarRuntime, 1000);
      atualizarRuntime();
      runtimeRunning = true;
    }

    function pararRuntime() {
      if (runtimeTimer) {
        clearInterval(runtimeTimer);
        runtimeTimer = null;
        runtimeEl.textContent += " (finalizado)";
        runtimeRunning = false;
      }
    }

    function resetarRuntime() {
      if (runtimeTimer) clearInterval(runtimeTimer);
      runtimeTimer = null;
      startTime = null;
      runtimeEl.textContent = "Rodando há 0s";
      runtimeRunning = false;
    }

    start();
  </script>

</body>

</html>
