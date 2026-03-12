<?php
require_once __DIR__ . '/../includes/functions.php';

// 1. Locate and Load the .env file from the server root
// Assumes .env is placed one level above the Admin folder (e.g., /var/www/html/.env)
$envPath = realpath(__DIR__ . '/../../.env'); 
if ($envPath) {
    loadEnv($envPath);
}

// 2. Securely Load Database Configuration
$host = getEnvSafe('DB_HOST', 'localhost');
$db   = getEnvSafe('DB_NAME', 'suropara_db');
$user = getEnvSafe('DB_USER', 'root');
$pass = getEnvSafe('DB_PASS', ''); 
$charset = 'utf8mb4';

// 3. Establish PDO Connection
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => false, // Disabled for stability in shared hosting
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Log the actual error to the server, but hide it from the UI for security
    error_log("CRITICAL ADMIN ERROR: Database Connection Failed - " . $e->getMessage());
    die("<h1>503 Service Unavailable</h1><p>Database connection failed. Check server logs.</p>");
}

// Start Session for Admin Auth
if (session_status() === PHP_SESSION_NONE) {
    // Production Session Security
    if (getEnvSafe('APP_ENV') === 'production') {
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        session_set_cookie_params([
            'lifetime' => 86400,
            'path' => '/',
            'domain' => getEnvSafe('COOKIE_DOMAIN') ?: '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }
    session_start();
}
?>