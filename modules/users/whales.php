<?php
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');
$pageTitle = "Whale Watcher (VIPs)";
requireRole(['GOD', 'FINANCE']);

$balanceThreshold = 500000; 
$depositThreshold = 1000000; 

$sql = "
    SELECT 
        u.id, u.username, u.phone, u.balance, u.level, u.status, u.pnl_lifetime,
        (SELECT SUM(amount) FROM transactions WHERE user_id = u.id AND type = 'deposit' AND status = 'approved') as total_deposited,
        (SELECT SUM(amount) FROM transactions WHERE user_id = u.id AND type = 'withdraw' AND status = 'approved') as total_withdrawn
    FROM users u
    HAVING u.balance >= ? OR total_deposited >= ?
    ORDER BY u.balance DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$balanceThreshold, $depositThreshold]);
$whales = $stmt->fetchAll();

require_once ADMIN_BASE_PATH . '/layout/main.php';
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h2 class="fw-black text-warning italic tracking-widest m-0 drop-shadow-[0_0_15px_rgba(234,179,8,0.3)]"><i class="bi bi-gem"></i> WHALE WATCHER</h2>
        <p class="text-muted small mt-1">Real-time monitoring of High-Net-Worth individuals.</p>
    </div>
    <div class="badge bg-black border border-warning text-warning px-4 py-2 font-mono fs-6 shadow-[0_0_15px_rgba(234,179,8,0.2)]">
        <?= count($whales) ?> VIPs DETECTED
    </div>
</div>

<div class="row g-4">
    <?php foreach($whales as $w): 
        $pnl = $w['total_deposited'] - $w['total_withdrawn']; 
        $isWinning = $pnl < 0;
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="glass-card h-100 p-0 overflow-hidden border-warning border-opacity-30 position-relative group">
            
            <?php if($isWinning): ?>
                <div class="absolute inset-0 bg-danger opacity-5 mix-blend-screen pointer-events-none animate-pulse"></div>
            <?php endif; ?>

            <div class="p-4 bg-gradient-to-r from-black to-transparent border-b border-white border-opacity-10 d-flex justify-content-between align-items-start">
                <div class="d-flex align-items-center gap-3">
                    <div class="w-12 h-12 rounded-circle bg-gradient-to-br from-yellow-400 to-orange-600 d-flex justify-content-center align-items-center text-black fw-black fs-4 shadow-[0_0_15px_gold]">
                        <?= strtoupper(substr($w['username'], 0, 1)) ?>
                    </div>
                    <div>
                        <h5 class="text-white fw-black m-0"><?= htmlspecialchars($w['username']) ?></h5>
                        <div class="text-gray-400 font-mono text-[10px]">ID: <?= $w['id'] ?> | <?= htmlspecialchars($w['phone']) ?></div>
                    </div>
                </div>
            </div>

            <div class="p-4">
                <div class="mb-3">
                    <span class="text-[10px] text-gray-500 fw-bold text-uppercase tracking-widest">Available Liquidity</span>
                    <h2 class="text-yellow-400 font-mono fw-black m-0 drop-shadow-md">
                        <?= number_format($w['balance']) ?> <small class="fs-6 text-yellow-600">MMK</small>
                    </h2>
                </div>

                <div class="row g-2 mb-4 text-[10px] font-mono">
                    <div class="col-6">
                        <div class="bg-black bg-opacity-50 p-2 rounded border border-white border-opacity-5">
                            <span class="text-gray-500 d-block">TOTAL DEPOSITS</span>
                            <span class="text-success fw-bold text-sm">+<?= number_format($w['total_deposited']) ?></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-black bg-opacity-50 p-2 rounded border border-white border-opacity-5">
                            <span class="text-gray-500 d-block">TOTAL PAYOUTS</span>
                            <span class="text-danger fw-bold text-sm">-<?= number_format($w['total_withdrawn']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top border-white border-opacity-10">
                    <div>
                        <span class="text-[9px] text-gray-500 fw-bold text-uppercase d-block mb-1">Casino Profit/Loss</span>
                        <?php if($isWinning): ?>
                            <span class="badge bg-danger text-white px-2 py-1 shadow-[0_0_10px_red] animate-pulse">BLEEDING: <?= number_format(abs($pnl)) ?></span>
                        <?php else: ?>
                            <span class="badge bg-success bg-opacity-20 text-success border border-success border-opacity-50 px-2 py-1">PROFIT: +<?= number_format($pnl) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <a href="?route=players/details&id=<?= $w['id'] ?>" class="btn btn-warning btn-sm fw-bold text-dark rounded-pill px-4 shadow-[0_0_15px_rgba(234,179,8,0.4)] hover:scale-105 transition-transform">
                        INSPECT
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if(empty($whales)): ?>
        <div class="col-12 text-center py-5 text-gray-500">
            <i class="bi bi-water display-1 opacity-20 mb-3 d-block"></i>
            No whales detected. Lower the threshold to see more players.
        </div>
    <?php endif; ?>
</div>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>