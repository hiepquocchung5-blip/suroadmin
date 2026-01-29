<?php
$pageTitle = "Player Management";
require_once '../../layout/main.php';

// Handle Actions (Ban/Unban/Reset)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole(['GOD', 'FINANCE']); // Staff can view, only higher roles can act
    
    $userId = (int)$_POST['user_id'];
    $action = $_POST['action'];
    
    try {
        if ($action === 'toggle_ban') {
            $currentStatus = $_POST['current_status'];
            $newStatus = ($currentStatus === 'active') ? 'banned' : 'active';
            $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $userId]);
            $msg = "User #$userId status updated to $newStatus.";
        } elseif ($action === 'reset_pass') {
            // Default to '123456'
            $hash = password_hash('123456', PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $userId]);
            $msg = "Password for User #$userId reset to '123456'.";
        }
        
        // Audit
        $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'users')")
            ->execute([$_SESSION['admin_id'], $msg]);
            
        $success = $msg;
    } catch (Exception $e) {
        $error = "Action failed.";
    }
}

// Pagination & Search Logic
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = $_GET['q'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';
$typeFilter = $_GET['type'] ?? 'all';

$where = "1";
$params = [];

if ($search) {
    $where .= " AND (username LIKE ? OR phone LIKE ? OR id = ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = $search;
}

if ($statusFilter !== 'all') {
    $where .= " AND status = ?";
    $params[] = $statusFilter;
}

if ($typeFilter === 'agent') {
    $where .= " AND is_agent = 1";
} elseif ($typeFilter === 'player') {
    $where .= " AND is_agent = 0";
}

// Fetch Users
$sql = "SELECT * FROM users WHERE $where ORDER BY id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

// Total Count for Pagination
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $where");
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);
?>

<?php if(isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<!-- FILTERS -->
<div class="card mb-4 border-secondary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" name="q" class="form-control bg-dark text-white border-secondary" placeholder="Search Phone, Name, ID..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select bg-dark text-white border-secondary">
                    <option value="all">All Status</option>
                    <option value="active" <?= $statusFilter == 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="banned" <?= $statusFilter == 'banned' ? 'selected' : '' ?>>Banned</option>
                    <option value="suspended" <?= $statusFilter == 'suspended' ? 'selected' : '' ?>>Suspended</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select bg-dark text-white border-secondary">
                    <option value="all">All Types</option>
                    <option value="player" <?= $typeFilter == 'player' ? 'selected' : '' ?>>Regular Players</option>
                    <option value="agent" <?= $typeFilter == 'agent' ? 'selected' : '' ?>>Agents Only</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-info w-100 fw-bold">FILTER</button>
            </div>
        </form>
    </div>
</div>

<!-- LIST -->
<div class="card">
    <div class="card-header border-secondary d-flex justify-content-between align-items-center">
        <span class="text-white">PLAYER LIST (<?= number_format($totalRecords) ?>)</span>
        <div class="badge bg-dark border border-secondary text-muted">Page <?= $page ?> of <?= $totalPages ?></div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr class="text-secondary text-uppercase text-xs">
                        <th>ID</th>
                        <th>User Info</th>
                        <th>Type</th>
                        <th>Balance</th>
                        <th>Level / XP</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($results)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-5">No players found.</td></tr>
                    <?php else: foreach($results as $u): ?>
                    <tr>
                        <td><span class="text-muted">#</span><?= $u['id'] ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-secondary bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center text-info" style="width:32px;height:32px;font-weight:bold;">
                                    <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-white"><?= htmlspecialchars($u['username']) ?></div>
                                    <div class="text-muted small font-monospace"><?= htmlspecialchars($u['phone']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if($u['is_agent']): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i> AGENT</span>
                            <?php else: ?>
                                <span class="badge bg-dark border border-secondary text-muted">PLAYER</span>
                            <?php endif; ?>
                        </td>
                        <td class="font-monospace text-warning fw-bold"><?= number_format($u['balance']) ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-info bg-opacity-25 text-info border border-info border-opacity-25">Lvl <?= $u['level'] ?></span>
                                <small class="text-muted"><?= number_format($u['xp']) ?> XP</small>
                            </div>
                        </td>
                        <td>
                            <?php if($u['status'] === 'active'): ?>
                                <span class="badge bg-success bg-opacity-25 text-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger bg-opacity-25 text-danger">Banned</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small">
                            <?php if($u['last_login_at']): ?>
                                <div><?= date('M d', strtotime($u['last_login_at'])) ?></div>
                                <div><?= date('H:i', strtotime($u['last_login_at'])) ?></div>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="btn-group">
                                <a href="details.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-info" title="View Profile">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-light dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                                <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end border-secondary">
                                    <li>
                                        <form method="POST">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="action" value="toggle_ban">
                                            <input type="hidden" name="current_status" value="<?= $u['status'] ?>">
                                            <button class="dropdown-item text-<?= $u['status'] === 'active' ? 'danger' : 'success' ?>">
                                                <i class="bi bi-shield-slash"></i> <?= $u['status'] === 'active' ? 'Ban User' : 'Unban User' ?>
                                            </button>
                                        </form>
                                    </li>
                                    <li>
                                        <form method="POST" onsubmit="return confirm('Reset password to 123456?');">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="action" value="reset_pass">
                                            <button class="dropdown-item text-warning"><i class="bi bi-key"></i> Reset Password</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- PAGINATION -->
    <?php if($totalPages > 1): ?>
    <div class="card-footer border-secondary py-3">
        <nav>
            <ul class="pagination pagination-sm justify-content-center m-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link bg-dark text-white border-secondary" href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&status=<?= $statusFilter ?>&type=<?= $typeFilter ?>">Prev</a>
                </li>
                <?php for($i=1; $i<=$totalPages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link bg-dark text-white border-secondary <?= $i == $page ? 'bg-info border-info text-dark fw-bold' : '' ?>" href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&status=<?= $statusFilter ?>&type=<?= $typeFilter ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link bg-dark text-white border-secondary" href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&status=<?= $statusFilter ?>&type=<?= $typeFilter ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../layout/footer.php'; ?>