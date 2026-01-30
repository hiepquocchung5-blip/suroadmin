<?php
/**
 * Advanced Admin Panel Layout - Main Wrapper
 * Optimized for Security, Database Connectivity, and Modern UI/UX.
 */

// Core Requirements
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Strict Auth Check - Ensure only authorized admins can access
requireAuth();

// Safe Session Access
$adminUser = $_SESSION['admin_username'] ?? 'Admin';
$adminRole = $_SESSION['admin_role'] ?? 'STAFF';

// Security: Headers
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

// CSRF Token Management
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Module Detection
$current_module = $_GET['module'] ?? 'dashboard';

// Path Handling Logic - SAFE VERSION
// Logic: Ensure depth is never negative to prevent str_repeat errors
$pathDepth = max(0, substr_count($_SERVER['PHP_SELF'], '/') - 2); 
$baseUrl = str_repeat('../', $pathDepth);
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#f8fafc]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SURO Admin | <?php echo strtoupper($current_module); ?></title>
    
    <!-- Security: CSRF Token for JS -->
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    
    <!-- UI Assets -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        /* Glassmorphism & Custom Effects */
        .glass-panel {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        
        .sidebar-collapsed { width: 80px !important; }
        .sidebar-collapsed span, .sidebar-collapsed .nav-label { display: none; }
        .sidebar-collapsed .nav-group-title { opacity: 0; }
        
        .nav-item-active {
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            color: white !important;
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.25);
        }

        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }

        @keyframes pulse-ring {
            0% { transform: scale(.33); opacity: 1; }
            80%, 100% { opacity: 0; }
        }
        .status-pulse {
            position: relative;
        }
        .status-pulse:before {
            content: '';
            position: absolute;
            width: 100%; height: 100%;
            background-color: #10b981;
            border-radius: 50%;
            animation: pulse-ring 1.5s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite;
        }
    </style>
</head>
<body class="h-full overflow-hidden antialiased text-slate-900">

