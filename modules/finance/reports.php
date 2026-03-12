<?php
// Prevent direct access to layout file
if (!defined('ADMIN_BASE_PATH')) exit;
$pageTitle = "Financial Reports";
require_once ADMIN_BASE_PATH . '/layout/main.php';
requireRole(['GOD', 'FINANCE']);

// Time Range Logic
$range = $_GET['range'] ?? 'today';
$startDate = date('Y-m-d 00:00:00');
$endDate = date('Y-m-d 23:59:59');

if ($range === 'yesterday') {
    $startDate = date('Y-m-d 00:00:00', strtotime('-1 day'));
    $endDate = date('Y-m-d 23:59:59', strtotime('-1 day'));
} elseif ($range === 'week') {
    $startDate = date('Y-m-d 00:00:00', strtotime('monday this week'));
} elseif ($range === 'month') {
    $startDate = date('Y-m-01 00:00:00');
    $endDate = date('Y-m-t 23:59:59');
}

// Fetch Aggregates
// Deposits
$sqlDep = "SELECT SUM(amount) FROM transactions WHERE type='deposit' AND status='approved' AND created_at BETWEEN ? AND ?";
$stmtDep = $pdo->prepare($sqlDep);
$stmtDep->execute([$startDate, $endDate]);
$totalIn = $stmtDep->fetchColumn() ?: 0;

// Withdrawals
$sqlWith = "SELECT SUM(amount) FROM transactions WHERE type='withdraw' AND status='approved' AND created_at BETWEEN ? AND ?";
$stmtWith = $pdo->prepare($sqlWith);
$stmtWith->execute([$startDate, $endDate]);
$totalOut = $stmtWith->fetchColumn() ?: 0;

// Net Revenue (GGR)
$netRevenue = $totalIn - $totalOut;

// Provider Breakdown
$sqlProv = "
    SELECT 
        pm.provider_name, 
        SUM(CASE WHEN t.type = 'deposit' THEN t.amount ELSE 0 END) as total_in,
        SUM(CASE WHEN t.type = 'withdraw' THEN t.amount ELSE 0 END) as total_out
    FROM transactions t
    LEFT JOIN payment_methods pm ON t.payment_method_id = pm.id
    WHERE t.status = 'approved' AND t.created_at BETWEEN ? AND ?
    GROUP BY pm.provider_name
";
$stmtProv = $pdo->prepare($sqlProv);
$stmtProv->execute([$startDate, $endDate]);
$providers = $stmtProv->fetchAll();
?>

<!-- FILTER BAR -->
<div class="glass-card mb-4 border-secondary p-3 shadow-sm">
    <div class="d-flex gap-2">
        <a href="?route=finance/reports&range=today" class="btn btn-sm rounded-pill fw-bold <?= $range=='today' ? 'btn-info shadow-[0_0_10px_cyan] text-black' : 'btn-outline-secondary' ?>">Today</a>
        <a href="?route=finance/reports&range=yesterday" class="btn btn-sm rounded-pill fw-bold <?= $range=='yesterday' ? 'btn-info shadow-[0_0_10px_cyan] text-black' : 'btn-outline-secondary' ?>">Yesterday</a>
        <a href="?route=finance/reports&range=week" class="btn btn-sm rounded-pill fw-bold <?= $range=='week' ? 'btn-info shadow-[0_0_10px_cyan] text-black' : 'btn-outline-secondary' ?>">This Week</a>
        <a href="?route=finance/reports&range=month" class="btn btn-sm rounded-pill fw-bold <?= $range=='month' ? 'btn-info shadow-[0_0_10px_cyan] text-black' : 'btn-outline-secondary' ?>">This Month</a>
        <div class="ms-auto text-muted small align-self-center font-mono">
            <?= date('M d, Y', strtotime($startDate)) ?> - <?= date('M d, Y', strtotime($endDate)) ?>
        </div>
    </div>
</div>

