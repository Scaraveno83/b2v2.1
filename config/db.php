<?php
$errorHandler = __DIR__ . '/error_handling.php';
if (file_exists($errorHandler)) {
    require_once $errorHandler;
}

$dbHost = "localhost";
$dbName = "db_453539_5";
$dbUser = "USER453539_22";
$dbPass = "15118329112006";

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("DB-Verbindung fehlgeschlagen: " . $e->getMessage());
}
