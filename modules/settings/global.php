<?php
$pageTitle = "Global System Config";
require_once '../../layout/main.php';
requireRole(['GOD']);

// 1. Ensure Table Exists (Auto-Migration for Step 24)
$pdo->exec("CREATE TABLE IF NOT EXISTS `system_settings` (
    `key_name` varchar(50) NOT NULL,
    `value` text,
    PRIMARY KEY (`key_name`)
)");

// 2. Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
        'welcome_bonus' => (int)$_POST['welcome_bonus'],
        'min_deposit' => (int)$_POST['min_deposit'],
        'global_announcement' => cleanInput($_POST['global_announcement'])
    ];

    foreach ($settings as $key => $val) {
        $sql = "INSERT INTO system_settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?";
        $pdo->prepare($sql)->execute([$key, $val, $val]);
    }
    
    // Audit
    $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'system_settings')")
        ->execute([$_SESSION['admin_id'], "Updated Global Config"]);
        
    $success = "System settings updated successfully.";
}

// 3. Fetch Current Settings
$current = [];
$rows = $pdo->query("SELECT * FROM system_settings")->fetchAll();
foreach($rows as $r) $current[$r['key_name']] = $r['value'];

// Defaults
$maint = $current['maintenance_mode'] ?? '0';
$bonus = $current['welcome_bonus'] ?? '5000';
$minDep = $current['min_deposit'] ?? '1000';
$announcement = $current['global_announcement'] ?? '';
?>

<?php if(isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <form method="POST">
            <!-- MAIN SWITCH -->
            <div class="card mb-4 border-danger">
                <div class="card-header bg-danger bg-opacity-10 border-danger text-danger fw-bold">
                    <i class="bi bi-power me-2"></i> SYSTEM STATUS
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-white">Maintenance Mode</h5>
                            <p class="card-text text-muted small">If enabled, players cannot login or spin. Admin access remains.</p>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="maintenance_mode" style="width: 3em; height: 1.5em;" <?= $maint == '1' ? 'checked' : '' ?>>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ECONOMY -->
            <div class="card mb-4">
                <div class="card-header border-secondary text-info fw-bold">ECONOMY SETTINGS</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small">New User Welcome Bonus (MMK)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-white">MMK</span>
                            <input type="number" name="welcome_bonus" class="form-control bg-dark text-white border-secondary" value="<?= $bonus ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Minimum Deposit Limit (MMK)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-white">MMK</span>
                            <input type="number" name="min_deposit" class="form-control bg-dark text-white border-secondary" value="<?= $minDep ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ANNOUNCEMENTS -->
            <div class="card mb-4">
                <div class="card-header border-secondary text-warning fw-bold">GLOBAL ANNOUNCEMENT</div>
                <div class="card-body">
                    <label class="form-label text-muted small">Marquee Text (Visible in Game Lobby)</label>
                    <textarea name="global_announcement" class="form-control bg-dark text-white border-secondary" rows="3"><?= htmlspecialchars($announcement) ?></textarea>
                    <div class="form-text text-muted">Leave empty to disable the marquee ticker.</div>
                </div>
            </div>

            <button type="submit" class="btn btn-info w-100 fw-bold py-3">SAVE CONFIGURATION</button>
        </form>
    </div>

    <!-- INFO SIDEBAR -->
    <div class="col-md-6">
        <div class="card bg-dark border-secondary mb-4">
            <div class="card-header bg-transparent border-secondary">SERVER INFO</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item bg-transparent text-white border-secondary d-flex justify-content-between">
                    <span>PHP Version</span>
                    <span class="font-monospace text-info"><?= phpversion() ?></span>
                </li>
                <li class="list-group-item bg-transparent text-white border-secondary d-flex justify-content-between">
                    <span>Server IP</span>
                    <span class="font-monospace text-info"><?= $_SERVER['SERVER_ADDR'] ?? '127.0.0.1' ?></span>
                </li>
                <li class="list-group-item bg-transparent text-white border-secondary d-flex justify-content-between">
                    <span>Database</span>
                    <span class="font-monospace text-info">MySQL / MariaDB</span>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- Sidebar Toggle Logic included in footer.php, but specific UI logic here -->
<script>
    // Simple script to toggle sidebar class
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('collapsed');
        document.getElementById('mainWrapper').classList.toggle('expanded');
    }
</script>

<?php require_once '../../layout/footer.php'; ?>