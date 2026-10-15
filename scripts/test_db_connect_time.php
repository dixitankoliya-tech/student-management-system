<?php
require_once __DIR__ . '/../config/database.php';

// Measure new connection time
$t1 = microtime(true);
$pdo1 = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, $pdoOptions);
$pdo1->query("SELECT 1");
$time1 = (microtime(true) - $t1) * 1000;
echo "Standard Non-persistent TLS Connection: " . round($time1, 1) . " ms\n";

// Measure with persistent connection
$opts = $pdoOptions;
$opts[PDO::ATTR_PERSISTENT] = true;
$t2 = microtime(true);
$pdo2 = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, $opts);
$pdo2->query("SELECT 1");
$time2 = (microtime(true) - $t2) * 1000;
echo "Persistent Connection (1st call): " . round($time2, 1) . " ms\n";

$t3 = microtime(true);
$pdo3 = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, $opts);
$pdo3->query("SELECT 1");
$time3 = (microtime(true) - $t3) * 1000;
echo "Persistent Connection (2nd call reused): " . round($time3, 1) . " ms\n";

// Test DDL vs DML latency
$t4 = microtime(true);
$pdo3->exec("CREATE TABLE IF NOT EXISTS sessions (id VARCHAR(128) PRIMARY KEY, data TEXT, last_accessed TIMESTAMP)");
$time4 = (microtime(true) - $t4) * 1000;
echo "DDL 'CREATE TABLE IF NOT EXISTS sessions' latency: " . round($time4, 1) . " ms\n";

$t5 = microtime(true);
$pdo3->query("SELECT 1")->fetch();
$time5 = (microtime(true) - $t5) * 1000;
echo "DML 'SELECT 1' query latency: " . round($time5, 1) . " ms\n";
