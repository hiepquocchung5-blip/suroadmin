<?php
// Prevent direct access to layout file
if (!defined('__DIR__')) exit;
$currentRoute = $_GET['route'] ?? 'dashboard';



?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $pageTitle ?? 'Suropara Admin' ?> | System Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root { --sidebar-width: 250px; --header-height: 60px; --neon: #00f3ff; }
        body { background-color: #050505; font-family: 'Inter', system-ui, sans-serif; color: #e2e8f0; overflow-x: hidden; }
        
        /* Layout Transitions */
        .sidebar { width: var(--sidebar-width); position: fixed; top: 0; left: 0; height: 100vh; background: #0a0a0a; border-right: 1px solid rgba(255,255,255,0.1); z-index: 1000; overflow-y: auto; }
        .main-wrapper { margin-left: var(--sidebar-width); min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Glass Components */
        .glass-card { background: rgba(20, 20, 25, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; }
        
        /* Navigation */
        .nav-category { font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; padding: 15px 20px 5px; }
        .nav-link { color: #94a3b8; padding: 10px 20px; display: flex; align-items: center; gap: 12px; font-size: 0.85rem; border-left: 3px solid transparent; transition: all 0.2s; }
        .nav-link:hover { color: #fff; background: rgba(255,255,255,0.05); }
        .nav-link.active { color: var(--neon); background: rgba(0, 243, 255, 0.1); border-left-color: var(--neon); font-weight: 600; }
        
        /* Strict Mobile Landscape Enforcer */
        #rotate-device-overlay { display: none; position: fixed; inset: 0; background: #000; z-index: 9999; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 20px; }
        @media screen and (max-width: 800px) and (orientation: portrait) {
            #rotate-device-overlay { display: flex; }
            .sidebar, .main-wrapper { display: none; }
        }
    </style>
</head>
<body>

<!-- MOBILE ENFORCEMENT -->
<div id="rotate-device-overlay">
    <i class="bi bi-phone-landscape text-info animate-pulse mb-4" style="font-size: 4rem;"></i>
    <h3 class="text-white fw-black tracking-widest uppercase">Rotate Device</h3>
    <p class="text-gray-400 small mt-2 max-w-sm">The Leviathan Admin Portal requires a wide workspace to display high-density telemetry data.</p>
</div>

<!-- SIDEBAR -->
<div class="sidebar hide-scrollbar">
    <div class="d-flex align-items-center justify-content-center" style="height: var(--header-height); border-bottom: 1px solid rgba(255,255,255,0.1); position: sticky; top:0; background:#0a0a0a; z-index: 10;">
        <h5 class="m-0 text-info fw-black tracking-widest"><i class="bi bi-cpu"></i> SUROPARA V2</h5>
    </div>
    
    <div class="nav flex-column pb-5">
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
    <div class="px-4 d-flex justify-content-between align-items-center bg-black border-bottom border-secondary border-opacity-50 sticky-top z-30" style="height: var(--header-height);">
        <h5 class="m-0 text-white fw-bold"><?= $pageTitle ?? 'Control Panel' ?></h5>
        
        <div class="d-flex align-items-center gap-4">
            <div class="d-flex align-items-center gap-2 bg-dark px-3 py-1 rounded-pill border border-secondary">
                <span class="spinner-grow spinner-grow-sm text-success" style="width: 8px; height: 8px;"></span>
                <span class="text-white fw-bold font-mono" style="font-size: 0.65rem;">SECURE CONNECTION</span>
            </div>
            <a href="?logout=true" class="btn btn-sm btn-outline-danger fw-bold"><i class="bi bi-power"></i> DISCONNECT</a>
        </div>
    </div>

    <!-- PAGE CONTENT -->
    <div class="p-4 flex-grow-1">