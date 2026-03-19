<?php
/**
 * Database-backed PHP Session Handler
 * Stores sessions in PostgreSQL (Supabase) so they persist across
 * Vercel serverless invocations and container restarts.
 *
 * Table is auto-created on first run — no manual migration needed.
 */

class DbSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private int $lifetime;

    public function __construct(PDO $pdo, int $lifetime)
    {
        $this->pdo      = $pdo;
        $this->lifetime = $lifetime;
    }

    /** Ensure the sessions table exists. */
    public function open(string $path, string $name): bool
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS php_sessions (
                session_id   VARCHAR(128)  NOT NULL PRIMARY KEY,
                session_data TEXT          NOT NULL DEFAULT '',
                last_activity BIGINT       NOT NULL DEFAULT 0
            )
        ");
        return true;
    }

    public function close(): bool
    {
        // Occasionally garbage-collect expired sessions (~1% of requests)
        if (random_int(1, 100) === 1) {
            $this->gc($this->lifetime);
        }
        return true;
    }

    public function read(string $id): mixed
    {
        $stmt = $this->pdo->prepare(
            "SELECT session_data FROM php_sessions
             WHERE session_id = ? AND last_activity > ?"
        );
        $stmt->execute([$id, time() - $this->lifetime]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['session_data'] : '';
    }

    public function write(string $id, string $data): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO php_sessions (session_id, session_data, last_activity)
            VALUES (?, ?, ?)
            ON CONFLICT (session_id)
            DO UPDATE SET session_data = EXCLUDED.session_data,
                          last_activity = EXCLUDED.last_activity
        ");
        return $stmt->execute([$id, $data, time()]);
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM php_sessions WHERE session_id = ?"
        );
        return $stmt->execute([$id]);
    }

    public function gc(int $max_lifetime): mixed
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM php_sessions WHERE last_activity < ?"
        );
        $stmt->execute([time() - $max_lifetime]);
        return $stmt->rowCount();
    }
}

/**
 * Bootstrap the custom handler.
 * Called once from config.php BEFORE session_start().
 */
function registerDbSessionHandler(PDO $pdo, int $lifetime): void
{
    $handler = new DbSessionHandler($pdo, $lifetime);
    session_set_save_handler($handler, true);
}
