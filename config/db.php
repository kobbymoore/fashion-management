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
                $errorMsg = $e->getMessage();
                $debugInfo = sprintf(
                    "<br><small>Host: %s | Port: %s | DB: %s | User: %s</small>",
                    DB_HOST, DB_PORT, DB_NAME, DB_USER
                );
                die('<div style="font-family:sans-serif;padding:20px;background:#fee;border:1px solid #c00;border-radius:8px;max-width:800px;margin:50px auto;">
                    <h2 style="color:#c00">Database Connection Error</h2>
                    <p><strong>Message:</strong> ' . htmlspecialchars($errorMsg) . '</p>
                    ' . $debugInfo . '
                    <hr>
                    <p><strong>Troubleshooting:</strong></p>
                    <ol>
                        <li>Verify your <strong>Supabase Password</strong> is correct.</li>
                        <li>Ensure <strong>ipv4</strong> is enabled or check Supabase Project Settings > Database for the correct Host.</li>
                        <li>Ensure the schema has been imported via the Supabase <strong>SQL Editor</strong>.</li>
                    </ol>
                </div>');
            }
            die('Service temporarily unavailable.');
        }
    }
    return $pdo;
}
