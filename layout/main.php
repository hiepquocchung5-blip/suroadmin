<?php
// Prevent direct access to layout file
if (!defined('ADMIN_BASE_PATH')) exit;
$currentRoute = $_GET['route'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $pageTitle ?? 'Suropara Admin' ?> | System Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Anti-Flash Script (Runs before CSS paints) -->
    <script>
        const savedTheme = localStorage.getItem('admin_theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    </script>

    <style>
        /* =========================================================
           V2 THEME ENGINE: LUXURY LIGHT & GREY MARKETING DARK
           ========================================================= */
        :root { 
            --sidebar-width: 250px; 
            --header-height: 60px; 
        }

        /* 1. LUXURY LIGHT MODE (Default) */
        [data-bs-theme="light"] {
            --bg-main: #f5f6f8;
            --bg-sidebar: #ffffff;
            --bg-card: rgba(255, 255, 255, 0.95);
            --bg-header: #ffffff;
            --text-main: #2d3436;
            --text-muted: #64748b;
            --accent: #C5A059; /* Luxury Gold */
            --accent-hover: #b08d4a;
            --border-color: rgba(197, 160, 89, 0.3);
            --card-shadow: 0 10px 30px rgba(0,0,0,0.04);
            --sidebar-border: #e2e8f0;
        }

        /* 2. GREY MARKETING DARK MODE */
        [data-bs-theme="dark"] {
            --bg-main: #0f1115;
            --bg-sidebar: #15181d;
            --bg-card: rgba(30, 34, 40, 0.85);
            --bg-header: #111317;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #00f3ff; /* Neon Cyan */
            --accent-hover: #00d2dd;
            --border-color: rgba(255, 255, 255, 0.08);
            --card-shadow: 0 10px 30px rgba(0,0,0,0.5);
            --sidebar-border: rgba(255,255,255,0.05);
        }

        body { 
            background-color: var(--bg-main); 
            color: var(--text-main); 
            font-family: 'Inter', system-ui, sans-serif; 
            overflow-x: hidden; 
            transition: background-color 0.3s, color 0.3s;
        }
        
        /* Layout Transitions */
        .sidebar { 
            width: var(--sidebar-width); 
            position: fixed; 
            top: 0; 
            left: 0; 
            height: 100vh; 
            background: var(--bg-sidebar); 
            border-right: 1px solid var(--sidebar-border); 
            z-index: 1000; 
            overflow-y: auto;
            transition: background-color 0.3s;
        }
        .main-wrapper { 
            margin-left: var(--sidebar-width); 
            min-height: 100vh; 
            display: flex; 
            flex-direction: column; 
        }
        
        /* Glass Components */
        .glass-card { 
            background: var(--bg-card); 
            backdrop-filter: blur(12px); 
            border: 1px solid var(--border-color); 
            border-radius: 12px; 
            box-shadow: var(--card-shadow);
            transition: all 0.3s;
        }
        
        /* Navigation */
        .nav-category { font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; padding: 15px 20px 5px; }
        .nav-link { color: var(--text-muted); padding: 10px 20px; display: flex; align-items: center; gap: 12px; font-size: 0.85rem; border-left: 3px solid transparent; transition: all 0.2s; }
        .nav-link:hover { color: var(--text-main); background: rgba(128,128,128,0.05); }
        .nav-link.active { color: var(--accent); background: rgba(128,128,128,0.05); border-left-color: var(--accent); font-weight: 600; }
        
        /* --- AUTO-OVERRIDE HACKS FOR LIGHT MODE ---
           This magic block converts all the hardcoded Bootstrap "bg-dark" and "text-white" 
           classes in your modules into luxury light mode equivalents automatically! */
        [data-bs-theme="light"] .bg-dark,
        [data-bs-theme="light"] .bg-black,
        [data-bs-theme="light"] .card.bg-dark {
            background-color: #ffffff !important;
        }
        [data-bs-theme="light"] .text-white {
            color: #2d3436 !important;
        }
        [data-bs-theme="light"] .text-gray-400,
        [data-bs-theme="light"] .text-gray-500,
        [data-bs-theme="light"] .text-muted {
            color: #64748b !important;
        }
        [data-bs-theme="light"] .border-secondary,
        [data-bs-theme="light"] .border-white\/10 {
            border-color: #e2e8f0 !important;
        }
        [data-bs-theme="light"] .table-dark {
            --bs-table-bg: #ffffff;
            --bs-table-striped-bg: #f8fafc;
            --bs-table-color: #2d3436;
            --bs-table-border-color: #e2e8f0;
            color: #2d3436;
        }
        /* Map Info/Cyan colors to Luxury Gold */
        [data-bs-theme="light"] .text-info,
        [data-bs-theme="light"] .text-cyan-400 {
            color: var(--accent) !important;
        }
        [data-bs-theme="light"] .btn-info,
        [data-bs-theme="light"] .btn-outline-info {
            background-color: var(--accent) !important;
            border-color: var(--accent) !important;
            color: #fff !important;
        }
        [data-bs-theme="light"] .bg-info.bg-opacity-10 {
            background-color: rgba(197, 160, 89, 0.1) !important;
        }
        [data-bs-theme="light"] .form-control.bg-dark,
        [data-bs-theme="light"] .form-select.bg-dark,
        [data-bs-theme="light"] .form-control.bg-black {
            background-color: #f8fafc !important;
            color: #2d3436 !important;
            border-color: #cbd5e1 !important;
        }

        /* Strict Mobile Landscape Enforcer */
        #rotate-device-overlay { display: none; position: fixed; inset: 0; background: var(--bg-main); z-index: 9999; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 20px; }
        @media screen and (max-width: 800px) and (orientation: portrait) {
            #rotate-device-overlay { display: flex; }
            .sidebar, .main-wrapper { display: none; }
        }
    </style>
</head>
<body>

<!-- MOBILE ENFORCEMENT -->
<div id="rotate-device-overlay">
    <i class="bi bi-phone-landscape mb-4" style="font-size: 4rem; color: var(--accent);"></i>
    <h3 class="fw-black tracking-widest uppercase" style="color: var(--text-main);">Rotate Device</h3>
    <p class="small mt-2 max-w-sm" style="color: var(--text-muted);">The Leviathan Admin Portal requires a wide workspace to display high-density telemetry data.</p>
</div>

<!-- SIDEBAR -->
<div class="sidebar hide-scrollbar">
    <div class="d-flex align-items-center justify-content-center" style="height: var(--header-height); border-bottom: 1px solid var(--sidebar-border); position: sticky; top:0; background: var(--bg-sidebar); z-index: 10;">
        <h5 class="m-0 fw-black tracking-widest" style="color: var(--accent);"><i class="bi bi-cpu"></i> SUROPARA V2</h5>
    </div>
    
    <div class="nav flex-column pb-5 mt-2">
        <div class="nav-category">Core</div>
        <a href="?route=dashboard" class="nav-link <?= $currentRoute == 'dashboard' ? 'active' : '' ?>"><i class="bi bi-grid-1x2"></i> Dashboard</a>
        <a href="?route=live" class="nav-link <?= $currentRoute == 'live' ? 'active' : '' ?>"><i class="bi bi-activity"></i> Live Stream</a>
        
        <div class="nav-category">Finance</div>
        <a href="?route=finance/queue" class="nav-link <?= $currentRoute == 'finance/queue' ? 'active' : '' ?>"><i class="bi bi-bank"></i> Queue</a>
        <a href="?route=finance/reports" class="nav-link <?= $currentRoute == 'finance/reports' ? 'active' : '' ?>"><i class="bi bi-graph-up"></i> Reports</a>
        
        <div class="nav-category">Fleet Command</div>
        <a href="?route=fleet/monitor" class="nav-link <?= $currentRoute == 'fleet/monitor' ? 'active' : '' ?>"><i class="bi bi-joystick"></i> Map Monitor</a>
        <a href="?route=fleet/manage" class="nav-link <?= $currentRoute == 'fleet/manage' ? 'active' : '' ?>"><i class="bi bi-box-seam"></i> Inventory</a>
        <a href="?route=fleet/heatmap" class="nav-link <?= $currentRoute == 'fleet/heatmap' ? 'active' : '' ?>"><i class="bi bi-thermometer-high"></i> Heatmap</a>
        
        <div class="nav-category">Player Base</div>
        <a href="?route=players/list" class="nav-link <?= $currentRoute == 'players/list' ? 'active' : '' ?>"><i class="bi bi-people"></i> Directory</a>
        <a href="?route=players/whales" class="nav-link <?= $currentRoute == 'players/whales' ? 'active' : '' ?>"><i class="bi bi-gem"></i> VIP Whales</a>
        <a href="?route=players/agents" class="nav-link <?= $currentRoute == 'players/agents' ? 'active' : '' ?>"><i class="bi bi-diagram-3"></i> Affiliates</a>
        
        <div class="nav-category">World Engine</div>
        <a href="?route=content/islands" class="nav-link <?= $currentRoute == 'content/islands' ? 'active' : '' ?>"><i class="bi bi-map"></i> Islands & RTP</a>
        <a href="?route=marketing/jackpots" class="nav-link <?= $currentRoute == 'marketing/jackpots' ? 'active' : '' ?>"><i class="bi bi-trophy"></i> Jackpots</a>
        
        <div class="nav-category">System</div>
        <a href="?route=security/monitor" class="nav-link <?= $currentRoute == 'security/monitor' ? 'active' : '' ?>"><i class="bi bi-shield-lock"></i> Security</a>
        <a href="?route=staff/manage" class="nav-link <?= $currentRoute == 'staff/manage' ? 'active' : '' ?>"><i class="bi bi-person-badge"></i> Staff</a>
        <a href="?route=config/global" class="nav-link <?= $currentRoute == 'config/global' ? 'active' : '' ?>"><i class="bi bi-sliders"></i> Configuration</a>
    </div>
</div>

<!-- MAIN WRAPPER -->
<div class="main-wrapper">
    <!-- TOP HEADER -->
    <div class="px-4 d-flex justify-content-between align-items-center border-bottom sticky-top z-30" style="height: var(--header-height); background: var(--bg-header); border-color: var(--sidebar-border) !important;">
        <h5 class="m-0 fw-bold" style="color: var(--text-main);"><?= $pageTitle ?? 'Control Panel' ?></h5>
        
        <div class="d-flex align-items-center gap-3">
            
            <!-- THEME TOGGLE BUTTON -->
            <button onclick="toggleTheme()" class="btn btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main);">
                <i id="theme-icon" class="bi bi-moon-stars-fill"></i>
            </button>

            <div class="d-flex align-items-center gap-2 px-3 py-1 rounded-pill" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                <span class="spinner-grow spinner-grow-sm text-success" style="width: 8px; height: 8px;"></span>
                <span class="fw-bold font-mono" style="font-size: 0.65rem; color: var(--text-main);">SECURE CONNECTION</span>
            </div>
            <a href="?logout=true" class="btn btn-sm btn-outline-danger fw-bold"><i class="bi bi-power"></i> DISCONNECT</a>
        </div>
    </div>

    <script>
        // Set the initial icon based on what loaded
        const currentDataTheme = document.documentElement.getAttribute('data-bs-theme');
        document.getElementById('theme-icon').className = currentDataTheme === 'dark' ? 'bi bi-brightness-high-fill' : 'bi bi-moon-stars-fill';

        function toggleTheme() {
            const html = document.documentElement;
            const icon = document.getElementById('theme-icon');
            
            if (html.getAttribute('data-bs-theme') === 'dark') {
                html.setAttribute('data-bs-theme', 'light');
                localStorage.setItem('admin_theme', 'light');
                icon.className = 'bi bi-moon-stars-fill'; // Next action is to turn dark
            } else {
                html.setAttribute('data-bs-theme', 'dark');
                localStorage.setItem('admin_theme', 'dark');
                icon.className = 'bi bi-brightness-high-fill'; // Next action is to turn light
            }
        }
    </script>

    <!-- PAGE CONTENT -->
    <div class="p-4 flex-grow-1">