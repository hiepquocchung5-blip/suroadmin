<?php
/**
 * Suropara Admin V2 - Front Controller & Router
 * Hides raw file paths and handles global routing.
 */
session_start();

// 1. DEFINE ABSOLUTE BASE PATH TO FIX "FILE NOT FOUND" ERRORS
define('ADMIN_BASE_PATH', __DIR__);

require_once ADMIN_BASE_PATH . '/config/db.php';
require_once ADMIN_BASE_PATH . '/includes/functions.php';

// 2. Handle Logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}

// 3. Handle Login Submission
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $username = cleanInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            
            $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
            header("Location: index.php?route=dashboard");
            exit;
        } else {
            $loginError = "Invalid Credentials or Account Suspended.";
        }
    } else {
        $loginError = "Please enter all fields.";
    }
}

// 4. Render Login View if not authenticated
if (!isset($_SESSION['admin_id'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en" data-bs-theme="dark">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Suropara V2 - God Access</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background: #050505; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Inter', sans-serif; overflow: hidden; }
            .bg-grid { position: absolute; inset: 0; background-image: linear-gradient(rgba(0, 243, 255, 0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(0, 243, 255, 0.05) 1px, transparent 1px); background-size: 30px 30px; pointer-events: none; }
            .login-card { background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(0, 243, 255, 0.2); width: 100%; max-width: 400px; border-radius: 20px; box-shadow: 0 0 50px rgba(0, 243, 255, 0.1); z-index: 10; }
        </style>
    </head>
    <body>
        <div class="bg-grid"></div>
        <div class="login-card p-5">
            <div class="text-center mb-5">
                <h2 class="text-info fw-black tracking-widest mb-1" style="letter-spacing: 4px;">SUROPARA</h2>
                <div class="badge bg-info text-dark fw-bold px-3 py-1 mt-2">SYSTEM ACCESS V2</div>
            </div>
            
            <?php if($loginError): ?>
                <div class="alert bg-danger bg-opacity-25 text-danger border-danger text-center fw-bold small"><?= $loginError ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="login_submit" value="1">
                <div class="mb-3">
                    <input type="text" name="username" class="form-control bg-dark border-secondary text-white py-3" placeholder="Admin ID" required autocomplete="off">
                </div>
                <div class="mb-4">
                    <input type="password" name="password" class="form-control bg-dark border-secondary text-white py-3" placeholder="Secure Key" required>
                </div>
                <button type="submit" class="btn btn-info w-100 fw-black py-3 text-dark shadow-lg">ESTABLISH LINK</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 5. Central Routing Engine
$route = $_GET['route'] ?? 'dashboard';

// Map friendly URLs to actual file paths
$routes = [
    'dashboard'         => 'modules/dashboard/index.php',
    'live'              => 'modules/dashboard/live.php',
    
    'finance/queue'     => 'modules/finance/queue.php',
    'finance/reports'   => 'modules/finance/reports.php',
    'finance/export'    => 'modules/finance/export.php',
    
    'players/list'      => 'modules/users/list.php',
    'players/details'   => 'modules/users/details.php',
    'players/whales'    => 'modules/users/whales.php',
    'players/agents'    => 'modules/users/agent.php',
    
    'fleet/monitor'     => 'modules/machines/monitor.php',
    'fleet/manage'      => 'modules/machines/manage.php',
    'fleet/heatmap'     => 'modules/machines/heatmap.php',
    
    'content/islands'   => 'modules/islands/index.php',
    'content/chars'     => 'modules/characters/index.php',
    'content/editor'    => 'modules/characters/editor.php',
    
    'marketing/events'  => 'modules/marketing/events.php',
    'marketing/jackpots'=> 'modules/marketing/jackpots.php',
    
    'security/monitor'  => 'modules/security/index.php',
    'social/chat'       => 'modules/social/chat.php',
    
    'config/global'     => 'modules/settings/global.php',
    'config/methods'    => 'modules/finance_config/methods.php',
    'config/limits'     => 'modules/finance_config/limits.php',
    
    'staff/manage'      => 'modules/staff/manage.php',
    'staff/performance' => 'modules/staff/performance.php',
    'staff/logs'        => 'modules/staff/logs.php',
];

if (array_key_exists($route, $routes)) {
    $targetFile = ADMIN_BASE_PATH . '/' . $routes[$route];
    if (file_exists($targetFile)) {
        require_once $targetFile;
    } else {
        echo "<h2>Module Not Found</h2><p>The file <code>{$routes[$route]}</code> is missing.</p>";
    }
} else {
    echo "<h2>404 - Route Not Found</h2><p>The route <code>$route</code> is not registered.</p>";
}
?>