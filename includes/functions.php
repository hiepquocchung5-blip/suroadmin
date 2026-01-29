<?php
// Ensure session starts if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
function requireAuth() {
    // Check all required session keys
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username']) || !isset($_SESSION['admin_role'])) {
        // Clear potential partial session
        session_unset();
        session_destroy();
        
        // Redirect to Login
        // Adjust path dynamically based on depth
        $loginPath = '/index.php'; // Adjust based on your server root
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

// Safe Input
function cleanInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}
?>