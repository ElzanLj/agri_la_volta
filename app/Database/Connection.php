<?php

declare(strict_types=1);

namespace App\Database;

use App\Config;
use PDO;

final class Connection
{
    public static function connect(Config $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config->string('DB_HOST', 'localhost'),
            $config->int('DB_PORT', 3306),
            $config->string('DB_NAME'),
        );

        $pdo = new PDO($dsn, $config->string('DB_USER'), $config->string('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // DATETIME values are stored in UTC; DATE values (stay dates) carry no time zone.
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

        return $pdo;
    }
}
