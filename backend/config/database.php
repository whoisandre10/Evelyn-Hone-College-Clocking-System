<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Database Configuration (PostgreSQL PDO)
 */

if (!defined('EHC_SYSTEM')) {
    define('EHC_SYSTEM', true);
}

class Database {
    private static ?PDO $instance = null;

    private static string $host = '127.0.0.1';
    private static string $port = '5432';
    private static string $dbname = 'ehc_clocking_db';
    private static string $username = 'postgres';
    private static string $password = 'postgres';

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            // Allow environment variable overrides
            $host = getenv('DB_HOST') ?: self::$host;
            $port = getenv('DB_PORT') ?: self::$port;
            $dbname = getenv('DB_NAME') ?: self::$dbname;
            $username = getenv('DB_USER') ?: self::$username;
            $password = getenv('DB_PASS') ?: self::$password;

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
            ];

            try {
                self::$instance = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                // In production, log and show clean message
                error_log("EHC Database Connection Error: " . $e->getMessage());
                die("<div style='font-family:sans-serif;padding:30px;background:#fff3cd;border:1px solid #ffeeba;border-radius:8px;max-width:600px;margin:50px auto;color:#856404;'>"
                    . "<h3 style='margin-top:0;'>Evelyn Hone College Database Connection</h3>"
                    . "<p>Unable to connect to the PostgreSQL database (<strong>{$dbname}</strong>) at <strong>{$host}:{$port}</strong>.</p>"
                    . "<p><small>Details: " . htmlspecialchars($e->getMessage()) . "</small></p>"
                    . "<p>Please ensure PostgreSQL service is running and the database has been imported.</p>"
                    . "</div>");
            }
        }
        return self::$instance;
    }
}

function get_db(): PDO {
    return Database::getConnection();
}
