<?php
/**
 * Conexión PDO única a MySQL.
 * El esquema por defecto es vfpmedicos. Las tablas FoxPro se leen
 * con nombre calificado (cartilla.ebamp) sobre la misma conexión.
 *
 * Compatible con PHP 5.6.
 */

class Database
{
    private static $instance = null;

    private function __construct()
    {
    }

    public static function getConnection()
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $esLocal = ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1' || strpos($host, 'localhost:') === 0);

        $dsn = 'mysql:host=127.0.0.1;port=3306;dbname=vfpmedicos;charset=utf8';
        $opciones = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        );

        if ($esLocal) {
            self::$instance = new PDO($dsn, 'root', '', $opciones);
        } else {
            self::$instance = new PDO($dsn, 'vfpmedicos', 'vfpmedicos*18', $opciones);
        }

        self::$instance->exec('SET NAMES utf8');

        return self::$instance;
    }
}

function getDBConnection()
{
    return Database::getConnection();
}