<!-- BIG STATS -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="glass-card border-success h-100 p-0 overflow-hidden">
             <div class="bg-success bg-opacity-10 p-4 h-100 flex flex-col justify-center items-center relative">
                <i class="bi bi-arrow-down-circle absolute -right-4 -bottom-4 text-success opacity-10" style="font-size: 6rem;"></i>
                <h6 class="text-success fw-bold tracking-widest text-uppercase mb-2 relative z-10">TOTAL DEPOSITS</h6>
                <h2 class="fw-black font-mono relative z-10 text-success m-0">+<?= number_format($totalIn) ?> <small class="fs-6">MMK</small></h2>
             </div>
        </div>
    </div>
    <div class="col-md-4">
         <div class="glass-card border-danger h-100 p-0 overflow-hidden">
             <div class="bg-danger bg-opacity-10 p-4 h-100 flex flex-col justify-center items-center relative">
                <i class="bi bi-arrow-up-circle absolute -right-4 -bottom-4 text-danger opacity-10" style="font-size: 6rem;"></i>
                <h6 class="text-danger fw-bold tracking-widest text-uppercase mb-2 relative z-10">TOTAL PAYOUTS</h6>
                <h2 class="fw-black font-mono relative z-10 text-danger m-0">-<?= number_format($totalOut) ?> <small class="fs-6">MMK</small></h2>
             </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-card border-info h-100 p-0 overflow-hidden">
             <div class="bg-info bg-opacity-10 p-4 h-100 flex flex-col justify-center items-center relative">
                <i class="bi bi-graph-up-arrow absolute -right-4 -bottom-4 text-info opacity-10" style="font-size: 6rem;"></i>
                <h6 class="text-info fw-bold tracking-widest text-uppercase mb-2 relative z-10">NET REVENUE (GGR)</h6>
                <h2 class="fw-black font-mono relative z-10 <?= $netRevenue >= 0 ? 'text-info drop-shadow-[0_0_10px_cyan]' : 'text-danger drop-shadow-[0_0_10px_red]' ?> m-0">
                    <?= $netRevenue >= 0 ? '+' : '' ?><?= number_format($netRevenue) ?> <small class="fs-6">MMK</small>
                </h2>
             </div>
        </div>
    </div>
</div>

<!-- PROVIDER BREAKDOWN -->
<div class="glass-card p-0 border-secondary overflow-hidden">
    <div class="card-header bg-black bg-opacity-50 border-b border-white border-opacity-10 fw-bold tracking-widest italic p-3 d-flex items-center">
        <i class="bi bi-bar-chart-line text-warning me-2"></i> PAYMENT CHANNEL PERFORMANCE
    </div>
    <div class="table-responsive bg-black bg-opacity-40">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead>
                <tr class="text-gray-500 text-uppercase text-[10px] tracking-widest border-b border-white border-opacity-10">
                    <th class="ps-4 py-3">Provider</th>
                    <th class="text-end text-success">Total In</th>
                    <th class="text-end text-danger">Total Out</th>
                    <th class="text-end pe-4">Net Flow</th>
                </tr>
            </thead>
            <tbody class="font-mono text-sm">
                <?php foreach($providers as $p): 
                    $net = $p['total_in'] - $p['total_out'];
                ?>
                <tr class="border-b border-white border-opacity-5 hover:bg-white/5 transition-colors">
                    <td class="ps-4 fw-bold font-sans"><?= htmlspecialchars($p['provider_name'] ?? 'Manual/System') ?></td>
                    <td class="text-end text-success">+<?= number_format($p['total_in']) ?></td>
                    <td class="text-end text-danger">-<?= number_format($p['total_out']) ?></td>
                    <td class="text-end fw-bold pe-4 <?= $net >= 0 ? 'text-info' : 'text-danger animate-pulse' ?>">
                        <?= $net >= 0 ? '+' : '' ?><?= number_format($net) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($providers)): ?>
                    <tr><td colspan="4" class="text-center text-gray-500 py-5 font-sans">No transaction data for this period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>   