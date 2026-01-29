<?php
$pageTitle = "Player Profile";
require_once '../../layout/main.php';
requireRole(['GOD', 'FINANCE', 'STAFF']);

if (!isset($_GET['id'])) die("User ID required");
$userId = (int)$_GET['id'];

// 1. Fetch Basic Info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) die("User not found");

// 2. Fetch Financial Aggregates
$stmtFin = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN type='deposit' AND status='approved' THEN amount ELSE 0 END) as total_in,
        SUM(CASE WHEN type='withdraw' AND status='approved' THEN amount ELSE 0 END) as total_out
    FROM transactions WHERE user_id = ?
");
$stmtFin->execute([$userId]);
$fin = $stmtFin->fetch();
$netProfit = $fin['total_in'] - $fin['total_out']; // Positive = Casino Profit

// 3. Fetch Recent Gameplay
$games = $pdo->prepare("
    SELECT g.*, m.machine_number, i.name as island_name 
    FROM game_logs g
    LEFT JOIN machines m ON g.machine_id = m.id
    LEFT JOIN islands i ON m.island_id = i.id
    WHERE g.user_id = ? 
    ORDER BY g.created_at DESC 
    LIMIT 20
");
$games->execute([$userId]);
$gameHistory = $games->fetchAll();

// 4. Fetch Transactions
$txs = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$txs->execute([$userId]);
$txHistory = $txs->fetchAll();

// 5. Determine Withdrawal Limit Tier
$stmtLimit = $pdo->prepare("SELECT max_withdraw FROM withdrawal_limits WHERE deposit_amount <= ? ORDER BY deposit_amount DESC LIMIT 1");
$stmtLimit->execute([(float)$fin['total_in']]);
$limitConfig = $stmtLimit->fetch();
$currentLimit = $limitConfig ? $limitConfig['max_withdraw'] : 0;

// 6. Referral Info
$referrer = null;
if ($user['referrer_id']) {
    $stmtRef = $pdo->prepare("SELECT username, phone FROM users WHERE id = ?");
    $stmtRef->execute([$user['referrer_id']]);
    $referrer = $stmtRef->fetch();
}
$downlineCount = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referrer_id = ?");
$downlineCount->execute([$userId]);
$referrals = $downlineCount->fetchColumn();

?>

<div class="row g-4 mb-4">
    <!-- User Identity Card -->
    <div class="col-md-4">
        <div class="card h-100 border-info">
            <div class="card-header bg-dark border-info text-info fw-bold d-flex justify-content-between">
                <span>IDENTITY</span>
                <span class="badge bg-secondary">#<?= $user['id'] ?></span>
            </div>
            <div class="card-body text-center">
                <div class="display-1 mb-2">👤</div>
                <h3 class="text-white"><?= htmlspecialchars($user['username']) ?></h3>
                <div class="text-muted font-monospace mb-3"><?= htmlspecialchars($user['phone']) ?></div>
                
                <div class="d-flex justify-content-between px-4 mb-2">
                    <span class="text-muted">Status:</span>
                    <?php if($user['status']=='active'): ?>
                        <span class="badge bg-success">ACTIVE</span>
                    <?php else: ?>
                        <span class="badge bg-danger">BANNED</span>
                    <?php endif; ?>
                </div>
                <div class="d-flex justify-content-between px-4 mb-2">
                    <span class="text-muted">Joined:</span>
                    <span class="text-white small"><?= date('M d, Y', strtotime($user['created_at'])) ?></span>
                </div>
                <div class="d-flex justify-content-between px-4 mb-2">
                    <span class="text-muted">Last IP:</span>
                    <span class="text-info small"><?= $user['last_ip'] ?? 'Unknown' ?></span>
                </div>
                <div class="d-flex justify-content-between px-4">
                    <span class="text-muted">Role:</span>
                    <span class="badge <?= $user['is_agent'] ? 'bg-warning text-dark' : 'bg-secondary' ?>"><?= $user['is_agent'] ? 'AGENT' : 'PLAYER' ?></span>
                </div>
                
                <hr class="border-secondary">
                
                <!-- Actions -->
                <div class="d-grid gap-2">
                    <?php if($user['status'] == 'active'): ?>
                    <form method="POST" action="action.php" onsubmit="return confirm('Ban this user?');">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <input type="hidden" name="action" value="toggle_ban">
                        <input type="hidden" name="current_status" value="active">
                        <button class="btn btn-outline-danger w-100 btn-sm">BAN USER</button>
                    </form>
                    <?php else: ?>
                    <form method="POST" action="action.php">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <input type="hidden" name="action" value="toggle_ban">
                        <input type="hidden" name="current_status" value="banned">
                        <button class="btn btn-outline-success w-100 btn-sm">UNBAN USER</button>
                    </form>
                    <?php endif; ?>
                    
                    <form method="POST" action="action.php" onsubmit="return confirm('Reset password to 123456?');">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <input type="hidden" name="action" value="reset_pass">
                        <button class="btn btn-outline-warning w-100 btn-sm">RESET PASSWORD</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Wallet & Economy -->
    <div class="col-md-8">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-body">
                        <h6 class="text-muted small">CURRENT BALANCE</h6>
                        <h2 class="text-warning font-monospace"><?= number_format($user['balance']) ?> MMK</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-body">
                        <h6 class="text-muted small">CASINO P/L (GGR)</h6>
                        <h2 class="<?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?> font-monospace">
                            <?= $netProfit >= 0 ? '+' : '' ?><?= number_format($netProfit) ?> MMK
                        </h2>
                        <small class="text-muted">In: <?= number_format($fin['total_in']) ?> | Out: <?= number_format($fin['total_out']) ?></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
             <div class="col-md-6">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-body">
                        <h6 class="text-muted small">WITHDRAWAL STATUS</h6>
                         <div class="d-flex justify-content-between align-items-center mt-2">
                             <span class="text-white">Current Limit:</span>
                             <span class="text-info fw-bold"><?= number_format($currentLimit) ?> MMK</span>
                         </div>
                         <div class="progress mt-2" style="height: 5px;">
                            <?php 
                                $percent = $currentLimit > 0 ? min(100, ($fin['total_in'] / 1000000) * 100) : 0; // Arbitrary visualization logic
                            ?>
                            <div class="progress-bar bg-info" style="width: <?= $percent ?>%"></div>
                         </div>
                         <small class="text-muted d-block mt-1">Based on Lifetime Deposits: <?= number_format($fin['total_in']) ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-body">
                        <h6 class="text-muted small">NETWORK</h6>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="text-white">Invited By:</span>
                            <span class="text-white"><?= $referrer ? htmlspecialchars($referrer['username']) : 'None' ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <span class="text-white">Referrals:</span>
                            <span class="badge bg-primary rounded-pill"><?= number_format($referrals) ?></span>
                        </div>
                         <div class="d-flex justify-content-between align-items-center mt-1">
                            <span class="text-white">Commission Earned:</span>
                            <span class="text-success"><?= number_format($user['commission_balance']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Manual Adjustment (GOD Only) -->
        <?php if($_SESSION['admin_role'] === 'GOD'): ?>
        <div class="card mt-3 border-secondary">
            <div class="card-header bg-transparent border-secondary text-white fw-bold">MANUAL BALANCE ADJUSTMENT</div>
            <div class="card-body">
                <form action="balance_adjust.php" method="POST" class="row g-2 align-items-end">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <div class="col-md-4">
                        <label class="small text-muted">Operation</label>
                        <select name="type" class="form-select bg-black text-white border-secondary">
                            <option value="credit">ADD CREDIT (+)</option>
                            <option value="debit">DEDUCT (-)</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="small text-muted">Amount</label>
                        <input type="number" name="amount" class="form-control bg-black text-white border-secondary" required>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-warning w-100 fw-bold">EXECUTE</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- Game Logs -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header border-secondary text-white">RECENT SPINS</div>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0 small">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Game</th>
                            <th class="text-end">Bet</th>
                            <th class="text-end">Win</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($gameHistory as $g): ?>
                        <tr>
                            <td class="text-muted"><?= date('H:i:s', strtotime($g['created_at'])) ?></td>
                            <td><?= $g['island_name'] ?> #<?= $g['machine_number'] ?></td>
                            <td class="text-end"><?= number_format($g['bet']) ?></td>
                            <td class="text-end <?= $g['win'] > 0 ? 'text-success' : 'text-secondary' ?>"><?= number_format($g['win']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Transactions -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header border-secondary text-white">TRANSACTION HISTORY</div>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0 small">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($txHistory as $t): ?>
                        <tr>
                            <td class="text-muted"><?= date('M d H:i', strtotime($t['created_at'])) ?></td>
                            <td><span class="badge <?= $t['type']=='deposit'?'bg-success':'bg-warning' ?>"><?= strtoupper($t['type']) ?></span></td>
                            <td><?= number_format($t['amount']) ?></td>
                            <td><?= strtoupper($t['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../layout/footer.php'; ?>