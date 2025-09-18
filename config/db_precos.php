<?php
date_default_timezone_set('America/Sao_Paulo');

define('HOST2', 'mysql.saude.agudos.digital');
define('DBNAME2', 'consulta_precos');
define('CHARSET2', 'utf8');
define('USER2', 'abraao');
define('PASSWORD2', '7Sq0u2o6qFlk');

class ConexaoPrecos
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
        self::$pdo = new PDO(
          "mysql:host=" . HOST2 . ";port=9051;dbname=" . DBNAME2 . ";charset=" . CHARSET2,
          USER2,
          PASSWORD2,
          $opcoes
        );
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      } catch (PDOException $e) {
        die("Erro na conexão (consulta_precos): " . $e->getMessage());
      }
    }
    return self::$pdo;
  }
}