<div class="flex h-screen overflow-hidden">
    <!-- Desktop Sidebar -->
    <aside id="desktop-sidebar" class="hidden lg:flex flex-col w-72 bg-[#0f172a] text-slate-400 transition-all duration-300 ease-in-out border-r border-white/5 relative z-50">
        <!-- Logo Area -->
        <div class="h-20 flex items-center px-6 border-b border-white/5 flex-shrink-0">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center mr-3 shadow-lg shadow-blue-600/20">
                <i data-lucide="shield-check" class="w-6 h-6 text-white"></i>
            </div>
            <span class="text-lg font-extrabold text-white tracking-tight uppercase nav-label">Suro <span class="text-blue-500">Panel</span></span>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-4 py-6 space-y-8 overflow-y-auto custom-scrollbar">
            <!-- Group 1: Monitoring -->
            <div class="space-y-1">
                <p class="px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-4 nav-group-title transition-opacity">Main Dashboard</p>
                <a href="index.php?module=dashboard" class="flex items-center px-4 py-3 rounded-xl hover:bg-white/5 hover:text-white transition-all <?php echo $current_module == 'dashboard' ? 'nav-item-active' : ''; ?>">
                    <i data-lucide="layout-grid" class="w-5 h-5 mr-3"></i>
                    <span class="font-semibold text-sm">Overview</span>
                </a>
                <a href="index.php?module=live" class="flex items-center px-4 py-3 rounded-xl hover:bg-white/5 hover:text-white transition-all <?php echo $current_module == 'live' ? 'nav-item-active' : ''; ?>">
                    <i data-lucide="activity" class="w-5 h-5 mr-3"></i>
                    <span class="font-semibold text-sm">Live Traffic</span>
                </a>
            </div>

            <!-- Group 2: Management -->
            <div class="space-y-1">
                <p class="px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-4 nav-group-title transition-opacity">Economics</p>
                <a href="index.php?module=users" class="flex items-center px-4 py-3 rounded-xl hover:bg-white/5 hover:text-white transition-all <?php echo $current_module == 'users' ? 'nav-item-active' : ''; ?>">
                    <i data-lucide="users" class="w-5 h-5 mr-3"></i>
                    <span class="font-semibold text-sm">Users</span>
                </a>
                <a href="index.php?module=finance" class="flex items-center px-4 py-3 rounded-xl hover:bg-white/5 hover:text-white transition-all <?php echo $current_module == 'finance' ? 'nav-item-active' : ''; ?>">
                    <i data-lucide="landmark" class="w-5 h-5 mr-3"></i>
                    <span class="font-semibold text-sm">Finance Queue</span>
                </a>
                <a href="index.php?module=machines" class="flex items-center px-4 py-3 rounded-xl hover:bg-white/5 hover:text-white transition-all <?php echo $current_module == 'machines' ? 'nav-item-active' : ''; ?>">
                    <i data-lucide="cpu" class="w-5 h-5 mr-3"></i>
                    <span class="font-semibold text-sm">Machines</span>
                </a>
            </div>

            <!-- Group 3: System -->
            <div class="space-y-1">
                <p class="px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-4 nav-group-title transition-opacity">System</p>
                <a href="index.php?module=settings" class="flex items-center px-4 py-3 rounded-xl hover:bg-white/5 hover:text-white transition-all <?php echo $current_module == 'settings' ? 'nav-item-active' : ''; ?>">
                    <i data-lucide="settings-2" class="w-5 h-5 mr-3"></i>
                    <span class="font-semibold text-sm">Global Config</span>
                </a>
                <a href="index.php?module=security" class="flex items-center px-4 py-3 rounded-xl hover:bg-white/5 hover:text-white transition-all <?php echo $current_module == 'security' ? 'nav-item-active' : ''; ?>">
                    <i data-lucide="lock" class="w-5 h-5 mr-3"></i>
                    <span class="font-semibold text-sm">Security Hub</span>
                </a>
            </div>
        </nav>

        <!-- Sidebar Footer -->
        <div class="p-4 border-t border-white/5 bg-black/20">
            <div class="flex items-center p-3 space-x-3 bg-white/5 rounded-2xl mb-4 nav-label">
                <div class="w-8 h-8 rounded-lg bg-indigo-500/20 flex items-center justify-center text-indigo-400">
                    <i data-lucide="user" class="w-4 h-4"></i>
                </div>
                <div class="overflow-hidden">
                    <p class="text-xs font-bold text-white truncate"><?php echo htmlspecialchars($adminUser); ?></p>
                    <p class="text-[10px] text-slate-500 font-bold"><?php echo $adminRole; ?></p>
                </div>
            </div>
            <button onclick="toggleSidebarCollapse()" class="w-full flex items-center justify-center py-2 text-slate-500 hover:text-white transition-colors">
                <i id="collapse-icon" data-lucide="chevron-left" class="w-5 h-5"></i>
            </button>
        </div>
    </aside>

    <!-- Mobile Drawer -->
    <div id="mobile-sidebar-backdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] hidden lg:hidden" onclick="toggleMobileMenu()"></div>
    <aside id="mobile-sidebar" class="fixed top-0 left-0 bottom-0 w-72 bg-[#0f172a] z-[70] transform -translate-x-full transition-transform duration-300 lg:hidden flex flex-col">
        <div class="p-6 flex items-center justify-between border-b border-white/5">
            <span class="text-xl font-black text-white uppercase tracking-tighter">SURO <span class="text-blue-500">ADMIN</span></span>
            <button onclick="toggleMobileMenu()" class="text-slate-400"><i data-lucide="x" class="w-6 h-6"></i></button>
        </div>
        <div id="mobile-nav-content" class="flex-1 overflow-y-auto p-4">
            <!-- Content will be mirrored from desktop via JS -->
        </div>
    </aside>

    <!-- Content Area -->
    <main class="flex-1 flex flex-col overflow-hidden">
        <!-- Main Header -->
        <header class="h-20 glass-panel border-b border-slate-200/60 flex items-center justify-between px-6 md:px-10 flex-shrink-0 z-40">
            <div class="flex items-center space-x-4">
                <button onclick="toggleMobileMenu()" class="lg:hidden p-2.5 text-slate-600 bg-white border border-slate-200 rounded-xl shadow-sm">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                
                <div class="hidden md:flex flex-col">
                    <h2 class="text-lg font-extrabold text-slate-800 leading-none"><?php echo strtoupper($current_module); ?></h2>
                    <div class="flex items-center mt-1.5 space-x-2">
                        <div class="w-2 h-2 rounded-full bg-emerald-500 status-pulse"></div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Network Secure</span>
                    </div>
                </div>
            </div>

            <!-- Action Bar -->
            <div class="flex items-center space-x-3 sm:space-x-6">
                <!-- Search & Alerts (Visual only) -->
                <div class="hidden sm:flex items-center space-x-2 bg-slate-100/80 p-1.5 rounded-2xl border border-slate-200/50">
                    <button class="p-2 text-slate-400 hover:text-blue-600 transition-colors"><i data-lucide="search" class="w-5 h-5"></i></button>
                    <button class="p-2 text-slate-400 hover:text-blue-600 transition-colors relative">
                        <i data-lucide="bell" class="w-5 h-5"></i>
                        <span class="absolute top-2 right-2.5 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
                    </button>
                </div>

                <!-- Profile Dropdown Placeholder -->
                <div class="flex items-center space-x-3 pl-4 border-l border-slate-200">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 p-[2px] shadow-lg shadow-blue-200">
                        <div class="w-full h-full bg-white rounded-[14px] flex items-center justify-center font-black text-blue-600">
                            <?php echo strtoupper(substr($adminUser, 0, 2)); ?>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content Container -->
        <div id="content-container" class="flex-1 overflow-y-auto p-4 md:p-10 custom-scrollbar bg-[#f8fafc]">
            <div class="max-w-7xl mx-auto space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-700">