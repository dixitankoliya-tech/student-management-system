<?php
/**
 * Production-Safe Database Session Handler
 * Required for serverless/container environments like Vercel where the local filesystem
 * (e.g. /tmp) is ephemeral and not shared between instances.
 */

class DatabaseSessionHandler implements SessionHandlerInterface {
    private $pdo;
    private $initialDataHash = null;
    private $lastAccessed = 0;

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
            $stmt = $this->pdo->prepare("SELECT data, UNIX_TIMESTAMP(last_accessed) as ts FROM sessions WHERE id = ? AND last_accessed > DATE_SUB(NOW(), INTERVAL 1 DAY)");
            $stmt->execute([$id]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->initialDataHash = md5($row['data']);
                $this->lastAccessed = (int)($row['ts'] ?? 0);
                return $row['data'];
            }
            $this->initialDataHash = md5('');
            return '';
        } catch (PDOException $e) {
            error_log("Session Read Error: " . $e->getMessage());
            return '';
        }
    }

    public function write($id, $data): bool {
        // Optimization: If session data is unchanged and touched within last 5 minutes (300s),
        // skip the redundant REPLACE INTO query. Eliminates ~95ms of network waiting on read requests.
        $currentHash = md5($data);
        if ($this->initialDataHash !== null && $this->initialDataHash === $currentHash && (time() - $this->lastAccessed) < 300) {
            return true;
        }

        try {
            $stmt = $this->pdo->prepare("REPLACE INTO sessions (id, data, last_accessed) VALUES (?, ?, NOW())");
            $stmt->execute([$id, $data]);
            $this->initialDataHash = $currentHash;
            $this->lastAccessed = time();
            return true;
        } catch (PDOException $e) {
            // Self-healing bootstrap: If table is missing in a fresh database, create once and retry
            if ($e->getCode() === '42S02') {
                try {
                    $this->pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
                        id VARCHAR(128) NOT NULL PRIMARY KEY,
                        data TEXT NOT NULL,
                        last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )");
                    $stmt = $this->pdo->prepare("REPLACE INTO sessions (id, data, last_accessed) VALUES (?, ?, NOW())");
                    $stmt->execute([$id, $data]);
                    $this->initialDataHash = $currentHash;
                    $this->lastAccessed = time();
                    return true;
                } catch (PDOException $ex) {
                    error_log("Session Bootstrap Error: " . $ex->getMessage());
                }
            }
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

// Sessions table is created via database schema; avoid per-request DDL overhead

// Only use database sessions in production, keep local file sessions for local dev if preferred,
// but for consistency we can use it everywhere if DB works.
$handler = new DatabaseSessionHandler($pdo);
session_set_save_handler($handler, true);
?>
