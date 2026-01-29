<?php
$pageTitle = "Payment Configuration";
require_once '../../layout/main.php';
requireRole(['GOD', 'FINANCE']);

// Handle Add/Edit/Toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $val = (int)$_POST['val']; // 1 or 0
        $pdo->prepare("UPDATE payment_methods SET is_active = ? WHERE id = ?")->execute([$val, $id]);
        $success = "Status updated.";
    } 
    elseif ($action === 'save') {
        $provider = cleanInput($_POST['provider_name']);
        $accName = cleanInput($_POST['account_name']);
        $accNum = cleanInput($_POST['account_number']);
        $logo = cleanInput($_POST['logo_url']); // Optional URL
        
        $sql = "INSERT INTO payment_methods (provider_name, account_name, account_number, logo_url, is_active) VALUES (?, ?, ?, ?, 1)";
        $pdo->prepare($sql)->execute([$provider, $accName, $accNum, $logo]);
        $success = "New payment channel added.";
        
        // Audit
        $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'payment_methods')")
            ->execute([$_SESSION['admin_id'], "Added Payment Method: $provider"]);
    }
}

// Fetch Methods
$methods = $pdo->query("SELECT * FROM payment_methods ORDER BY is_active DESC, id ASC")->fetchAll();
?>

<?php if(isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<div class="row">
    <!-- ADD FORM -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header border-secondary text-warning fw-bold">ADD BANK / WALLET</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="save">
                    <div class="mb-3">
                        <label class="text-muted small">Provider Name</label>
                        <select name="provider_name" class="form-select bg-dark text-white border-secondary">
                            <option value="KBZPay">KBZPay</option>
                            <option value="WavePay">WavePay</option>
                            <option value="CB Pay">CB Pay</option>
                            <option value="AYA Pay">AYA Pay</option>
                            <option value="USDT">USDT (TRC20)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Account Name</label>
                        <input type="text" name="account_name" class="form-control bg-dark text-white border-secondary" placeholder="e.g. U Kyaw" required>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Account Number / Address</label>
                        <input type="text" name="account_number" class="form-control bg-dark text-white border-secondary" placeholder="09..." required>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Logo URL (Optional)</label>
                        <input type="text" name="logo_url" class="form-control bg-dark text-white border-secondary" placeholder="https://...">
                    </div>
                    <button type="submit" class="btn btn-warning w-100 fw-bold text-dark">ADD CHANNEL</button>
                </form>
            </div>
        </div>
    </div>

    <!-- LIST -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header border-secondary">ACTIVE CHANNELS</div>
            <div class="card-body p-0">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead>
                        <tr class="text-secondary text-uppercase text-xs">
                            <th>Provider</th>
                            <th>Account Details</th>
                            <th>Status</th>
                            <th class="text-end">Fast Switch</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($methods as $m): ?>
                        <tr class="<?= $m['is_active'] ? '' : 'opacity-50' ?>">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if($m['logo_url']): ?>
                                        <img src="<?= htmlspecialchars($m['logo_url']) ?>" width="24" height="24" class="rounded-circle">
                                    <?php else: ?>
                                        <div class="bg-secondary rounded-circle" style="width:24px;height:24px;"></div>
                                    <?php endif; ?>
                                    <span class="fw-bold"><?= htmlspecialchars($m['provider_name']) ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="font-monospace text-white"><?= htmlspecialchars($m['account_number']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($m['account_name']) ?></div>
                            </td>
                            <td>
                                <?php if($m['is_active']): ?>
                                    <span class="badge bg-success">ONLINE</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">OFFLINE</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <form method="POST">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                    <input type="hidden" name="val" value="<?= $m['is_active'] ? 0 : 1 ?>">
                                    
                                    <button type="submit" class="btn btn-sm fw-bold <?= $m['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                        <?= $m['is_active'] ? 'TURN OFF' : 'TURN ON' ?>
                                    </button>
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

<?php require_once '../../layout/footer.php'; ?>