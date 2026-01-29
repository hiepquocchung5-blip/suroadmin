<?php
$pageTitle = "Staff Management";
require_once '../../layout/main.php';
requireRole(['GOD']); // Only GOD admin can manage staff

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $username = cleanInput($_POST['username']);
    
    if ($action === 'create') {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, role, is_active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$username, $password, $role]);
            $success = "Staff member created successfully.";
        } catch (PDOException $e) {
            $error = "Error: Username likely exists.";
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $newStatus = (int)$_POST['status'];
        $pdo->prepare("UPDATE admin_users SET is_active = ? WHERE id = ?")->execute([$newStatus, $id]);
        $success = "Status updated.";
    }
}

// Fetch Staff
$staff = $pdo->query("SELECT * FROM admin_users ORDER BY id ASC")->fetchAll();
?>

<?php if(isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header border-secondary text-info fw-bold">ADD NEW STAFF</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="text-muted small">Username</label>
                        <input type="text" name="username" class="form-control bg-dark text-white border-secondary" required>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Password</label>
                        <input type="password" name="password" class="form-control bg-dark text-white border-secondary" required>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Role</label>
                        <select name="role" class="form-select bg-dark text-white border-secondary">
                            <option value="STAFF">Staff (Read Only/Basic)</option>
                            <option value="FINANCE">Finance (Process Payments)</option>
                            <option value="GOD">GOD (Full Access)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-info w-100 fw-bold">CREATE ACCOUNT</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header border-secondary">TEAM MEMBERS</div>
            <div class="card-body p-0">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead>
                        <tr class="text-secondary text-uppercase text-xs">
                            <th>ID</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($staff as $s): ?>
                        <tr>
                            <td>#<?= $s['id'] ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($s['username']) ?></td>
                            <td>
                                <?php 
                                $badges = ['GOD'=>'bg-warning text-dark', 'FINANCE'=>'bg-success', 'STAFF'=>'bg-secondary'];
                                $badge = $badges[$s['role']] ?? 'bg-secondary';
                                ?>
                                <span class="badge <?= $badge ?>"><?= $s['role'] ?></span>
                            </td>
                            <td>
                                <?php if($s['is_active']): ?>
                                    <span class="text-success"><i class="bi bi-circle-fill" style="font-size:8px;"></i> Active</span>
                                <?php else: ?>
                                    <span class="text-danger"><i class="bi bi-slash-circle"></i> Banned</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= $s['last_login'] ? date('M d H:i', strtotime($s['last_login'])) : 'Never' ?></td>
                            <td>
                                <?php if($s['role'] !== 'GOD' || $_SESSION['admin_id'] == $s['id']): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $s['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <?= $s['is_active'] ? 'Disable' : 'Enable' ?>
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