<?php
// Admin Layout Wrapper (v3.0 - Complete)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Strict Auth Check
requireAuth();

// Safe Session Access (Prevent Undefined Index Warnings)
$adminUser = $_SESSION['admin_username'] ?? 'Admin';
$adminRole = $_SESSION['admin_role'] ?? 'STAFF';

// Determine relative path depth to assets
// This assumes layout/main.php is included by files in /modules/xyz/
$pathDepth = substr_count($_SERVER['PHP_SELF'], '/') - 2; 
// Logic: if in /admin/modules/dashboard/index.php (depth 4), we need ../../ to reach /admin/
// But for simplicity in this flat prototype structure, we assume we are usually 2 levels deep from root admin.
// Links below use hardcoded relative paths typical for this structure (../../)

// Fetch System Status (Mock or DB)
$systemStatus = 1; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Admin Portal' ?> - Suropara</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root { --sidebar-width: 260px; --header-height: 70px; --bg-dark: #0f172a; --bg-card: #1e293b; --neon: #00f3ff; }
        body { background-color: var(--bg-dark); min-height: 100vh; overflow-x: hidden; font-family: 'Segoe UI', sans-serif; color: #e2e8f0; }
        
        /* Layout Transitions */
        .sidebar { width: var(--sidebar-width); position: fixed; top: 0; left: 0; height: 100%; background: var(--bg-card); border-right: 1px solid #334155; z-index: 1000; transition: margin-left 0.3s ease; }
        .sidebar.collapsed { margin-left: calc(var(--sidebar-width) * -1); }
        
        .main-wrapper { margin-left: var(--sidebar-width); transition: margin-left 0.3s ease; }
        .main-wrapper.expanded { margin-left: 0; }
        
        /* Navigation Links */
        .nav-link { color: #94a3b8; padding: 12px 25px; display: flex; align-items: center; gap: 12px; font-weight: 500; transition: all 0.2s; border-left: 3px solid transparent; }
        .nav-link:hover { color: #fff; background: rgba(255,255,255,0.05); }
        .nav-link.active { color: var(--neon); background: rgba(0, 243, 255, 0.05); border-left-color: var(--neon); }
        .nav-link i { font-size: 1.1rem; }
        
        /* UI Components */
        .card { background: var(--bg-card); border: 1px solid #334155; color: #fff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .text-neon { color: var(--neon); }
        .btn-toggle { color: var(--neon); background: transparent; border: 1px solid rgba(0, 243, 255, 0.3); }
        
        /* Mobile overrides */
        @media (max-width: 768px) {
            .sidebar { margin-left: calc(var(--sidebar-width) * -1); }
            .sidebar.collapsed { margin-left: 0; } /* Invert logic for mobile if needed, or stick to desktop-first */
            .main-wrapper { margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
    <div class="d-flex align-items-center justify-content-center" style="height: var(--header-height); border-bottom: 1px solid #334155;">
        <h4 class="m-0 text-neon fw-black tracking-widest">SUROPARA</h4>
    </div>
    
    <div class="nav flex-column py-3" style="height: calc(100vh - 70px); overflow-y: auto;">
        <!-- MAIN -->
        <small class="text-uppercase text-muted fw-bold px-4 mb-2 mt-2" style="font-size: 0.7rem;">Main Menu</small>
        <a href="../../modules/dashboard/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'dashboard/index') ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2-fill"></i> Dashboard
        </a>
        <a href="../../modules/dashboard/live.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'dashboard/live') ? 'active' : '' ?>">
            <i class="bi bi-activity"></i> Live Monitor
        </a>
        <a href="../../modules/finance/queue.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'finance') ? 'active' : '' ?>">
            <i class="bi bi-wallet2"></i> Finance Queue
        </a>
        
        <!-- GAME -->
        <small class="text-uppercase text-muted fw-bold px-4 mt-3 mb-2" style="font-size: 0.7rem;">Game Management</small>
        <a href="../../modules/users/list.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'users') ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i> Players
        </a>
        <a href="../../modules/machines/monitor.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'machines/monitor') ? 'active' : '' ?>">
            <i class="bi bi-joystick"></i> Game Floor
        </a>
        <a href="../../modules/machines/manage.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'machines/manage') ? 'active' : '' ?>">
            <i class="bi bi-box-seam"></i> Machine Inventory
        </a>
        <a href="../../modules/machines/heatmap.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'heatmap') ? 'active' : '' ?>">
            <i class="bi bi-thermometer-high"></i> Floor Heatmap
        </a>
        
        <!-- CONTENT -->
        <small class="text-uppercase text-muted fw-bold px-4 mt-3 mb-2" style="font-size: 0.7rem;">Content & World</small>
        <a href="../../modules/islands/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'islands') ? 'active' : '' ?>">
            <i class="bi bi-map-fill"></i> Islands & RTP
        </a>
        <a href="../../modules/characters/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'characters') ? 'active' : '' ?>">
            <i class="bi bi-person-hearts"></i> Characters
        </a>
        <a href="../../modules/marketing/jackpots.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'jackpots') ? 'active' : '' ?>">
            <i class="bi bi-trophy-fill"></i> Jackpots
        </a>
        <a href="../../modules/marketing/events.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'events') ? 'active' : '' ?>">
            <i class="bi bi-calendar-event"></i> Events
        </a>

        <!-- SYSTEM -->
        <small class="text-uppercase text-muted fw-bold px-4 mt-3 mb-2" style="font-size: 0.7rem;">System</small>
        <a href="../../modules/settings/global.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'settings') ? 'active' : '' ?>">
            <i class="bi bi-sliders"></i> Global Config
        </a>
        <a href="../../modules/finance_config/methods.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'finance_config/methods') ? 'active' : '' ?>">
            <i class="bi bi-bank"></i> Payment Methods
        </a>
         <a href="../../modules/finance_config/limits.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'finance_config/limits') ? 'active' : '' ?>">
            <i class="bi bi-graph-up-arrow"></i> Withdrawal Limits
        </a>
        <a href="../../modules/staff/manage.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'staff') ? 'active' : '' ?>">
            <i class="bi bi-shield-lock-fill"></i> Staff Access
        </a>
    </div>
</div>

<!-- MAIN CONTENT WRAPPER -->
<div class="main-wrapper" id="mainWrapper">
    
    <!-- TOP HEADER -->
    <div class="px-4 py-3 d-flex justify-content-between align-items-center" style="height: var(--header-height); background: rgba(30, 41, 59, 0.95); border-bottom: 1px solid #334155; position: sticky; top: 0; z-index: 999;">
        
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary border-0" id="sidebarToggle" onclick="toggleSidebar()">
                <i class="bi bi-list fs-4"></i>
            </button>
            <h5 class="m-0 text-white fw-bold d-none d-md-block"><?= $pageTitle ?? 'Admin Portal' ?></h5>
        </div>

        <div class="d-flex align-items-center gap-4">
            
            <!-- SYSTEM STATUS INDICATOR -->
            <div class="d-flex align-items-center gap-2 bg-dark px-3 py-1 rounded-pill border border-secondary">
                <span class="badge bg-success rounded-circle p-1"><span class="visually-hidden">Online</span></span>
                <span class="text-white small fw-bold" style="font-size: 0.75rem; letter-spacing: 1px;">SYSTEM LIVE</span>
            </div>

            <div class="vr text-secondary h-50"></div>

            <!-- ADMIN PROFILE -->
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                    <div class="bg-gradient text-white rounded-circle d-flex align-items-center justify-content-center me-2 fw-bold" style="width: 35px; height: 35px; background: linear-gradient(45deg, #00f3ff, #0066ff);">
                        <?= strtoupper(substr($adminUser, 0, 1)) ?>
                    </div>
                    <div class="d-none d-md-block text-end me-1">
                        <div class="text-white small fw-bold" style="line-height: 1;"><?= htmlspecialchars($adminUser) ?></div>
                        <div class="text-neon" style="font-size: 0.65rem;"><?= htmlspecialchars($adminRole) ?></div>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow-lg border-secondary mt-2">
                    <li><h6 class="dropdown-header">Logged in as <?= htmlspecialchars($adminUser) ?></h6></li>
                    <li><a class="dropdown-item" href="../../modules/staff/logs.php"><i class="bi bi-activity me-2"></i> Audit Logs</a></li>
                    <li><a class="dropdown-item" href="../../modules/finance/reports.php"><i class="bi bi-file-earmark-bar-graph me-2"></i> Reports</a></li>
                    <li><hr class="dropdown-divider border-secondary"></li>
                    <li><a class="dropdown-item text-danger" href="../../index.php?logout=true"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- PAGE CONTENT START -->
    <div class="p-4">

    <!-- Sidebar Toggle Script -->
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('mainWrapper').classList.toggle('expanded');
        }
    </script>