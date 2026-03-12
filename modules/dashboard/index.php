<?php
// Ensure this is loaded via the router
if (!defined('__DIR__')) exit;

$pageTitle = "Leviathan Command Center";
requireRole(['GOD', 'FINANCE']);
require_once __DIR__ . '/../../layout/main.php';

// --- DATA AGGREGATION ---

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
?>

<!-- HEADS UP DISPLAY (HUD) -->
<div class="row g-3 mb-4">
    <!-- REVENUE -->
    <div class="col-md-3">
        <div class="glass-card border-success h-100 position-relative overflow-hidden">
            <div class="position-absolute top-0 end-0 p-3 opacity-25">
                <i class="bi bi-graph-up-arrow display-4 text-success"></i>
            </div>
            <div class="card-body position-relative z-10">
                <h6 class="text-success small fw-bold mb-2 tracking-widest">NET REVENUE (24H)</h6>
                <h3 class="text-white fw-black mb-0 font-mono"><?= number_format($netRevenue) ?> <small class="fs-6 text-muted">MMK</small></h3>
                <div class="small mt-3 font-mono">
                    <span class="text-success bg-success bg-opacity-10 px-2 py-1 rounded"><i class="bi bi-arrow-down"></i> <?= number_format($deposits) ?></span>
                    <span class="text-danger bg-danger bg-opacity-10 px-2 py-1 rounded ms-1"><i class="bi bi-arrow-up"></i> <?= number_format($withdrawals) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- JACKPOT -->
    <div class="col-md-3">
        <div class="glass-card border-warning h-100 position-relative overflow-hidden">
            <div class="position-absolute top-0 end-0 p-3 opacity-25">
                <i class="bi bi-trophy display-4 text-warning"></i>
            </div>
            <div class="card-body position-relative z-10">
                <h6 class="text-warning small fw-bold mb-2 tracking-widest">GRAND JACKPOT</h6>
                <h3 class="text-white fw-black mb-0 font-mono text-shadow-sm"><?= number_format($jackpot) ?> <small class="fs-6 text-muted">MMK</small></h3>
                <div class="small text-muted mt-3">
                    Contribution: <span class="text-white font-mono">1.0% / Spin</span>
                </div>
            </div>
            <?php if($jackpot > 5000000): ?>
                <div class="card-footer bg-warning text-dark fw-bold text-center py-1 text-[10px] tracking-widest animate-pulse border-t-0">
                    <i class="bi bi-fire"></i> HIGH VALUE ALERT
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ACTION ITEMS -->
    <div class="col-md-3">
        <div class="glass-card border-info h-100 position-relative overflow-hidden">
            <div class="card-body position-relative z-10">
                <h6 class="text-info small fw-bold mb-3 tracking-widest">ACTION REQUIRED</h6>
                <div class="d-flex justify-content-between align-items-center mb-3 bg-black bg-opacity-50 p-2 rounded border border-white border-opacity-10">
                    <span class="text-white small"><i class="bi bi-bank text-info me-1"></i> Pending Tx</span>
                    <span class="badge bg-info text-dark rounded-pill font-mono"><?= $pendingTx ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center bg-black bg-opacity-50 p-2 rounded border border-white border-opacity-10">
                    <span class="text-white small"><i class="bi bi-shield-lock text-danger me-1"></i> Security Alerts</span>
                    <span class="badge <?= $activeThreats > 0 ? 'bg-danger animate-pulse shadow-[0_0_10px_red]' : 'bg-secondary' ?> rounded-pill font-mono"><?= $activeThreats ?></span>
                </div>
            </div>
            <?php if($pendingTx > 0): ?>
                <a href="?route=finance/queue" class="card-footer text-center text-info text-decoration-none text-[10px] tracking-widest fw-bold bg-info bg-opacity-10 border-t border-info hover:bg-opacity-20 transition-all">
                    PROCESS QUEUE <i class="bi bi-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- LIVE STATUS -->
    <div class="col-md-3">
        <div class="glass-card border-secondary h-100">
            <div class="card-body">
                <h6 class="text-muted small fw-bold mb-3 tracking-widest">FLEET STATUS</h6>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-white small"><i class="bi bi-person-fill text-success me-1"></i> Online Players</span>
                    <span class="fw-bold font-mono text-success bg-success bg-opacity-10 px-2 py-0.5 rounded"><?= $activeUsers ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-white small"><i class="bi bi-joystick text-warning me-1"></i> Active Units</span>
                    <span class="fw-bold font-mono text-warning bg-warning bg-opacity-10 px-2 py-0.5 rounded"><?= $activeMachines ?> <small class="text-muted">/ <?= $totalMachines ?></small></span>
                </div>
                
                <div class="progress mt-4 bg-dark border border-secondary" style="height: 6px;">
                    <div class="progress-bar bg-success shadow-[0_0_10px_lime]" style="width: <?= ($activeMachines / max(1, $totalMachines)) * 100 ?>%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- SYSTEM ACTIVITY -->
    <div class="col-md-8">
        <div class="glass-card border-secondary h-100 p-0 overflow-hidden">
            <div class="card-header bg-black bg-opacity-40 border-b border-white border-opacity-10 d-flex justify-content-between align-items-center p-3">
                <span class="text-white fw-bold tracking-widest italic"><i class="bi bi-activity text-cyan-400 me-2"></i> SYSTEM AUDIT TRAIL</span>
                <a href="?route=staff/logs" class="btn btn-sm btn-outline-secondary text-[10px] fw-bold rounded-pill">VIEW ALL</a>
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead>
                        <tr class="text-gray-500 text-uppercase text-[10px] tracking-widest">
                            <th class="ps-4">Time</th>
                            <th>Admin</th>
                            <th>Action</th>
                            <th class="pe-4 text-end">Target</th>
                        </tr>
                    </thead>
                    <tbody class="font-mono text-xs">
                        <?php foreach($logs as $log): ?>
                        <tr>
                            <td class="text-muted ps-4"><?= date('H:i:s', strtotime($log['created_at'])) ?></td>
                            <td class="fw-bold text-cyan-400">
                                <i class="bi bi-person-badge me-1"></i> <?= htmlspecialchars($log['username'] ?? 'System') ?>
                            </td>
                            <td class="text-gray-300"><?= htmlspecialchars($log['action']) ?></td>
                            <td class="pe-4 text-end">
                                <span class="badge bg-dark border border-secondary text-gray-400">
                                    <?= htmlspecialchars($log['target_table']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($logs)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-5">No recent activity logged.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- QUICK LINKS -->
    <div class="col-md-4 d-flex flex-column gap-4">
        <div class="glass-card border-secondary p-0 overflow-hidden">
            <div class="card-header bg-black bg-opacity-40 border-b border-white border-opacity-10 text-white fw-bold tracking-widest italic p-3">
                <i class="bi bi-lightning-charge text-yellow-400 me-2"></i> QUICK ACTIONS
            </div>
            <div class="card-body d-grid gap-2 p-3">
                <a href="?route=fleet/manage" class="btn btn-outline-info text-start fw-bold py-2">
                    <i class="bi bi-cpu me-2"></i> Manage Fleet
                </a>
                <a href="?route=players/list" class="btn btn-outline-success text-start fw-bold py-2">
                    <i class="bi bi-search me-2"></i> Player Directory
                </a>
                <a href="?route=config/global" class="btn btn-outline-danger text-start fw-bold py-2">
                    <i class="bi bi-exclamation-triangle me-2"></i> Emergency Override
                </a>
            </div>
        </div>

        <div class="glass-card border-secondary p-0 overflow-hidden flex-1">
            <div class="card-header bg-black bg-opacity-40 border-b border-white border-opacity-10 text-white fw-bold tracking-widest italic p-3">
                <i class="bi bi-hdd-network text-green-400 me-2"></i> SERVER HEALTH
            </div>
            <div class="card-body p-4">
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-1 small text-gray-400 font-bold uppercase text-[10px]">
                        <span>Database I/O</span>
                        <span class="text-success animate-pulse">Stable</span>
                    </div>
                    <div class="progress bg-dark border border-secondary" style="height: 4px;">
                        <div class="progress-bar bg-success shadow-[0_0_10px_lime]" style="width: 100%"></div>
                    </div>
                </div>
                <div>
                    <div class="d-flex justify-content-between mb-1 small text-gray-400 font-bold uppercase text-[10px]">
                        <span>API Payload Load</span>
                        <span class="text-cyan-400">12%</span>
                    </div>
                    <div class="progress bg-dark border border-secondary" style="height: 4px;">
                        <div class="progress-bar bg-cyan-400 shadow-[0_0_10px_cyan]" style="width: 12%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>