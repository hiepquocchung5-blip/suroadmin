<?php
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');
$pageTitle = "Player Profile Data";
requireRole(['GOD', 'FINANCE', 'STAFF']);

if (!isset($_GET['id'])) die("User ID required");
$userId = (int)$_GET['id'];

// --- HANDLE POST ACTIONS (Balance Adjust, Ban, Reset) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $adminId = $_SESSION['admin_id'];

    try {
        $pdo->beginTransaction();

        if ($action === 'balance_adjust') {
            requireRole(['GOD']);
            $type = $_POST['type']; // 'credit' or 'debit'
            $amount = (float)$_POST['amount'];
            
            if ($amount > 0) {
                if ($type === 'credit') {
                    $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $userId]);
                    $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, processed_by_admin_id, admin_note) VALUES (?, 'bonus', ?, 'approved', ?, 'Admin Credit')")->execute([$userId, $amount, $adminId]);
                } else {
                    $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$amount, $userId]);
                    $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, processed_by_admin_id, admin_note) VALUES (?, 'withdraw', ?, 'approved', ?, 'Admin Debit Correction')")->execute([$userId, $amount, $adminId]);
                }
                $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'users')")->execute([$adminId, "Manual $type of $amount to User #$userId"]);
                $success = "Balance adjusted successfully.";
            }
        } 
        elseif ($action === 'toggle_ban') {
            $newStatus = $_POST['current_status'] === 'active' ? 'banned' : 'active';
            $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $userId]);
            $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'users')")->execute([$adminId, "Changed status of User #$userId to $newStatus"]);
            $success = "User status updated to $newStatus.";
        }
        elseif ($action === 'reset_pass') {
            $defaultHash = password_hash('123456', PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$defaultHash, $userId]);
            $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'users')")->execute([$adminId, "Reset password for User #$userId"]);
            $success = "Password reset to 123456.";
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Action failed: " . $e->getMessage();
    }
}

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
$netProfit = $fin['total_in'] - $fin['total_out']; 

