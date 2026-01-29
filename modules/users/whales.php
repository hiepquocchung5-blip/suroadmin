<?php
$pageTitle = "Whale Watcher (VIPs)";
require_once '../../layout/main.php';
requireRole(['GOD', 'FINANCE']);

// Define "Whale" Thresholds
$balanceThreshold = 500000; // Users with > 500k
$depositThreshold = 1000000; // Users who deposited > 1M

// Fetch Whales
$sql = "
    SELECT 
        u.id, u.username, u.phone, u.balance, u.level, u.status,
        (SELECT SUM(amount) FROM transactions WHERE user_id = u.id AND type = 'deposit' AND status = 'approved') as total_deposited,
        (SELECT SUM(amount) FROM transactions WHERE user_id = u.id AND type = 'withdraw' AND status = 'approved') as total_withdrawn
    FROM users u
    HAVING u.balance >= ? OR total_deposited >= ?
    ORDER BY u.balance DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$balanceThreshold, $depositThreshold]);
$whales = $stmt->fetchAll();
?>

<div class="alert alert-info bg-dark border-info text-info">
    <i class="bi bi-info-circle"></i> <strong>Whale Definition:</strong> Players with Balance > <?= number_format($balanceThreshold) ?> OR Total Deposits > <?= number_format($depositThreshold) ?>.
</div>

<div class="card">
    <div class="card-header border-secondary d-flex justify-content-between">
        <span class="text-white fw-bold">HIGH VALUE PLAYERS (<?= count($whales) ?>)</span>
        <span class="badge bg-warning text-dark">VIP MONITOR</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr class="text-secondary text-uppercase text-xs">
                        <th>User</th>
                        <th>Current Balance</th>
                        <th>Total In</th>
                        <th>Total Out</th>
                        <th>Profit/Loss</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($whales as $w): 
                        $pnl = $w['total_deposited'] - $w['total_withdrawn']; // Casino Profit from this user
                        // If PnL is negative, the user is winning (Casino Loss)
                        $isWinning = $pnl < 0;
                    ?>
                    <tr class="<?= $w['status'] === 'banned' ? 'opacity-50' : '' ?>">
                        <td>
                            <div class="fw-bold text-white"><?= htmlspecialchars($w['username']) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($w['phone']) ?></div>
                            <span class="badge bg-secondary text-white" style="font-size: 0.6rem;">LVL <?= $w['level'] ?></span>
                        </td>
                        <td>
                            <span class="fs-5 fw-black text-warning"><?= number_format($w['balance']) ?></span>
                        </td>
                        <td class="text-success">+<?= number_format($w['total_deposited']) ?></td>
                        <td class="text-danger">-<?= number_format($w['total_withdrawn']) ?></td>
                        <td>
                            <?php if($isWinning): ?>
                                <span class="text-danger fw-bold">WINNING (<?= number_format(abs($pnl)) ?>)</span>
                                <i class="bi bi-exclamation-triangle-fill text-danger" title="User is beating the house"></i>
                            <?php else: ?>
                                <span class="text-success fw-bold">LOSING (<?= number_format($pnl) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="details.php?user_id=<?= $w['id'] ?>" class="btn btn-sm btn-outline-info">PROFILE</a>
                            <?php if($w['status'] !== 'banned'): ?>
                                <form method="POST" action="action.php" style="display:inline" onsubmit="return confirm('Ban this Whale?');">
                                    <input type="hidden" name="user_id" value="<?= $w['id'] ?>">
                                    <input type="hidden" name="action" value="toggle_ban">
                                    <input type="hidden" name="current_status" value="active">
                                    <button class="btn btn-sm btn-outline-danger" title="Freeze Account"><i class="bi bi-slash-circle"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../layout/footer.php'; ?>