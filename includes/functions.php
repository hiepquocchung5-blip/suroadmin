<?php
// Ensure session starts if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================================
// 1. SECURE ENVIRONMENT LOADER (No Composer Required)
// ============================================================================
function loadEnv($path) {
    if (!file_exists($path)) return false;
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip comments and empty lines
        if (empty($line) || strpos($line, '#') === 0) continue;
        
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Strip quotes if they exist around the value
            if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            // Inject into environment securely
            if (function_exists('putenv')) putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    return true;
}

// Safe retrieval function with fallback defaults
function getEnvSafe($key, $default = null) {
    if (isset($_ENV[$key])) return $_ENV[$key];
    if (isset($_SERVER[$key])) return $_SERVER[$key];
    $val = getenv($key);
    return $val !== false ? $val : $default;
}

// ============================================================================
// 2. AUTHENTICATION & UTILITIES
// ============================================================================

// Check if admin is logged in
function requireAuth() {
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username']) || !isset($_SESSION['admin_role'])) {
        session_unset();
        session_destroy();
        
        $loginPath = '/index.php'; 
        if (file_exists('../../index.php')) $loginPath = '../../index.php';
        elseif (file_exists('../index.php')) $loginPath = '../index.php';
        
        header("Location: " . $loginPath);
        exit;
    }
}

// Check role permissions
function requireRole($allowedRoles) {
    if (!in_array($_SESSION['admin_role'], $allowedRoles)) {
        die("ACCESS DENIED: You do not have permission to view this page.");
    }
}

// Format Currency
function formatMMK($amount) {
    return number_format($amount, 0) . ' MMK';
}

// Safe Input to prevent XSS/SQLi
function cleanInput($data) {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}
?>