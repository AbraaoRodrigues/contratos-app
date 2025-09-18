<?php
try {
  $pdo = new PDO('mysql:host=10.0.2.3;dbname=contratos_agudos;charset=utf8', 'abraao', '7Sq0u2o6qFlk');
  echo "Conectado com sucesso!";
} catch (PDOException $e) {
  echo "Erro na conexão: " . $e->getMessage();
}
