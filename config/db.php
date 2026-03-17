<?php
require_once __DIR__ . '/config.php';

/**
 * Returns a singleton PDO connection.
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=require', DB_HOST, DB_PORT, DB_NAME);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                die('<div style="font-family:sans-serif;padding:20px;background:#fee;border:1px solid #c00;border-radius:8px;max-width:600px;margin:50px auto;">
                    <h2 style="color:#c00">Database Connection Error</h2>
                    <p>' . htmlspecialchars($e->getMessage()) . '</p>
                    <p>Please ensure XAMPP MySQL is running and the database <strong>' . DB_NAME . '</strong> has been imported.</p>
                </div>');
            }
            die('Service temporarily unavailable. Please try again later.');
        }
    }
    return $pdo;
}
