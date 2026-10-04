<?php
/**
 * Data Migration Script: Local MariaDB -> TiDB Cloud
 * 
 * This script exports the structure and data from the local database
 * and imports it into the production TiDB database.
 */

// 1. Read Local Credentials
$local_host = '127.0.0.1';
$local_port = '3307';
$local_user = 'root';
$local_pass = '';
$local_db   = 'student_management';

// 2. Read Production Credentials
$prod_env = [];
if (!file_exists(__DIR__ . '/.env.production')) {
    die("Error: .env.production file not found.\n");
}

foreach (file(__DIR__ . '/.env.production', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    $l = trim($l);
    if ($l === '' || $l[0] === '#' || strpos($l, '=') === false) continue;
    [$k, $v] = explode('=', $l, 2);
    $prod_env[trim($k)] = trim($v, " \t\"'");
}

$prod_host = $prod_env['DB_HOST'] ?? '';
$prod_port = $prod_env['DB_PORT'] ?? '4000';
$prod_user = $prod_env['DB_USER'] ?? '';
$prod_pass = $prod_env['DB_PASS'] ?? '';
$prod_db   = $prod_env['DB_NAME'] ?? 'student_management';

if (empty($prod_host) || empty($prod_user)) {
    die("Error: Missing TiDB credentials in .env.production. Please fill them in and save the file.\n");
}

echo "Migrating data from local MariaDB ($local_db) to TiDB Cloud ($prod_db)...\n";
echo "Production Host: $prod_host\n";
echo "Production User: $prod_user\n";

try {
    // Connect to local DB
    $local_pdo = new PDO("mysql:host=$local_host;port=$local_port;dbname=$local_db;charset=utf8mb4", $local_user, $local_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "Connected to local database.\n";

    // Connect to TiDB
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 15];
    $prod_pdo = new PDO("mysql:host=$prod_host;port=$prod_port;dbname=$prod_db;charset=utf8mb4", $prod_user, $prod_pass, $opts);
    echo "Connected to TiDB database.\n";

    // Disable foreign key checks on TiDB for migration
    $prod_pdo->exec("SET FOREIGN_KEY_CHECKS=0;");

    // Fetch all tables from local
    $stmt = $local_pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        echo "\nProcessing table: $table\n";

        // Get Create Table statement
        $stmt = $local_pdo->query("SHOW CREATE TABLE `$table`");
        $create_stmt = $stmt->fetch(PDO::FETCH_ASSOC)['Create Table'];

        // Remove AUTO_INCREMENT value to start fresh if needed, or keep it.
        // We'll just execute it on TiDB to ensure the table exists
        $prod_pdo->exec("DROP TABLE IF EXISTS `$table`;");
        $prod_pdo->exec($create_stmt);
        echo "- Created table in TiDB.\n";

        // Fetch data
        $stmt = $local_pdo->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($rows) > 0) {
            echo "- Inserting " . count($rows) . " rows... ";
            
            // Build insert query
            $columns = array_keys($rows[0]);
            $colNames = implode(", ", array_map(function($c) { return "`$c`"; }, $columns));
            $placeholders = implode(", ", array_fill(0, count($columns), "?"));
            
            $insert_stmt = $prod_pdo->prepare("INSERT INTO `$table` ($colNames) VALUES ($placeholders)");
            
            // Insert in chunks to avoid overwhelming the server
            $prod_pdo->beginTransaction();
            $count = 0;
            foreach ($rows as $row) {
                $insert_stmt->execute(array_values($row));
                $count++;
                if ($count % 500 == 0) {
                    $prod_pdo->commit();
                    $prod_pdo->beginTransaction();
                    echo ".";
                }
            }
            $prod_pdo->commit();
            echo " Done.\n";
        } else {
            echo "- No rows to insert.\n";
        }
    }

    // Initialize file storage table
    echo "\nInitializing file_storage table...\n";
    require_once __DIR__ . '/includes/file_storage.php';
    init_file_storage($prod_pdo);
    echo "- file_storage table ready.\n";

    $prod_pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
    echo "\nMigration completed successfully!\n";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
