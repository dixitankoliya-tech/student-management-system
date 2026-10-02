<?php
/**
 * Production-Safe Database Session Handler
 * Required for serverless/container environments like Vercel where the local filesystem
 * (e.g. /tmp) is ephemeral and not shared between instances.
 */

class DatabaseSessionHandler implements SessionHandlerInterface {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function open($savePath, $sessionName): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read($id): string|false {
        try {
            $stmt = $this->pdo->prepare("SELECT data FROM sessions WHERE id = ? AND last_accessed > DATE_SUB(NOW(), INTERVAL 1 DAY)");
            $stmt->execute([$id]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return $row['data'];
            }
            return '';
        } catch (PDOException $e) {
            error_log("Session Read Error: " . $e->getMessage());
            return '';
        }
    }

    public function write($id, $data): bool {
        try {
            $stmt = $this->pdo->prepare("REPLACE INTO sessions (id, data, last_accessed) VALUES (?, ?, NOW())");
            $stmt->execute([$id, $data]);
            return true;
        } catch (PDOException $e) {
            error_log("Session Write Error: " . $e->getMessage());
            return false;
        }
    }

    public function destroy($id): bool {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE id = ?");
            $stmt->execute([$id]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function gc($max_lifetime): int|false {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE last_accessed < DATE_SUB(NOW(), INTERVAL ? SECOND)");
            $stmt->execute([$max_lifetime]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            return false;
        }
    }
}

// Ensure the table exists if it doesn't already
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
        id VARCHAR(128) NOT NULL PRIMARY KEY,
        data TEXT NOT NULL,
        last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    error_log("Failed to create sessions table: " . $e->getMessage());
}

// Only use database sessions in production, keep local file sessions for local dev if preferred,
// but for consistency we can use it everywhere if DB works.
$handler = new DatabaseSessionHandler($pdo);
session_set_save_handler($handler, true);
?>
