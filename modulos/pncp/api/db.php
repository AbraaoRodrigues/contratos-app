<?php
date_default_timezone_set('America/Sao_Paulo');

define('HOST', 'mysql.saude.agudos.digital');
define('DBNAME', 'consulta_precos');
define('CHARSET', 'utf8');
define('USER', 'abraao');
define('PASSWORD', '7Sq0u2o6qFlk');

class Conexao
{
  private static $pdo;

  private function __construct() {}

  public static function getInstance()
  {
    if (!isset(self::$pdo)) {
      try {
        $opcoes = [
          PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES UTF8',
          PDO::ATTR_PERSISTENT => TRUE
        ];
        self::$pdo = new PDO("mysql:host=" . HOST . ";port=9051;dbname=" . DBNAME . ";charset=" . CHARSET, USER, PASSWORD, $opcoes);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      } catch (PDOException $e) {
        die("Erro na conexão: " . $e->getMessage());
      }
    }
    return self::$pdo;
  }
}