// 3. Fetch Recent Gameplay
$games = $pdo->prepare("
    SELECT g.*, m.machine_number, i.name as island_name 
    FROM game_logs g
    LEFT JOIN machines m ON g.machine_id = m.id
    LEFT JOIN islands i ON m.island_id = i.id
    WHERE g.user_id = ? ORDER BY g.created_at DESC LIMIT 20
");
$games->execute([$userId]);
$gameHistory = $games->fetchAll();

// 4. Fetch Transactions
$txs = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$txs->execute([$userId]);
$txHistory = $txs->fetchAll();

require_once ADMIN_BASE_PATH . '/layout/main.php';
?>

<!-- Sakura Particles Integration -->
<style>
    #sakura-container-details { position: fixed; inset: 0; overflow: hidden; pointer-events: none; z-index: 0; }
    .sakura-petal { position: absolute; background: linear-gradient(135deg, #ffb3c6, #ff6699); border-radius: 15px 0px 15px 0px; opacity: 0.3; animation: fall linear infinite; box-shadow: 0 0 5px rgba(255, 182, 193, 0.3); }
    @keyframes fall { 0% { transform: translate(0, -10vh) rotate(0deg); opacity: 0; } 10% { opacity: 0.3; } 90% { opacity: 0.3; } 100% { transform: translate(20vw, 110vh) rotate(360deg); opacity: 0; } }
    .dash-wrapper { position: relative; z-index: 10; }
</style>
<div id="sakura-container-details"></div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const container = document.getElementById('sakura-container-details');
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
            <h3 class="text-white fw-black mb-0 italic tracking-widest d-flex align-items-center gap-2">
                <i class="bi bi-person-bounding-box text-info"></i> DOSSIER #<?= $user['id'] ?>
            </h3>
            <div class="text-pink-400 fw-bold mt-1" style="font-size: 0.7rem; letter-spacing: 2px;">プレイヤーデータ</div>
        </div>
        <a href="?route=players/list" class="btn btn-sm btn-outline-secondary rounded-pill fw-bold px-4">BACK TO LIST</a>
    </div>

    <?php if(isset($success)): ?><div class="alert bg-success bg-opacity-20 text-success border border-success fw-bold shadow-sm animate-pulse"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>
    <?php if(isset($error)): ?><div class="alert bg-danger bg-opacity-20 text-danger border border-danger fw-bold shadow-sm"><i class="bi bi-x-circle-fill me-2"></i><?= $error ?></div><?php endif; ?>

    <div class="row g-4 mb-4">
        <!-- User Identity Card -->
        <div class="col-md-4">
            <div class="glass-card h-100 border-info border-opacity-50 overflow-hidden relative group">
                <div class="absolute inset-0 bg-gradient-to-b from-info/10 to-transparent pointer-events-none"></div>
                <div class="card-body text-center relative z-10 p-5">
                    <div class="w-24 h-24 rounded-full bg-gradient-to-br from-cyan-400 to-blue-600 d-flex justify-content-center align-items-center mx-auto mb-3 shadow-[0_0_20px_rgba(6,182,212,0.5)]">
                        <span class="display-3 fw-black text-black"><?= strtoupper(substr($user['username'], 0, 1)) ?></span>
                    </div>
                    <h3 class="text-white fw-black m-0"><?= htmlspecialchars($user['username']) ?></h3>
                    <div class="text-info font-mono tracking-widest mb-4"><?= htmlspecialchars($user['phone']) ?></div>
                    
                    <div class="bg-black bg-opacity-50 rounded-xl p-3 border border-white border-opacity-10 text-start space-y-2 mb-4">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small fw-bold uppercase">Status</span>
                            <?php if($user['status']=='active'): ?>
                                <span class="badge bg-success bg-opacity-20 text-success border border-success animate-pulse">ACTIVE</span>
                            <?php else: ?>
                                <span class="badge bg-danger bg-opacity-20 text-danger border border-danger">BANNED</span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small fw-bold uppercase">Joined</span>
                            <span class="text-white font-mono small"><?= date('M d, Y', strtotime($user['created_at'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small fw-bold uppercase">Last IP</span>
                            <span class="text-gray-400 font-mono small"><?= $user['last_ip'] ?? 'Unknown' ?></span>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="d-grid gap-2">
                        <?php if($user['status'] == 'active'): ?>
                        <form method="POST" onsubmit="return confirm('Ban this user?');">
                            <input type="hidden" name="action" value="toggle_ban">
                            <input type="hidden" name="current_status" value="active">
                            <button class="btn btn-outline-danger w-100 fw-bold shadow-sm"><i class="bi bi-slash-circle me-1"></i> BAN USER</button>
                        </form>
                        <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="toggle_ban">
                            <input type="hidden" name="current_status" value="banned">
                            <button class="btn btn-outline-success w-100 fw-bold shadow-sm"><i class="bi bi-check2-circle me-1"></i> UNBAN USER</button>
                        </form>
                        <?php endif; ?>
                        
                        <form method="POST" onsubmit="return confirm('Reset password to 123456?');">
                            <input type="hidden" name="action" value="reset_pass">
                            <button class="btn btn-outline-warning w-100 fw-bold shadow-sm"><i class="bi bi-key me-1"></i> RESET PASSWORD</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Wallet & Economy -->
        <div class="col-md-8">
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="glass-card bg-black bg-opacity-80 border-yellow-500 border-opacity-30 h-100 p-4 relative overflow-hidden">
                        <div class="absolute -right-4 -bottom-4 opacity-10 text-yellow-500"><i class="bi bi-wallet2 display-1"></i></div>
                        <h6 class="text-yellow-500 fw-bold tracking-widest text-uppercase mb-2">CURRENT BALANCE</h6>
                        <h2 class="text-white font-mono fw-black drop-shadow-[0_0_10px_rgba(234,179,8,0.5)] m-0">
                            <?= number_format($user['balance']) ?> <span class="fs-6 text-muted">MMK</span>
                        </h2>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="glass-card bg-black bg-opacity-80 border-secondary h-100 p-4 relative overflow-hidden">
                        <div class="absolute -right-4 -bottom-4 opacity-10 text-white"><i class="bi bi-graph-up-arrow display-1"></i></div>
                        <h6 class="text-gray-400 fw-bold tracking-widest text-uppercase mb-2">CASINO P/L (GGR)</h6>
                        <h2 class="<?= $netProfit >= 0 ? 'text-success' : 'text-danger animate-pulse' ?> font-mono fw-black m-0">
                            <?= $netProfit >= 0 ? '+' : '' ?><?= number_format($netProfit) ?> <span class="fs-6 text-muted">MMK</span>
                        </h2>
                        <div class="text-[10px] text-gray-500 font-mono mt-1">IN: <?= number_format($fin['total_in']) ?> | OUT: <?= number_format($fin['total_out']) ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Manual Adjustment (GOD Only) -->
            <?php if($_SESSION['admin_role'] === 'GOD'): ?>
            <div class="glass-card border-danger border-opacity-50 p-0 overflow-hidden shadow-[0_0_30px_rgba(239,68,68,0.1)]">
                <div class="bg-danger bg-opacity-20 text-danger fw-black p-3 border-bottom border-danger border-opacity-30 tracking-widest italic flex items-center">
                    <i class="bi bi-sliders me-2"></i> GOD MODE: BALANCE OVERRIDE
                </div>
                <div class="p-4 bg-black bg-opacity-60">
                    <form method="POST" class="row g-3 align-items-end">
                        <input type="hidden" name="action" value="balance_adjust">
                        <div class="col-md-4">
                            <label class="small text-gray-400 fw-bold text-uppercase mb-1">Operation</label>
                            <select name="type" class="form-select bg-dark text-white border-secondary rounded-lg">
                                <option value="credit">ADD CREDIT (+)</option>
                                <option value="debit">DEDUCT (-)</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="small text-gray-400 fw-bold text-uppercase mb-1">Amount (MMK)</label>
                            <input type="number" name="amount" class="form-control bg-dark text-white border-secondary font-mono rounded-lg" required>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-danger w-100 fw-black shadow-lg rounded-lg py-2 hover:scale-105 active:scale-95 transition-transform">EXECUTE</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <!-- Game Logs -->
        <div class="col-md-6">
            <div class="glass-card border-secondary p-0 overflow-hidden">
                <div class="card-header bg-black bg-opacity-50 border-bottom border-white border-opacity-10 text-white fw-bold tracking-widest italic p-3">
                    <i class="bi bi-joystick text-cyan-400 me-2"></i> RECENT SPINS
                </div>
                <div class="table-responsive bg-black bg-opacity-30 hide-scrollbar" style="max-height: 400px;">
                    <table class="table table-dark table-hover table-sm mb-0 font-mono text-xs">
                        <thead class="sticky-top bg-black shadow-sm">
                            <tr class="text-gray-500 uppercase tracking-widest">
                                <th class="ps-3 py-2">Time</th>
                                <th>Game</th>
                                <th class="text-end">Bet</th>
                                <th class="text-end pe-3">Win</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($gameHistory as $g): ?>
                            <tr class="hover:bg-white/5 transition-colors <?= $g['win'] > 0 ? 'bg-success bg-opacity-10 border-l-2 border-success' : 'border-l-2 border-transparent' ?>">
                                <td class="text-gray-500 ps-3"><?= date('H:i:s', strtotime($g['created_at'])) ?></td>
                                <td class="text-info"><?= $g['island_name'] ?> <span class="text-gray-500">#<?= $g['machine_number'] ?></span></td>
                                <td class="text-end text-gray-300"><?= number_format($g['bet']) ?></td>
                                <td class="text-end pe-3 <?= $g['win'] > 0 ? 'text-success fw-bold' : 'text-gray-600' ?>">
                                    <?= $g['win'] > 0 ? '+' : '' ?><?= number_format($g['win']) ?>
                                </td>
                            </tr>
                            <?php endforeach; if(empty($gameHistory)) echo '<tr><td colspan="4" class="text-center py-4 text-muted">No game activity found.</td></tr>'; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Transactions -->
        <div class="col-md-6">
            <div class="glass-card border-secondary p-0 overflow-hidden">
                <div class="card-header bg-black bg-opacity-50 border-bottom border-white border-opacity-10 text-white fw-bold tracking-widest italic p-3">
                    <i class="bi bi-bank text-yellow-400 me-2"></i> TRANSACTION HISTORY
                </div>
                <div class="table-responsive bg-black bg-opacity-30 hide-scrollbar" style="max-height: 400px;">
                    <table class="table table-dark table-hover table-sm mb-0 font-mono text-xs">
                        <thead class="sticky-top bg-black shadow-sm">
                            <tr class="text-gray-500 uppercase tracking-widest">
                                <th class="ps-3 py-2">Time</th>
                                <th>Type</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end pe-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($txHistory as $t): 
                                $txColor = $t['type'] == 'deposit' ? 'text-green-400' : ($t['type'] == 'withdraw' ? 'text-red-400' : 'text-yellow-400');
                            ?>
                            <tr class="hover:bg-white/5 transition-colors">
                                <td class="text-gray-500 ps-3"><?= date('M d H:i', strtotime($t['created_at'])) ?></td>
                                <td class="<?= $txColor ?> font-bold uppercase"><?= $t['type'] ?></td>
                                <td class="text-end text-white"><?= number_format($t['amount']) ?></td>
                                <td class="text-end pe-3">
                                    <?php if($t['status'] == 'approved'): ?>
                                        <span class="text-success"><i class="bi bi-check-circle-fill"></i></span>
                                    <?php elseif($t['status'] == 'rejected'): ?>
                                        <span class="text-danger"><i class="bi bi-x-circle-fill"></i></span>
                                    <?php else: ?>
                                        <span class="text-warning"><i class="bi bi-clock-fill"></i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; if(empty($txHistory)) echo '<tr><td colspan="4" class="text-center py-4 text-muted">No financial history found.</td></tr>'; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>