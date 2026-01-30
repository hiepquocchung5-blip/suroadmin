<?php
/**
 * SUROPARA Admin Portal - Master Entry & Router (v3.0)
 * Handles "God Mode" Authentication and Dynamic Module Routing.
 */

// 1. Core Initialization
require_once 'config/db.php';
require_once 'includes/functions.php';

// 2. Handle Logout
if (isset($_GET['logout'])) {
    // Audit online status before logout
    if (isset($_SESSION['admin_id'])) {
        $pdo->prepare("UPDATE admin_users SET is_online = 0 WHERE id = ?")->execute([$_SESSION['admin_id']]);
    }
    session_unset();
    session_destroy();
    header("Location: index.php?msg=logged_out");
    exit;
}

// 3. Authentication Check
$isLoggedIn = isset($_SESSION['admin_id']) && isset($_SESSION['admin_role']);

// 4. Handle Login POST
$error = '';
if (!$isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = cleanInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Credentials Required";
    } else {
        try {
            // Updated to match your provided table structure (admin_users)
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                session_regenerate_id(true);
                
                // Set Critical Session Variables
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role']; // e.g., GOD, FINANCE, etc.
                
                // CSRF Token - Stored in SESSION, not the database
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
                // Update Login Stats & Online Status
                $pdo->prepare("UPDATE admin_users SET last_login = NOW(), is_online = 1 WHERE id = ?")->execute([$admin['id']]);
                
                // Redirect to the default dashboard module
                header("Location: index.php?module=dashboard");
                exit;
            } else {
                $error = "Access Denied: Invalid Credentials";
            }
        } catch (PDOException $e) {
            $error = "Core Database Link Failure";
        }
    }
}

// 5. ROUTING LOGIC (If Logged In)
if ($isLoggedIn) {
    $module = $_GET['module'] ?? 'dashboard';
    
    // Map Modules to Physical Files
    $routes = [
        'dashboard'  => 'modules/dashboard/index.php',
        'live'       => 'modules/dashboard/live.php',
        'users'      => 'modules/users/list.php',
        'finance'    => 'modules/finance/queue.php',
        'characters' => 'modules/characters/index.php',
        'islands'    => 'modules/islands/index.php',
        'settings'   => 'modules/settings/global.php',
        'security'   => 'modules/security/index.php',
        'social'     => 'modules/social/chat.php'
    ];

    $targetFile = $routes[$module] ?? 'modules/dashboard/index.php';

    if (file_exists($targetFile)) {
        /**
         * SAFETY FIX: We define $pathDepth here with a max(0) safeguard.
         * This prevents the str_repeat error when PHP_SELF count is low.
         */
        $pathDepth = max(0, substr_count($_SERVER['PHP_SELF'], '/') - 2);

        // Wrap the module content in the modern layout
        require_once 'layout/main.php';
        include $targetFile;
        require_once 'layout/footer.php';
        exit;
    } else {
        // Fallback for missing files
        header("Location: index.php?module=dashboard&error=not_found");
        exit;
    }
}

// 6. LOGIN INTERFACE (If NOT Logged In)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SUROPARA | God Mode Access</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #020617; }
        .glow-overlay {
            background: radial-gradient(circle at 50% 50%, rgba(37, 99, 235, 0.15) 0%, rgba(2, 6, 23, 0) 70%);
        }
        .glass-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        @keyframes subtle-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .float-anim { animation: subtle-float 6s ease-in-out infinite; }
    </style>
</head>
<body class="h-screen flex items-center justify-center overflow-hidden p-4">

    <!-- Background FX -->
    <div class="fixed inset-0 glow-overlay z-0"></div>
    <div class="fixed top-[-10%] left-[-10%] w-[40%] h-[40%] bg-blue-600/10 blur-[120px] rounded-full"></div>
    <div class="fixed bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-indigo-600/10 blur-[120px] rounded-full"></div>

    <!-- Login Container -->
    <div class="w-full max-w-md z-10 float-anim">
        <div class="glass-card rounded-[2rem] p-8 md:p-12 shadow-2xl">
            <div class="text-center mb-10">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-600 rounded-2xl mb-6 shadow-xl shadow-blue-600/20">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <h1 class="text-3xl font-black text-white tracking-tighter uppercase mb-2">Suropara</h1>
                <p class="text-slate-400 text-xs font-bold uppercase tracking-[0.3em] opacity-60">God Mode Access Only</p>
            </div>

            <?php if ($error): ?>
            <div class="mb-6 bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl text-xs font-bold text-center animate-pulse">
                <span class="mr-2">⚠️</span> <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <form action="index.php" method="POST" class="space-y-6">
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">Secure Username</label>
                    <input type="text" name="username" required autofocus
                        class="w-full bg-slate-900/50 border border-slate-700 text-white rounded-2xl px-5 py-4 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all placeholder-slate-600 font-semibold"
                        placeholder="Enter admin ID">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">System Cipher</label>
                    <input type="password" name="password" required
                        class="w-full bg-slate-900/50 border border-slate-700 text-white rounded-2xl px-5 py-4 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all placeholder-slate-600 font-semibold"
                        placeholder="••••••••">
                </div>

                <button type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-500 text-white font-black py-4 rounded-2xl shadow-lg shadow-blue-600/30 transition-all active:scale-[0.98] uppercase tracking-widest text-sm">
                    Authenticate
                </button>
            </form>

            <div class="mt-10 text-center border-t border-slate-800 pt-8">
                <div class="flex items-center justify-center space-x-2">
                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                    <p class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">Authorized Personnel Port 8080</p>
                </div>
            </div>
        </div>
        
        <p class="text-center mt-6 text-[10px] text-slate-600 font-bold uppercase tracking-tighter">
            Encrypted Session &bull; IP Logged &bull; SURO Networks v3.0
        </p>
    </div>

</body>
</html>