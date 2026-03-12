<?php
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');
$pageTitle = "Payment Configuration";
requireRole(['GOD', 'FINANCE']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    if ($action === 'toggle') {
        $id = (int)$_POST['id']; $val = (int)$_POST['val'];
        $pdo->prepare("UPDATE payment_methods SET is_active = ? WHERE id = ?")->execute([$val, $id]);
        $success = "Channel status updated.";
    } 
    elseif ($action === 'save') {
        $provider = cleanInput($_POST['provider_name']);
        $accName = cleanInput($_POST['account_name']);
        $accNum = cleanInput($_POST['account_number']);
        $logo = cleanInput($_POST['logo_url']); 
        $sql = "INSERT INTO payment_methods (provider_name, account_name, account_number, logo_url, is_active, admin_id) VALUES (?, ?, ?, ?, 1, ?)";
        $pdo->prepare($sql)->execute([$provider, $accName, $accNum, $logo, $_SESSION['admin_id']]);
        $success = "New payment channel deployed.";
        $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'payment_methods')")->execute([$_SESSION['admin_id'], "Added Payment Method: $provider"]);
    }
}

$methods = $pdo->query("SELECT pm.*, a.username as agent_name FROM payment_methods pm LEFT JOIN admin_users a ON pm.admin_id = a.id ORDER BY pm.is_active DESC, pm.id ASC")->fetchAll();
require_once ADMIN_BASE_PATH . '/layout/main.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="text-white fw-black mb-0 italic tracking-widest"><i class="bi bi-wallet2 text-success"></i> INBOUND CHANNELS</h3>
</div>

<?php if(isset($success)): ?><div class="alert bg-success bg-opacity-20 text-success border border-success fw-bold shadow-sm animate-pulse"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>

<div class="row g-4">
    <!-- ADD FORM -->
    <div class="col-md-4">
        <div class="glass-card p-0 border-success border-opacity-50 h-100">
            <div class="bg-success bg-opacity-20 text-success fw-black p-3 border-b border-success border-opacity-30 tracking-widest italic">
                <i class="bi bi-plus-lg me-1"></i> ADD WALLET
            </div>
            <div class="card-body p-4 bg-black bg-opacity-60">
                <form method="POST">
                    <input type="hidden" name="action" value="save">
                    <div class="mb-3">
                        <label class="text-gray-400 small fw-bold text-uppercase tracking-widest mb-1">Provider</label>
                        <select name="provider_name" class="form-select bg-dark text-white border-secondary rounded-lg">
                            <option value="KBZPay">KBZPay</option>
                            <option value="WavePay">WavePay</option>
                            <option value="CB Pay">CB Pay</option>
                            <option value="AYA Pay">AYA Pay</option>
                            <option value="USDT">USDT (TRC20)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="text-gray-400 small fw-bold text-uppercase tracking-widest mb-1">Account Name</label>
                        <input type="text" name="account_name" class="form-control bg-dark text-white border-secondary rounded-lg" placeholder="e.g. U Kyaw" required>
                    </div>
                    <div class="mb-3">
                        <label class="text-gray-400 small fw-bold text-uppercase tracking-widest mb-1">Number / Address</label>
                        <input type="text" name="account_number" class="form-control bg-dark text-white border-secondary rounded-lg font-mono" placeholder="09..." required>
                    </div>
                    <button type="submit" class="btn btn-success w-100 fw-black py-3 mt-2 shadow-[0_0_15px_rgba(34,197,94,0.4)] hover:scale-105 transition-transform">DEPLOY CHANNEL</button>
                </form>
            </div>
        </div>
    </div>

    <!-- LIST -->
    <div class="col-md-8">
        <div class="glass-card p-0 border-secondary overflow-hidden h-100">
            <div class="card-header bg-black bg-opacity-50 border-b border-white border-opacity-10 text-white fw-bold tracking-widest italic p-3">
                ACTIVE CHANNELS
            </div>
            <div class="table-responsive bg-black bg-opacity-40">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead>
                        <tr class="text-gray-500 text-uppercase text-[10px] tracking-widest border-b border-white border-opacity-10">
                            <th class="ps-4 py-3">Provider</th>
                            <th>Details</th>
                            <th>Agent</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Toggle</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php foreach($methods as $m): ?>
                        <tr class="border-b border-white border-opacity-5 hover:bg-white/5 transition-colors <?= $m['is_active'] ? '' : 'opacity-50 grayscale' ?>">
                            <td class="ps-4 fw-bold text-white"><?= htmlspecialchars($m['provider_name']) ?></td>
                            <td>
                                <div class="font-mono text-cyan-400 fw-bold"><?= htmlspecialchars($m['account_number']) ?></div>
                                <div class="text-[10px] text-gray-500 uppercase"><?= htmlspecialchars($m['account_name']) ?></div>
                            </td>
                            <td><span class="text-xs text-gray-400"><i class="bi bi-person"></i> <?= htmlspecialchars($m['agent_name'] ?? 'Unassigned') ?></span></td>
                            <td>
                                <?php if($m['is_active']): ?>
                                    <span class="badge bg-success bg-opacity-20 text-success border border-success border-opacity-50">ONLINE</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">OFFLINE</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <form method="POST" class="m-0">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                    <input type="hidden" name="val" value="<?= $m['is_active'] ? 0 : 1 ?>">
                                    <div class="form-check form-switch d-inline-block m-0">
                                        <input class="form-check-input" type="checkbox" onchange="this.form.submit()" <?= $m['is_active'] ? 'checked' : '' ?> style="cursor: pointer; width: 2.5em; height: 1.25em;">
                                    </div>
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

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>