<?php
// Esse arquivo executa os dois alertas de e-mail

chdir(__DIR__);

require_once __DIR__ . '/../config/db.php';

// Executa o script de alerta de saldo
include __DIR__ . '/../alertas/verificar_saldos.php';

// Executa o script de alerta de prazo
include __DIR__ . '/../alertas/verificar_alertas.php';

echo "Alertas executados em " . date('Y-m-d H:i:s');
