<?php
// Admin Portal Database Configuration
$host = 'localhost';
$db   = 'suro';
$user = 'root';
$pass = 'Stephan2k03'; // Set your password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("CRITICAL ADMIN ERROR: Database Connection Failed.");
}

// Start Session for Admin Auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>