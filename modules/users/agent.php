<?php
$pageTitle = "Agent & Affiliate Manager";
require_once '../../layout/main.php';
requireRole(['GOD', 'FINANCE']);

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $userId = (int)$_POST['user_id'];

    if ($action === 'promote') {
        $commission = (float)$_POST['commission_rate']; // e.g. 5%
        // In a real schema, you'd add a 'commission_rate' column. using generic update for now.
        $pdo->prepare("UPDATE users SET is_agent = 1 WHERE id = ?")->execute([$userId]);
        $success = "User #$userId promoted to Agent.";
        
        // Log
        $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'users')")
            ->execute([$_SESSION['admin_id'], "Promoted User #$userId to Agent"]);
    } 
    elseif ($action === 'demote') {
        $pdo->prepare("UPDATE users SET is_agent = 0 WHERE id = ?")->execute([$userId]);
        $success = "Agent #$userId demoted to Player.";
    }
}

// Fetch Agents
// Calculation: Count total referrals and total commission earned (sum of commission logs)
$sql = "
    SELECT 
        u.id, u.username, u.phone, u.balance, u.commission_balance, u.created_at,
        (SELECT COUNT(*) FROM users WHERE referrer_id = u.id) as total_referrals,
        (SELECT SUM(amount) FROM transactions WHERE user_id = u.id AND type = 'commission') as lifetime_earnings
    FROM users u
    WHERE u.is_agent = 1
    ORDER BY total_referrals DESC
";
$agents = $pdo->query($sql)->fetchAll();
?>

<?php if(isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<div class="row">
    <!-- ADD AGENT FORM -->
    <div class="col-md-4">
        <div class="card mb-4 border-info">
            <div class="card-header bg-dark border-info text-info fw-bold">PROMOTE PLAYER</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="promote">
                    <div class="mb-3">
                        <label class="text-muted small">User ID / Phone</label>
                        <input type="text" name="user_search" class="form-control bg-black text-white border-secondary" placeholder="Enter ID..." required onchange="document.getElementById('promoteId').value = this.value">
                        <input type="hidden" name="user_id" id="promoteId">
                        <div class="form-text text-muted">Enter the ID of an existing player.</div>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Commission Rate (%)</label>
                        <input type="number" name="commission_rate" class="form-control bg-black text-white border-secondary" value="5" min="1" max="50">
                    </div>
                    <button type="submit" class="btn btn-info w-100 fw-bold">MAKE AGENT</button>
                </form>
            </div>
        </div>
    </div>

    <!-- AGENT LIST -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header border-secondary text-white">ACTIVE AGENTS (<?= count($agents) ?>)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0 align-middle">
                        <thead>
                            <tr class="text-secondary text-uppercase text-xs">
                                <th>Agent</th>
                                <th class="text-center">Downline</th>
                                <th class="text-end">Wallet</th>
                                <th class="text-end">Commission</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($agents as $a): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-white"><?= htmlspecialchars($a['username']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($a['phone']) ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary rounded-pill"><?= number_format($a['total_referrals']) ?> Users</span>
                                </td>
                                <td class="text-end font-monospace text-warning"><?= number_format($a['balance']) ?></td>
                                <td class="text-end">
                                    <div class="text-success fw-bold"><?= number_format($a['commission_balance']) ?></div>
                                    <div class="small text-muted">Life: <?= number_format($a['lifetime_earnings']) ?></div>
                                </td>
                                <td class="text-end">
                                    <form method="POST" onsubmit="return confirm('Revoke Agent Status?');">
                                        <input type="hidden" name="action" value="demote">
                                        <input type="hidden" name="user_id" value="<?= $a['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger border-0"><i class="bi bi-person-dash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../layout/footer.php'; ?>