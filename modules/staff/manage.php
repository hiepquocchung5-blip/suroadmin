<?php
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');
$pageTitle = "Staff Management";
requireRole(['GOD']); 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $username = cleanInput($_POST['username']);
    
    if ($action === 'create') {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        try {
            $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, role, is_active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$username, $password, $role]);
            $success = "Staff member authorized.";
        } catch (PDOException $e) {
            $error = "Error: Username likely exists.";
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $newStatus = (int)$_POST['status'];
        $pdo->prepare("UPDATE admin_users SET is_active = ? WHERE id = ?")->execute([$newStatus, $id]);
        $success = "Clearance level updated.";
    }
}

$staff = $pdo->query("SELECT * FROM admin_users ORDER BY id ASC")->fetchAll();
require_once ADMIN_BASE_PATH . '/layout/main.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="text-white fw-black mb-0 italic tracking-widest"><i class="bi bi-person-badge text-info"></i> STAFF COMMAND</h3>
</div>

<?php if(isset($success)): ?><div class="alert bg-success bg-opacity-20 text-success border border-success fw-bold shadow-sm animate-pulse"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert bg-danger bg-opacity-20 text-danger border border-danger fw-bold shadow-sm"><i class="bi bi-x-circle-fill me-2"></i><?= $error ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="glass-card p-0 border-info border-opacity-50">
            <div class="bg-info bg-opacity-20 text-info fw-black p-3 border-bottom border-info border-opacity-30 tracking-widest italic">
                <i class="bi bi-person-plus-fill me-2"></i> NEW OPERATIVE
            </div>
            <div class="card-body p-4 bg-black bg-opacity-60">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="text-gray-400 small fw-bold uppercase mb-1">Username</label>
                        <input type="text" name="username" class="form-control bg-dark text-white border-secondary rounded-lg" required autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="text-gray-400 small fw-bold uppercase mb-1">Secure Key</label>
                        <input type="password" name="password" class="form-control bg-dark text-white border-secondary rounded-lg" required>
                    </div>
                    <div class="mb-4">
                        <label class="text-gray-400 small fw-bold uppercase mb-1">Security Clearance</label>
                        <select name="role" class="form-select bg-dark text-white border-secondary rounded-lg">
                            <option value="STAFF">STAFF (Read-Only Support)</option>
                            <option value="FINANCE">FINANCE (Banker / Processor)</option>
                            <option value="GOD">GOD (Root Access)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-info w-100 fw-black shadow-[0_0_15px_cyan] rounded-lg py-3 text-black hover:scale-105 transition-transform">
                        GRANT CLEARANCE
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="glass-card p-0 border-secondary overflow-hidden">
            <div class="bg-black bg-opacity-50 border-bottom border-white border-opacity-10 text-white fw-bold tracking-widest italic p-3">
                <i class="bi bi-shield-lock text-green-400 me-2"></i> AUTHORIZED PERSONNEL
            </div>
            <div class="table-responsive bg-black bg-opacity-30">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead>
                        <tr class="text-gray-500 text-uppercase tracking-widest text-[10px]">
                            <th class="ps-4">ID</th>
                            <th>Operative</th>
                            <th>Clearance</th>
                            <th>Status</th>
                            <th>Last Uplink</th>
                            <th class="text-end pe-4">Controls</th>
                        </tr>
                    </thead>
                    <tbody class="font-mono text-sm">
                        <?php foreach($staff as $s): ?>
                        <tr class="border-b border-white border-opacity-5 hover:bg-white/5">
                            <td class="ps-4 text-gray-500">#<?= $s['id'] ?></td>
                            <td class="fw-bold text-white font-sans"><?= htmlspecialchars($s['username']) ?></td>
                            <td>
                                <?php 
                                $badgeColor = $s['role'] === 'GOD' ? 'bg-warning text-dark shadow-[0_0_10px_gold]' : ($s['role'] === 'FINANCE' ? 'bg-success text-white' : 'bg-secondary text-white');
                                ?>
                                <span class="badge <?= $badgeColor ?> px-3 py-1 rounded-pill"><?= $s['role'] ?></span>
                            </td>
                            <td>
                                <?php if($s['is_active']): ?>
                                    <span class="text-success fw-bold text-xs flex items-center gap-1"><i class="bi bi-circle-fill text-[8px] animate-pulse"></i> ACTIVE</span>
                                <?php else: ?>
                                    <span class="text-danger fw-bold text-xs flex items-center gap-1"><i class="bi bi-x-circle-fill"></i> REVOKED</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-gray-400 text-xs"><?= $s['last_login'] ? date('M d H:i', strtotime($s['last_login'])) : 'NEVER' ?></td>
                            <td class="text-end pe-4">
                                <?php if($s['role'] !== 'GOD' || $_SESSION['admin_id'] == $s['id']): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $s['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-sm <?= $s['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?> rounded-pill fw-bold text-[10px] px-3 transition-colors">
                                            <?= $s['is_active'] ? 'REVOKE' : 'RESTORE' ?>
                                        </button>
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
</div>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>