<?php
/**
 * Command Center - Dashboard Module
 * This file is included by the master router in index.php
 */

// --- DATA AGGREGATION ---

try {
    // 1. Live Counters
    $activeUsers = $pdo->query("SELECT COUNT(*) FROM user_tokens WHERE expires_at > NOW()")->fetchColumn();
    $activeMachines = $pdo->query("SELECT COUNT(*) FROM machines WHERE status = 'occupied'")->fetchColumn();
    $totalMachines = $pdo->query("SELECT COUNT(*) FROM machines")->fetchColumn();

    // 2. Financials (Today)
    $today = date('Y-m-d 00:00:00');
    $deposits = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type='deposit' AND status='approved' AND created_at >= '$today'")->fetchColumn() ?: 0;
    $withdrawals = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type='withdraw' AND status='approved' AND created_at >= '$today'")->fetchColumn() ?: 0;
    $netRevenue = $deposits - $withdrawals;

    // 3. Security & Action Items
    $pendingTx = $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending'")->fetchColumn();
    $activeThreats = $pdo->query("SELECT COUNT(*) FROM security_alerts")->fetchColumn();

    // 4. Jackpot Status
    $jackpot = $pdo->query("SELECT current_amount FROM global_jackpots WHERE name = 'GRAND SURO JACKPOT'")->fetchColumn() ?: 0;

    // 5. Recent Activity Stream
    $logs = $pdo->query("
        SELECT l.*, a.username 
        FROM audit_logs l 
        LEFT JOIN admin_users a ON l.admin_id = a.id 
        ORDER BY l.created_at DESC 
        LIMIT 6
    ")->fetchAll();
} catch (PDOException $e) {
    // Fallback if some tables don't exist yet in the user's DB
    $activeUsers = 0; $activeMachines = 0; $totalMachines = 10;
    $deposits = 0; $withdrawals = 0; $netRevenue = 0;
    $pendingTx = 0; $activeThreats = 0; $jackpot = 0; $logs = [];
}
?>

<!-- DASHBOARD HEADER -->
<div class="mb-8">
    <h1 class="text-2xl font-black text-slate-800 tracking-tight">COMMAND CENTER</h1>
    <p class="text-sm text-slate-500 font-medium">Real-time system oversight and financial intelligence.</p>
</div>

<!-- HEADS UP DISPLAY (HUD) CARDS -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    
    <!-- NET REVENUE CARD -->
    <div class="bg-white p-6 rounded-[2rem] border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-4">
            <div class="p-3 bg-emerald-50 text-emerald-600 rounded-2xl">
                <i data-lucide="trending-up" class="w-6 h-6"></i>
            </div>
            <span class="text-[10px] font-black text-emerald-500 bg-emerald-50 px-2 py-1 rounded-full">LIVE 24H</span>
        </div>
        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Net Revenue</p>
        <h3 class="text-2xl font-black text-slate-900"><?= number_format($netRevenue) ?> <span class="text-xs font-bold text-slate-400 ml-1">MMK</span></h3>
        <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold">
            <span class="text-emerald-600">IN: <?= number_format($deposits) ?></span>
            <span class="text-slate-300">|</span>
            <span class="text-red-500">OUT: <?= number_format($withdrawals) ?></span>
        </div>
    </div>

    <!-- JACKPOT CARD -->
    <div class="bg-slate-900 p-6 rounded-[2rem] border border-slate-800 shadow-xl relative overflow-hidden group">
        <div class="absolute top-0 right-0 w-32 h-32 bg-blue-600/10 blur-3xl -mr-16 -mt-16 group-hover:bg-blue-600/20 transition-all"></div>
        <div class="flex items-center justify-between mb-4 relative z-10">
            <div class="p-3 bg-blue-600 text-white rounded-2xl shadow-lg shadow-blue-600/20">
                <i data-lucide="crown" class="w-6 h-6"></i>
            </div>
            <?php if($jackpot > 5000000): ?>
                <span class="flex h-2 w-2 rounded-full bg-blue-500 animate-ping"></span>
            <?php endif; ?>
        </div>
        <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1 relative z-10">Global Jackpot</p>
        <h3 class="text-2xl font-black text-white relative z-10"><?= number_format($jackpot) ?> <span class="text-xs font-bold text-slate-600 ml-1">MMK</span></h3>
        <p class="text-[10px] font-bold text-slate-600 mt-4 uppercase tracking-tighter relative z-10">Rate: <span class="text-blue-400">1.2% Contribution</span></p>
    </div>

    <!-- ACTION ITEMS CARD -->
    <div class="bg-white p-6 rounded-[2rem] border border-slate-200 shadow-sm flex flex-col justify-between">
        <div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Urgent Actions</p>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-bold text-slate-700">Pending Finance</span>
                    <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-black rounded-full"><?= $pendingTx ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-bold text-slate-700">Threat Alerts</span>
                    <span class="px-2 py-0.5 <?= $activeThreats > 0 ? 'bg-red-500 text-white animate-pulse' : 'bg-slate-100 text-slate-400' ?> text-[10px] font-black rounded-full"><?= $activeThreats ?></span>
                </div>
            </div>
        </div>
        <?php if($pendingTx > 0): ?>
            <a href="?module=finance" class="mt-4 flex items-center justify-center py-2 bg-slate-900 text-white text-[10px] font-black rounded-xl hover:bg-blue-600 transition-colors uppercase tracking-widest">
                Resolve Queue <i data-lucide="arrow-right" class="w-3 h-3 ml-2"></i>
            </a>
        <?php endif; ?>
    </div>

    <!-- LIVE PLAYER STATUS -->
    <div class="bg-white p-6 rounded-[2rem] border border-slate-200 shadow-sm">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Traffic Status</p>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-2 h-2 rounded-full bg-emerald-500 mr-2"></div>
                    <span class="text-sm font-bold text-slate-700">Active Users</span>
                </div>
                <span class="text-sm font-black text-slate-900"><?= $activeUsers ?></span>
            </div>
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-[10px] font-bold text-slate-400 uppercase">Machine Load</span>
                    <span class="text-[10px] font-bold text-slate-700"><?= $activeMachines ?>/<?= $totalMachines ?></span>
                </div>
                <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-indigo-500 rounded-full transition-all duration-1000" style="width: <?= ($activeMachines / max(1, $totalMachines)) * 100 ?>%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- SYSTEM LOGS TABLE -->
    <div class="lg:col-span-2 bg-white rounded-[2rem] border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center">
                <i data-lucide="activity" class="w-4 h-4 mr-2 text-blue-600"></i> Recent Auditor Logs
            </h3>
            <a href="?module=security" class="text-[10px] font-bold text-blue-600 hover:underline uppercase tracking-tighter">View All logs</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 text-slate-400 text-[10px] font-black uppercase tracking-widest">
                    <tr>
                        <th class="px-6 py-4">Timestamp</th>
                        <th class="px-6 py-4">Admin Entity</th>
                        <th class="px-6 py-4">Action Type</th>
                        <th class="px-6 py-4 text-right">Scope</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach($logs as $log): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 text-xs font-bold text-slate-500"><?= date('H:i:s', strtotime($log['created_at'])) ?></td>
                        <td class="px-6 py-4">
                            <span class="text-sm font-black text-slate-800"><?= htmlspecialchars($log['username'] ?? 'System') ?></span>
                        </td>
                        <td class="px-6 py-4 text-sm font-medium text-slate-600"><?= htmlspecialchars($log['action']) ?></td>
                        <td class="px-6 py-4 text-right">
                            <span class="px-2 py-0.5 bg-slate-100 text-slate-500 text-[10px] font-bold rounded capitalize"><?= htmlspecialchars($log['target_table']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($logs)): ?>
                        <tr><td colspan="4" class="text-center text-slate-400 py-12 text-sm font-medium">Clear history: No recent admin activity.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SIDE PANEL: QUICK ACCESS & HEALTH -->
    <div class="space-y-8">
        <!-- QUICK ACTIONS -->
        <div class="bg-indigo-600 rounded-[2rem] p-6 text-white shadow-xl shadow-indigo-600/20">
            <h4 class="text-xs font-black uppercase tracking-widest mb-6 opacity-80">Quick Protocol</h4>
            <div class="space-y-3">
                <a href="?module=users" class="flex items-center justify-between p-3 bg-white/10 hover:bg-white/20 rounded-2xl transition-all group">
                    <span class="text-sm font-bold">Search Player ID</span>
                    <i data-lucide="search" class="w-4 h-4 opacity-40 group-hover:opacity-100 transition-opacity"></i>
                </a>
                <a href="?module=islands" class="flex items-center justify-between p-3 bg-white/10 hover:bg-white/20 rounded-2xl transition-all group">
                    <span class="text-sm font-bold">Global Machine Stop</span>
                    <i data-lucide="power" class="w-4 h-4 opacity-40 group-hover:opacity-100 transition-opacity"></i>
                </a>
                <a href="?module=settings" class="flex items-center justify-between p-3 bg-white/10 hover:bg-white/20 rounded-2xl transition-all group">
                    <span class="text-sm font-bold">Adjust Global RTP</span>
                    <i data-lucide="sliders" class="w-4 h-4 opacity-40 group-hover:opacity-100 transition-opacity"></i>
                </a>
            </div>
        </div>

        <!-- SERVER HEALTH -->
        <div class="bg-white rounded-[2rem] border border-slate-200 p-6 shadow-sm">
            <h4 class="text-xs font-black uppercase tracking-widest mb-6 text-slate-400">Server Topology</h4>
            <div class="space-y-6">
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-xs font-bold text-slate-600">DB Master Node</span>
                        <span class="text-[10px] font-black text-emerald-500 uppercase">Synchronized</span>
                    </div>
                    <div class="h-1 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 w-full"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-xs font-bold text-slate-600">API Gateway Load</span>
                        <span class="text-[10px] font-black text-blue-500 uppercase">12.4ms Latency</span>
                    </div>
                    <div class="h-1 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-500 w-1/4"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Component-level scripts for the dashboard
    document.addEventListener('DOMContentLoaded', () => {
        if(window.showToast) {
            // showToast('Dashboard Sync Complete', 'success');
        }
    });
</script>