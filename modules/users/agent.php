<?php
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');
$pageTitle = "Agent & Affiliate Manager";
requireRole(['GOD', 'FINANCE']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $userId = (int)$_POST['user_id'];

    if ($action === 'promote') {
        $pdo->prepare("UPDATE users SET is_agent = 1 WHERE id = ?")->execute([$userId]);
        $success = "User #$userId promoted to Agent.";
        $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'users')")->execute([$_SESSION['admin_id'], "Promoted User #$userId to Agent"]);
    } 
    elseif ($action === 'demote') {
        $pdo->prepare("UPDATE users SET is_agent = 0 WHERE id = ?")->execute([$userId]);
        $success = "Agent #$userId demoted to Player.";
    }
}

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

require_once ADMIN_BASE_PATH . '/layout/main.php';
?>

<style>
    #sakura-container-agents { position: fixed; inset: 0; overflow: hidden; pointer-events: none; z-index: 0; }
    .sakura-petal { position: absolute; background: linear-gradient(135deg, #ffb3c6, #ff6699); border-radius: 15px 0px 15px 0px; opacity: 0.3; animation: fall linear infinite; box-shadow: 0 0 5px rgba(255, 182, 193, 0.3); }
    @keyframes fall { 0% { transform: translate(0, -10vh) rotate(0deg); opacity: 0; } 10% { opacity: 0.3; } 90% { opacity: 0.3; } 100% { transform: translate(20vw, 110vh) rotate(360deg); opacity: 0; } }
    .dash-wrapper { position: relative; z-index: 10; }
</style>
<div id="sakura-container-agents"></div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const container = document.getElementById('sakura-container-agents');
        if(!container) return;
        const petalCount = window.innerWidth < 768 ? 10 : 20;
        for(let i=0; i<petalCount; i++) {
            let p = document.createElement('div');
            p.className = 'sakura-petal';
            p.style.width = p.style.height = (Math.random()*6+4) + 'px';
            p.style.left = Math.random()*100 + 'vw';
            p.style.animationDuration = (Math.random()*8+7) + 's';
            p.style.animationDelay = (Math.random()*5) + 's';
            container.appendChild(p);
        }
    });
</script>

<div class="dash-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="text-white fw-black mb-0 italic tracking-widest"><i class="bi bi-diagram-3 text-info"></i> AFFILIATES</h3>
            <div class="text-pink-400 fw-bold mt-1" style="font-size: 0.7rem; letter-spacing: 2px;">代理店管理</div>
        </div>
        <span class="badge bg-black border border-info text-info px-4 py-2 font-mono fs-6 shadow-[0_0_15px_rgba(13,202,240,0.3)]">
            <?= count($agents) ?> AGENTS ACTIVE
        </span>
    </div>

    <?php if(isset($success)): ?><div class="alert bg-success bg-opacity-20 text-success border border-success fw-bold shadow-sm animate-pulse"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="glass-card p-0 border-info border-opacity-50 shadow-[0_0_30px_rgba(13,202,240,0.1)]">
                <div class="bg-info bg-opacity-20 text-info fw-black p-3 border-b border-info border-opacity-30 tracking-widest italic">
                    <i class="bi bi-person-plus-fill me-2"></i> PROMOTE PLAYER
                </div>
                <div class="card-body p-4 bg-black bg-opacity-60">
                    <form method="POST">
                        <input type="hidden" name="action" value="promote">
                        <div class="mb-4">
                            <label class="small text-gray-400 fw-bold text-uppercase mb-1">User ID</label>
                            <input type="number" name="user_id" class="form-control bg-dark text-white border-secondary rounded-lg font-mono" placeholder="e.g. 1024" required>
                            <div class="form-text text-muted text-[10px]">Grants the user an invite code and commission wallet.</div>
                        </div>
                        <button type="submit" class="btn btn-info w-100 fw-black shadow-[0_0_15px_cyan] rounded-lg py-3 hover:scale-105 transition-transform text-black">
                            AUTHORIZE AGENT STATUS
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="glass-card p-0 border-secondary overflow-hidden">
                <div class="card-header bg-black bg-opacity-50 border-b border-white border-opacity-10 text-white fw-bold tracking-widest italic p-3">
                    <i class="bi bi-star-fill text-yellow-400 me-2"></i> MASTER ROSTER
                </div>
                <div class="table-responsive bg-black bg-opacity-40">
                    <table class="table table-dark table-hover mb-0 align-middle">
                        <thead class="border-b border-white border-opacity-10">
                            <tr class="text-gray-500 text-uppercase tracking-widest text-[10px]">
                                <th class="ps-4 py-3">Agent ID</th>
                                <th class="text-center">Downline</th>
                                <th class="text-end">Wallet</th>
                                <th class="text-end">Commission</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody class="font-mono text-sm">
                            <?php foreach($agents as $a): ?>
                            <tr class="hover:bg-white/5 transition-colors border-b border-white border-opacity-5">
                                <td class="ps-4">
                                    <div class="fw-bold text-white font-sans"><?= htmlspecialchars($a['username']) ?></div>
                                    <div class="text-[10px] text-gray-500">ID: <?= $a['id'] ?> | <?= htmlspecialchars($a['phone']) ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-purple-900 bg-opacity-50 text-purple-400 border border-purple-500 rounded-pill px-3 shadow-[0_0_10px_purple] animate-pulse">
                                        <i class="bi bi-people-fill me-1"></i> <?= number_format((float)$a['total_referrals']) ?>
                                    </span>
                                </td>
                                <!-- FIXED: Cast to (float) to prevent null errors -->
                                <td class="text-end text-yellow-400 fw-bold"><?= number_format((float)$a['balance']) ?></td>
                                <td class="text-end">
                                    <div class="text-green-400 fw-black text-lg lh-1"><?= number_format((float)$a['commission_balance']) ?></div>
                                    <div class="text-[9px] text-gray-500 uppercase mt-1">Life: <?= number_format((float)$a['lifetime_earnings']) ?></div>
                                </td>
                                <td class="text-end pe-4">
                                    <form method="POST" onsubmit="return confirm('Revoke Agent Status from <?= htmlspecialchars($a['username']) ?>?');">
                                        <input type="hidden" name="action" value="demote">
                                        <input type="hidden" name="user_id" value="<?= $a['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger rounded-circle border-0 hover:bg-danger hover:text-white transition-colors" title="Demote">
                                            <i class="bi bi-person-dash-fill"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; if(empty($agents)) echo '<tr><td colspan="5" class="text-center py-5 text-muted font-sans">No active agents found.</td></tr>'; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>