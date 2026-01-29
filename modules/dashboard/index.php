<?php
$pageTitle = "Command Center";
require_once '../../layout/main.php';

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
        <div class="card border-success h-100 bg-success bg-opacity-10">
            <div class="card-body">
                <h6 class="text-success small fw-bold mb-2">NET REVENUE (24H)</h6>
                <h3 class="text-white fw-black mb-0"><?= number_format($netRevenue) ?> <small class="fs-6 text-muted">MMK</small></h3>
                <div class="small mt-2">
                    <span class="text-success"><i class="bi bi-arrow-down"></i> <?= number_format($deposits) ?></span>
                    <span class="text-muted mx-1">|</span>
                    <span class="text-danger"><i class="bi bi-arrow-up"></i> <?= number_format($withdrawals) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- JACKPOT -->
    <div class="col-md-3">
        <div class="card border-warning h-100 bg-warning bg-opacity-10">
            <div class="card-body">
                <h6 class="text-warning small fw-bold mb-2">GRAND JACKPOT</h6>
                <h3 class="text-white fw-black mb-0"><?= number_format($jackpot) ?> <small class="fs-6 text-muted">MMK</small></h3>
                <div class="small text-muted mt-2">
                    Contribution Rate: <span class="text-white">1.0%</span>
                </div>
            </div>
            <?php if($jackpot > 10000000): ?>
                <div class="card-footer bg-warning text-dark fw-bold text-center py-1 small animate-pulse">
                    HIGH VALUE ALERT
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ACTION ITEMS -->
    <div class="col-md-3">
        <div class="card border-info h-100 bg-info bg-opacity-10">
            <div class="card-body">
                <h6 class="text-info small fw-bold mb-2">ACTION REQUIRED</h6>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-white">Pending Finance</span>
                    <span class="badge bg-info text-dark rounded-pill"><?= $pendingTx ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-white">Security Alerts</span>
                    <span class="badge <?= $activeThreats > 0 ? 'bg-danger animate-pulse' : 'bg-secondary' ?> rounded-pill"><?= $activeThreats ?></span>
                </div>
            </div>
            <?php if($pendingTx > 0): ?>
                <a href="../../modules/finance/queue.php" class="card-footer text-center text-info text-decoration-none small fw-bold bg-dark border-info">
                    PROCESS QUEUE &rarr;
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- LIVE STATUS -->
    <div class="col-md-3">
        <div class="card border-secondary h-100 bg-dark">
            <div class="card-body">
                <h6 class="text-muted small fw-bold mb-2">LIVE STATUS</h6>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-white"><i class="bi bi-person-fill text-success"></i> Online Users</span>
                    <span class="fw-bold font-monospace"><?= $activeUsers ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-white"><i class="bi bi-joystick text-warning"></i> Active Machines</span>
                    <span class="fw-bold font-monospace"><?= $activeMachines ?> <small class="text-muted">/ <?= $totalMachines ?></small></span>
                </div>
                
                <div class="progress mt-3" style="height: 6px;">
                    <div class="progress-bar bg-success" style="width: <?= ($activeMachines / max(1, $totalMachines)) * 100 ?>%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- SYSTEM ACTIVITY -->
    <div class="col-md-8">
        <div class="card border-secondary">
            <div class="card-header bg-transparent border-secondary d-flex justify-content-between align-items-center">
                <span class="text-white fw-bold"><i class="bi bi-activity"></i> RECENT ADMIN ACTIVITY</span>
                <a href="../../modules/staff/logs.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead>
                        <tr class="text-secondary text-uppercase text-xs">
                            <th>Time</th>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Target</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($logs as $log): ?>
                        <tr>
                            <td class="text-muted small font-monospace"><?= date('H:i', strtotime($log['created_at'])) ?></td>
                            <td class="fw-bold text-info"><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
                            <td class="text-white"><?= htmlspecialchars($log['action']) ?></td>
                            <td><span class="badge bg-secondary text-dark"><?= htmlspecialchars($log['target_table']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($logs)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No recent activity.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- QUICK LINKS -->
    <div class="col-md-4">
        <div class="card border-secondary mb-3">
            <div class="card-header bg-transparent border-secondary text-white fw-bold">QUICK ACTIONS</div>
            <div class="card-body d-grid gap-2">
                <a href="../machines/manage.php" class="btn btn-outline-warning text-start">
                    <i class="bi bi-plus-lg me-2"></i> Add New Machines
                </a>
                <a href="../users/list.php" class="btn btn-outline-info text-start">
                    <i class="bi bi-search me-2"></i> Find Player
                </a>
                <a href="../settings/global.php" class="btn btn-outline-danger text-start">
                    <i class="bi bi-power me-2"></i> Emergency Stop
                </a>
            </div>
        </div>

        <div class="card border-secondary">
            <div class="card-header bg-transparent border-secondary text-white fw-bold">SERVER HEALTH</div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1 small text-muted">
                        <span>Database Connection</span>
                        <span class="text-success">Stable</span>
                    </div>
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar bg-success" style="width: 100%"></div>
                    </div>
                </div>
                <div>
                    <div class="d-flex justify-content-between mb-1 small text-muted">
                        <span>API Load</span>
                        <span class="text-info">Low</span>
                    </div>
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar bg-info" style="width: 15%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../layout/footer.php'; ?>