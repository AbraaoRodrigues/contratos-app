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
    <div class="bar">
      <button id="btnRefresh">Atualizar agora</button>
      <button id="btnToggleAuto">Pausar auto-atualização</button>
      <button id="btnToggleScroll">Pausar auto-scroll</button>
      <a class="btn" href="ver_log.php?download=1" target="_blank" rel="noopener">Baixar log</a>
      <button id="btnClear" class="btn" style="border-color:#663;">Limpar log</button>
    </div>
    <div class="right">
      <span class="pill">Atualiza a cada <span id="freq">3s</span></span>
      <span class="pill" id="runtime">Rodando há 0s</span>
    </div>

  </header>

  <div id="terminal">Carregando logs...</div>

  <script>
    const LOG_URL = 'ver_log.php?tailKb=256'; // pega os últimos 256 KB p/ não pesar
    const term = document.getElementById('terminal');
    const btnRefresh = document.getElementById('btnRefresh');
    const btnToggleAuto = document.getElementById('btnToggleAuto');
    const btnToggleScroll = document.getElementById('btnToggleScroll');

    let autoRefresh = true;
    let autoScroll = true;
    let timer = null;
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
        if (
          texto.includes("🔄 Iniciando sincronização FULL PNCP") ||
          texto.includes("🔄 [FALHAS] Iniciando reprocessamento") ||
          texto.includes("🔄 [PROCESSOS] Iniciando reprocessamento")
        ) {
          iniciarRuntime();
        }

        // Detecta finais
        if (
          texto.includes("✅ Finalizado") ||
          texto.includes("🏁 [FALHAS] Finalizado reprocessamento.") ||
          texto.includes("🏁 [PROCESSOS] Finalizado reprocessamento.")
        ) {
          pararRuntime();
        }

      } catch (e) {
        term.textContent = 'Erro ao carregar logs: ' + e;
      }
    }

    //alerta de cronômetro rodando
    let runtimeRunning = false;


    // Timer dos logs
    let logTimer = null;

    // Inicia atualização automática dos logs
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

    // Cronômetro de tempo de execução
    const runtimeEl = document.getElementById('runtime');
    let startTime = null;
    let runtimeTimer = null;

    function atualizarRuntime() {
      if (startTime === null) return; // parado, não faz nada
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
      if (runtimeRunning) return; // já rodando, não reinicia
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

    const btnClear = document.getElementById('btnClear');

    btnClear.addEventListener('click', async () => {
      if (!confirm('Limpar arquivo de log?')) return;
      try {
        const res = await fetch('ver_log.php?clear=1', {
          method: 'POST',
          cache: 'no-store'
        });
        const txt = await res.text();
        if (txt.trim() === 'CLEARED') {
          term.textContent = '🧹 Logs limpos.\n';
          if (autoScroll) term.scrollTop = term.scrollHeight;
          resetarRuntime(); // 🔄 reseta cronômetro
          setTimeout(carregarLogs, 300); // recarrega o conteúdo logo depois
        } else {
          alert('Não foi possível limpar o log.');
        }

      } catch (e) {
        console.error(e);
        alert('Erro ao limpar log.');
      }
    });
  </script>
</body>

</html>
