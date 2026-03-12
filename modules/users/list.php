<?php
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');
$pageTitle = "Player Database";
requireRole(['GOD', 'FINANCE']);

// Handle Ban/Unban
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $userId = (int)$_POST['user_id'];
    if ($_POST['action'] === 'toggle_ban') {
        $newStatus = $_POST['current_status'] === 'active' ? 'banned' : 'active';
        $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $userId]);
        $success = "User #$userId status changed to " . strtoupper($newStatus);
        $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'users')")->execute([$_SESSION['admin_id'], "$newStatus User #$userId"]);
    }
}

// Search & Sorting
$search = $_GET['q'] ?? '';
$sort = $_GET['sort'] ?? 'recent'; 

$where = "1";
$params = [];
if ($search) {
    $where .= " AND (username LIKE ? OR phone LIKE ? OR id = ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = $search;
}

$orderBy = match($sort) {
    'rich' => "balance DESC",
    'bleeding' => "pnl_lifetime ASC",
    'xp' => "xp DESC",
    default => "id DESC"
};

$sql = "SELECT id, username, phone, balance, level, xp, status, pnl_lifetime, created_at, is_agent, active_pet_id 
        FROM users WHERE $where ORDER BY $orderBy LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once ADMIN_BASE_PATH . '/layout/main.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-black text-white italic tracking-widest m-0"><i class="bi bi-people"></i> PLAYER DIRECTORY</h2>
</div>

<?php if(isset($success)): ?><div class="alert bg-success bg-opacity-20 text-success border border-success fw-bold shadow-sm"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>

<!-- HIGH END SEARCH BAR -->
<div class="glass-card p-3 mb-4 border border-secondary shadow-sm">
    <form method="GET" class="row g-3 align-items-center">
        <input type="hidden" name="route" value="players/list">
        <div class="col-md-5 relative">
            <input type="text" name="q" class="form-control bg-black text-white border-secondary py-3 px-4 rounded-pill" placeholder="Search by Phone, ID, or Username..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-4">
            <select name="sort" class="form-select bg-black text-white border-secondary py-3 rounded-pill fw-bold" onchange="this.form.submit()">
                <option value="recent" <?= $sort==='recent'?'selected':'' ?>>Latest Registrations</option>
                <option value="rich" <?= $sort==='rich'?'selected':'' ?>>Highest Balance (Whales)</option>
                <option value="bleeding" <?= $sort==='bleeding'?'selected':'' ?>>Highest P/L (Beating the House)</option>
                <option value="xp" <?= $sort==='xp'?'selected':'' ?>>Highest Level</option>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-info w-100 py-3 rounded-pill fw-black tracking-widest"><i class="bi bi-search me-1"></i> EXECUTE</button>
        </div>
    </form>
</div>

<!-- V2 DATA GRID -->
<div class="glass-card p-0 overflow-hidden border border-secondary">
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead class="bg-black bg-opacity-50">
                <tr class="text-gray-400 text-uppercase tracking-widest text-[10px]">
                    <th class="ps-4 py-4">Player Profile</th>
                    <th>Status</th>
                    <th>Wallet Balance</th>
                    <th>Progression</th>
                    <th>Casino P/L</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody class="border-top-0">
                <?php foreach($users as $u): 
                    $pnl = (float)$u['pnl_lifetime'];
                    $isWinning = $pnl < 0; 
                ?>
                <tr class="<?= $u['status'] === 'banned' ? 'opacity-50 bg-danger bg-opacity-10' : 'hover:bg-white hover:bg-opacity-5' ?> transition-colors">
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="w-10 h-10 rounded-circle bg-dark border border-secondary d-flex align-items-center justify-content-center text-info fw-bold shadow-inner">
                                <?= strtoupper(substr($u['username'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="fw-bold text-white fs-6 d-flex align-items-center gap-2">
                                    <?= htmlspecialchars($u['username']) ?>
                                    <?php if($u['is_agent']): ?><span class="badge bg-warning text-dark text-[8px] px-1 py-0.5"><i class="bi bi-star-fill"></i> AGENT</span><?php endif; ?>
                                </div>
                                <div class="font-mono text-gray-500 text-[10px] mt-0.5">ID: <?= $u['id'] ?> | <?= htmlspecialchars($u['phone']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if($u['status'] === 'active'): ?>
                            <span class="badge bg-success bg-opacity-20 text-success border border-success border-opacity-50 px-3 py-1 rounded-pill"><i class="bi bi-check2"></i> ACTIVE</span>
                        <?php else: ?>
                            <span class="badge bg-danger bg-opacity-20 text-danger border border-danger border-opacity-50 px-3 py-1 rounded-pill"><i class="bi bi-slash-circle"></i> BANNED</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="font-mono fs-5 fw-black text-yellow-400 drop-shadow-[0_0_5px_rgba(234,179,8,0.5)]">
                            <?= number_format($u['balance']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-info text-dark font-black">LVL <?= $u['level'] ?></span>
                            <span class="text-[10px] text-gray-400 font-mono"><?= number_format($u['xp']) ?> XP</span>
                        </div>
                    </td>
                    <td>
                        <?php if($isWinning): ?>
                            <span class="text-danger fw-black font-mono bg-danger bg-opacity-10 px-2 py-1 rounded border border-danger border-opacity-20">
                                <?= number_format($pnl) ?> <i class="bi bi-graph-down-arrow ms-1"></i>
                            </span>
                        <?php else: ?>
                            <span class="text-success fw-bold font-mono">
                                +<?= number_format($pnl) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end pe-4">
                        <div class="btn-group shadow-sm">
                            <a href="?route=players/details&id=<?= $u['id'] ?>" class="btn btn-sm btn-dark border-secondary text-info hover:bg-info hover:text-black transition-colors"><i class="bi bi-search"></i></a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Change status for User #<?= $u['id'] ?>?');">
                                <input type="hidden" name="action" value="toggle_ban">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="current_status" value="<?= $u['status'] ?>">
                                <button class="btn btn-sm btn-dark border-secondary <?= $u['status'] === 'active' ? 'text-danger hover:bg-danger hover:text-white' : 'text-success hover:bg-success hover:text-white' ?> transition-colors">
                                    <i class="bi bi-power"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($users)) echo '<tr><td colspan="6" class="text-center text-gray-500 py-5">No players found matching query.</td></tr>'; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>