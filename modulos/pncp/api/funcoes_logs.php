<?php
function registrarLog($usuarioId, $acao)
{
  try {
    $pdo = new PDO("mysql:host=localhost;dbname=contratos_agudos;charset=utf8mb4", "root", "sua_senha");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $st = $pdo->prepare("INSERT INTO logs (usuario_id, acao, ip) VALUES (?, ?, ?)");
    $st->execute([$usuarioId, $acao, $ip]);
  } catch (Throwable $e) {
    // falha silenciosa para não travar
  }
}
