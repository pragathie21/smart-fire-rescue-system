<?php
/**
 * Smart Fire and Rescue Response System
 * Database Connection Helper (PDO Singleton)
 */

require_once __DIR__ . '/config.php';

/**
 * Returns a shared PDO database connection instance.
 *
 * @return PDO
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $host    = defined('DB_HOST') ? DB_HOST : 'localhost';
        $dbname  = defined('DB_NAME') ? DB_NAME : 'smart_fire_rescue';
        $username= defined('DB_USER') ? DB_USER : 'root';
        $password= defined('DB_PASS') ? DB_PASS : '';
        $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

        $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die("Database connection failed: " . htmlspecialchars($e->getMessage()));
        }
    }

    return $pdo;
}

// Global variable for backward compatibility with scripts expecting $pdo
$pdo = getDB();