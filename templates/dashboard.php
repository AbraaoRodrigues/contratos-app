<?php
require_once 'config/db.php';
$pdo = Conexao::getInstance();

require_once './templates/includes/verifica_login.php';

function diasRestantes($data)
{
  $hoje = new DateTime();
  $fim = new DateTime($data);
  return $hoje->diff($fim)->days;
}

// Contratos
$stmt = $pdo->query("SELECT * FROM contratos ORDER BY data_fim ASC");
$contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Empenhos com contrato
$stmt = $pdo->query("SELECT e.*, c.numero AS numero_empenho FROM empenhos e JOIN contratos c ON e.contrato_id = c.id ORDER BY e.data_fim_previsto ASC");
$empenhos_com_contrato = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Empenhos sem contrato
$stmt = $pdo->query("SELECT * FROM empenhos WHERE contrato_id IS NULL OR contrato_id = 0 ORDER BY data_fim_previsto ASC");
$empenhos_sem_contrato = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($contrato['data_fim'])) {
  $dataFim = new DateTime($contrato['data_fim']);
  $diasRestantes = (int)$dataHoje->diff($dataFim)->format('%r%a');
} else {
  $diasRestantes = '-';
}
?>

<div class="container">
  <h2>Painel Administrativo</h2>

  <!-- CONTRATOS -->
  <section class="card-dashboard">
    <h3>Contratos Ativos</h3>
    <table class="tabela-usuarios">
      <thead>
        <tr>
          <th>Número</th>
          <th>Objeto</th>
          <th>Vencimento</th>
          <th>Dias Restantes</th>
          <th>Prazo para Prorrogar</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($contratos as $c):
          $dias = diasRestantes($c['data_fim']);
          $cor = $dias <= 30 ? 'vermelho' : ($dias <= 90 ? 'amarelo' : 'verde');

          // Cálculo do tempo restante para prorrogação
          $anos_maximos = isset($c['prorrogavel_max_anos']) ? (int)$c['prorrogavel_max_anos'] : 0;
          $data_inicio = !empty($c['data_inicio']) ? new DateTime($c['data_inicio']) : null;
          $hoje = new DateTime();

          $tempo_total = $data_inicio ? $data_inicio->diff($hoje)->y : 0;
          $tempo_restante = max(0, $anos_maximos - $tempo_total);
        ?>
          <tr>
            <td><?= htmlspecialchars($c['numero']) ?></td>
            <td><?= htmlspecialchars($c['objeto']) ?></td>
            <td><?= date('d/m/Y', strtotime($c['data_fim'])) ?></td>
            <td class="<?= $cor ?>"><?= $dias ?> dias</td>
            <td><?= $tempo_restante ?> anos</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <!-- EMPENHOS COM CONTRATO -->
  <section class="card-dashboard">
    <h3>Empenhos com Contrato</h3>
    <table class="tabela-usuarios">
      <thead>
        <tr>
          <th>Empenho</th>
          <th>Valor Empenhado</th>
          <th>Fim Previsto</th>
          <th>Dias Restantes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($empenhos_com_contrato as $e):
          $dias = diasRestantes($e['data_fim_previsto']);
          $cor = $dias <= 30 ? 'vermelho' : ($dias <= 90 ? 'amarelo' : 'verde');
        ?>
          <tr>
            <td><?= htmlspecialchars($e['numero_empenho']) ?></td>
            <td>R$ <?= number_format($e['valor_empenhado'], 2, ',', '.') ?></td>
            <td><?= date('d/m/Y', strtotime($e['data_fim_previsto'])) ?></td>
            <td class="<?= $cor ?>"><?= $dias ?> dias</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <!-- EMPENHOS SEM CONTRATO -->
  <section class="card-dashboard">
    <h3>Empenhos sem Contrato</h3>
    <table class="tabela-usuarios">
      <thead>
        <tr>
          <th>Empenho</th>
          <th>Objeto</th>
          <th>Valor</th>
          <th>Fim Previsto</th>
          <th>Dias Restantes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($empenhos_sem_contrato as $e):
          $dias = diasRestantes($e['data_fim_previsto']);
          $cor = $dias <= 30 ? 'vermelho' : ($dias <= 90 ? 'amarelo' : 'verde');
        ?>
          <tr>
            <td><?= htmlspecialchars($e['numero_empenho']) ?></td>
            <td><?= htmlspecialchars($e['fornecedor']) ?></td>
            <td>R$ <?= number_format($e['valor_empenhado'], 2, ',', '.') ?></td>
            <td><?= date('d/m/Y', strtotime($e['data_fim_previsto'])) ?></td>
            <td class="<?= $cor ?>"><?= $dias ?> dias</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</div>

<?php include './templates/includes/footer.php'; ?>
