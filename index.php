<?php
/**
 * Suropara Admin Portal - Login Entry Point (v3.0)
 * Handles authentication, session initiation, and logout.
 */

// 1. Load Database Configuration
require_once 'config/db.php';

// 2. Load Helper Functions (if available, otherwise define basic input cleaning)
if (file_exists('includes/functions.php')) {
    require_once 'includes/functions.php';
} else {
    // Fallback if functions.php isn't loaded yet
    function cleanInput($data) {
        return htmlspecialchars(stripslashes(trim($data)));
    }
}

// 3. Handle Logout Request
if (isset($_GET['logout'])) {
    // Completely destroy session
    session_unset();
    session_destroy();
    // Redirect to self to clear query params
    header("Location: index.php");
    exit;
}

// 4. Redirect if Already Logged In
if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_role']) && isset($_SESSION['admin_username'])) {
    header("Location: modules/dashboard/index.php");
    exit;
}

$error = '';

// 5. Handle Login Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = cleanInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        try {
            // Fetch Admin User (Must be Active)
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            // Verify Password
            if ($admin && password_verify($password, $admin['password_hash'])) {
                // Security: Regenerate Session ID to prevent Session Fixation attacks
                session_regenerate_id(true);

                // Set Critical Session Variables
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['login_time'] = time();

                // Update Last Login Timestamp in DB
                $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
                
                // Audit Log (Optional, if audit_logs table exists)
                try {
                    $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, 'LOGIN', 'auth')")->execute([$admin['id']]);
                } catch (Exception $e) { /* Ignore audit error on login */ }

                // Redirect to Dashboard
                header("Location: modules/dashboard/index.php");
                exit;
            } else {
                $error = "Invalid Credentials or Account Suspended";
            }
        } catch (PDOException $e) {
            $error = "System Error: Database connection failed.";
            // error_log($e->getMessage()); // Log internal error
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suropara God Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: #0f172a; 
            color: #fff; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
            font-family: 'Segoe UI', sans-serif;
            overflow: hidden;
        }
        .login-card { 
            background: #1e293b; 
            border: 1px solid #334155; 
            width: 100%; 
            max-width: 400px; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.5); 
            border-radius: 12px;
            position: relative;
            z-index: 10;
        }
        .btn-neon { 
            background: #00f3ff; 
            color: #000; 
            font-weight: 800; 
            border: none; 
            transition: 0.3s; 
            letter-spacing: 1px;
        }
        .btn-neon:hover { 
            background: #00c4cf; 
            box-shadow: 0 0 20px rgba(0, 243, 255, 0.4); 
            transform: translateY(-2px); 
        }
        .form-control {
            background-color: #0f172a;
            border-color: #334155;
            color: #fff;
        }
        .form-control:focus { 
            background-color: #0f172a;
            color: #fff; 
            border-color: #00f3ff; 
            box-shadow: 0 0 0 0.25rem rgba(0, 243, 255, 0.1); 
        }
        
        /* Background FX */
        .bg-glow {
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(0,243,255,0.05) 0%, rgba(0,0,0,0) 70%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 0;
            pointer-events: none;
        }
    </style>
</head>
<body>

    <div class="bg-glow"></div>

    <div class="login-card p-5">
        <div class="text-center mb-5">
            <h2 class="text-info fw-black tracking-widest mb-1">SUROPARA</h2>
            <p class="text-secondary small fw-bold letter-spacing-2 m-0" style="letter-spacing: 2px;">GOD MODE ACCESS</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger py-2 small text-center fw-bold border-0 bg-danger bg-opacity-25 text-danger-emphasis mb-4">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="small text-muted mb-1 fw-bold">USERNAME</label>
                <input type="text" name="username" class="form-control form-control-lg" required autofocus autocomplete="off">
            </div>
            <div class="mb-4">
                <label class="small text-muted mb-1 fw-bold">PASSWORD</label>
                <input type="password" name="password" class="form-control form-control-lg" required>
            </div>
            <button type="submit" class="btn btn-neon w-100 py-3 rounded-2">AUTHENTICATE</button>
        </form>
        
        <div class="mt-4 text-center">
            <small class="text-muted" style="font-size: 0.7rem;">SECURE SYSTEM • AUTHORIZED PERSONNEL ONLY</small>
        </div>
    </div>

</body>
</html>