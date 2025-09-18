<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <title>Dashboard PNCP</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-900 text-gray-100 p-6">

  <h1 class="text-2xl font-bold mb-6">📊 Dashboard PNCP</h1>

  <!-- Cards resumo -->
  <div id="cards" class="grid grid-cols-4 gap-4 mb-6">
    <div class="bg-gray-800 p-4 rounded-lg text-center">
      <div class="text-lg">✅ Sucesso</div>
      <div id="card-sucesso" class="text-2xl font-bold">0</div>
    </div>
    <div class="bg-gray-800 p-4 rounded-lg text-center">
      <div class="text-lg">⚠️ Pendentes</div>
      <div id="card-pendentes" class="text-2xl font-bold">0</div>
    </div>
    <div class="bg-gray-800 p-4 rounded-lg text-center">
      <div class="text-lg">❌ Irrecuperáveis</div>
      <div id="card-irrecuperaveis" class="text-2xl font-bold">0</div>
    </div>
    <div class="bg-gray-800 p-4 rounded-lg text-center">
      <div class="text-lg">📦 Total</div>
      <div id="card-total" class="text-2xl font-bold">0</div>
    </div>
  </div>

  <!-- Conteúdo -->
  <div class="grid grid-cols-2 gap-6">
    <div class="bg-gray-800 p-4 rounded-lg">
      <canvas id="chartFalhas"></canvas>
    </div>
    <div class="bg-gray-800 p-4 rounded-lg overflow-auto max-h-96">
      <h2 class="text-lg mb-2">Falhas por tipo</h2>
      <table class="w-full text-sm">
        <thead class="bg-gray-700 sticky top-0">
          <tr>
            <th class="px-4 py-2 text-left">Motivo</th>
            <th class="px-4 py-2 text-right">Total</th>
          </tr>
        </thead>
        <tbody id="tabelaFalhas"></tbody>
      </table>
    </div>
  </div>

  <script>
    async function carregarEstatisticas() {
      const res = await fetch("api/estatisticas.php");
      const data = await res.json();

      // Atualizar cards
      document.getElementById("card-sucesso").textContent = data.totais.corrigido || 0;
      document.getElementById("card-pendentes").textContent = data.totais.pendente || 0;
      document.getElementById("card-irrecuperaveis").textContent = data.totais.irrecuperavel || 0;
      const total = Object.values(data.totais).reduce((a, b) => a + parseInt(b), 0);
      document.getElementById("card-total").textContent = total;

      // Gráfico de falhas
      const ctx = document.getElementById("chartFalhas");
      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: data.falhas_tipos.map(t => t.motivo),
          datasets: [{
            data: data.falhas_tipos.map(t => t.total),
            backgroundColor: "#f87171"
          }]
        },
        options: {
          indexAxis: 'y', // barras horizontais
          plugins: {
            legend: {
              display: false
            }
          }
        }
      });

      // Tabela
      const tbody = document.getElementById("tabelaFalhas");
      tbody.innerHTML = "";
      data.falhas_tipos.forEach(t => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
      <td class="px-4 py-2">${t.motivo}</td>
      <td class="px-4 py-2 text-right">${t.total}</td>
    `;
        tbody.appendChild(tr);
      });
    }

    carregarEstatisticas();
  </script>
</body>

</html>
