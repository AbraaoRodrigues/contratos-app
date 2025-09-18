<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: /templates/login.php');
  exit;
}

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../../config/db_precos.php';
$pdo = ConexaoPrecos::getInstance();

// Se recebeu POST para atualizar base
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar'])) {
  $dias = (int)($_POST['dias'] ?? 7);
  exec("php " . __DIR__ . "/crontab/cron_atualizar_cache.php $dias > /dev/null 2>&1 &");
  $msg = "Atualização da base iniciada em background para últimos $dias dias.";
}

// Se recebeu POST para reprocessar falhas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reprocessar'])) {
  exec("php " . __DIR__ . "/crontab/cron_reprocessar_falhas.php > /dev/null 2>&1 &");
  $msg = "Reprocessamento de falhas iniciado em background.";
}

// Buscar falhas registradas
$falhas = $pdo->query("SELECT * FROM cache_pncp_falhas ORDER BY criado_em DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <title>Administração PNCP</title>
  <link rel="stylesheet" href="assets/styles.css">
</head>

<body>
  <h2>Administração do Cache PNCP</h2>

  <?php if (!empty($msg)): ?>
    <div class="alert success"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <form method="post" style="margin-bottom:20px;">
    <label>
      Atualizar base de dados (últimos dias):
      <input type="number" name="dias" value="7" min="1" max="365">
    </label>
    <button type="submit" name="atualizar" class="btn">Atualizar</button>
  </form>

  <form method="post" style="margin-bottom:20px;">
    <button type="submit" name="reprocessar" class="btn">Reprocessar falhas</button>
  </form>

  <h3>Falhas registradas (últimas 50)</h3>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Número Controle</th>
        <th>Motivo</th>
        <th>Status</th>
        <th>Tentativas</th>
        <th>Criado em</th>
        <th>Corrigido em</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($falhas as $f): ?>
        <tr>
          <td><?= $f['id'] ?></td>
          <td><?= htmlspecialchars($f['numeroControlePNCP']) ?></td>
          <td><?= htmlspecialchars($f['motivo']) ?></td>
          <td><?= $f['status'] ?></td>
          <td><?= $f['tentativas'] ?></td>
          <td><?= $f['criado_em'] ?></td>
          <td><?= $f['corrigido_em'] ?? '-' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <hr>

  <h3>📜 Últimos logs de sincronização</h3>
  <pre style="background:#111;color:#0f0;padding:10px;max-height:400px;overflow:auto;font-family:monospace">
<?php
$logFile = __DIR__ . '/crontab/logs/cron.log';
if (file_exists($logFile)) {
  // lê os últimos ~2000 caracteres (para não pesar se log for enorme)
  $size = filesize($logFile);
  $fp = fopen($logFile, 'r');
  if ($size > 2000) {
    fseek($fp, -2000, SEEK_END);
    echo htmlspecialchars(fread($fp, 2000));
  } else {
    echo htmlspecialchars(file_get_contents($logFile));
  }
  fclose($fp);
} else {
  echo "⚠️ Nenhum log encontrado ainda.";
}
?>
</pre>
  <button onclick="location.reload()">🔄 Atualizar log</button>


</body>

</html>
