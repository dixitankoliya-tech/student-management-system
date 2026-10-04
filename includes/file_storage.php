<?php
/**
 * Database-backed BLOB Storage Helper
 * 
 * Since Vercel has an ephemeral filesystem, we store uploaded files
 * in the TiDB/MySQL database instead of the local filesystem.
 */

// Function to initialize the file storage table if it doesn't exist
function init_file_storage($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS file_storage (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_path VARCHAR(255) NOT NULL UNIQUE,
        file_name VARCHAR(255) NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        file_size INT NOT NULL,
        file_content LONGBLOB NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
}

/**
 * Stores an uploaded file into the database.
 * 
 * @param string $tmp_name The temporary file path (e.g. $_FILES['...']['tmp_name'])
 * @param string $file_path The virtual path to store the file as (e.g. 'uploads/submissions/file.pdf')
 * @param string $file_name The original file name
 * @param string $mime_type The MIME type of the file
 * @param int $file_size The file size in bytes
 * @param PDO $pdo The PDO connection
 * @return bool True on success, False on failure
 */
function store_file_in_db($tmp_name, $file_path, $file_name, $mime_type, $file_size, $pdo) {
    try {
        // Read file content
        $file_content = file_get_contents($tmp_name);
        if ($file_content === false) {
            return false;
        }

        // Delete existing file with the same path if any
        delete_file_from_db($file_path, $pdo);

        $stmt = $pdo->prepare("INSERT INTO file_storage (file_path, file_name, mime_type, file_size, file_content) VALUES (?, ?, ?, ?, ?)");
        $result = $stmt->execute([$file_path, $file_name, $mime_type, $file_size, $file_content]);
        return $result;
    } catch (PDOException $e) {
        error_log("DB File Storage Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Deletes a file from the database.
 * 
 * @param string $file_path The virtual path of the file
 * @param PDO $pdo The PDO connection
 * @return bool True on success, False on failure
 */
function delete_file_from_db($file_path, $pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM file_storage WHERE file_path = ?");
        return $stmt->execute([$file_path]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Retrieves a file from the database.
 * 
 * @param string $file_path The virtual path of the file
 * @param PDO $pdo The PDO connection
 * @return array|false The file array containing 'file_name', 'mime_type', 'file_size', 'file_content' or false if not found
 */
function get_file_from_db($file_path, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT file_name, mime_type, file_size, file_content FROM file_storage WHERE file_path = ?");
        $stmt->execute([$file_path]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
}
