<?php
declare(strict_types=1);

namespace App\models;

use PDO;

abstract class DBManager
{
    private static ?PDO $pdo = null;

    protected function db(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_general_ci",
            ]);
        }
        return self::$pdo;
    }
}
