<?php
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');
$pageTitle = "Global System Config";
requireRole(['GOD']);

$pdo->exec("CREATE TABLE IF NOT EXISTS `system_settings` (`key_name` varchar(50) NOT NULL, `value` text, PRIMARY KEY (`key_name`))");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
        'welcome_bonus' => (int)$_POST['welcome_bonus'],
        'min_deposit' => (int)$_POST['min_deposit'],
        'global_announcement' => cleanInput($_POST['global_announcement'])
    ];

    foreach ($settings as $key => $val) {
        $pdo->prepare("INSERT INTO system_settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?")->execute([$key, $val, $val]);
    }
    
    $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'system_settings')")->execute([$_SESSION['admin_id'], "Updated Global Config"]);
    $success = "Core system parameters overwritten successfully.";
}

$current = [];
$rows = $pdo->query("SELECT * FROM system_settings")->fetchAll();
foreach($rows as $r) $current[$r['key_name']] = $r['value'];

$maint = $current['maintenance_mode'] ?? '0';
$bonus = $current['welcome_bonus'] ?? '5000';
$minDep = $current['min_deposit'] ?? '1000';
$announcement = $current['global_announcement'] ?? '';

require_once ADMIN_BASE_PATH . '/layout/main.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="text-white fw-black mb-0 italic tracking-widest"><i class="bi bi-sliders text-info"></i> MASTER CONFIG</h3>
</div>

<?php if(isset($success)): ?><div class="alert bg-success bg-opacity-20 text-success border border-success fw-bold shadow-sm animate-pulse"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-md-7">
        <form method="POST">
            <!-- MAIN SWITCH -->
            <div class="glass-card mb-4 border-danger border-opacity-50 overflow-hidden shadow-[0_0_30px_rgba(239,68,68,0.15)]">
                <div class="bg-danger bg-opacity-20 text-danger fw-black p-3 border-b border-danger border-opacity-30 tracking-widest italic flex items-center">
                    <i class="bi bi-power me-2"></i> DEFCON STATUS
                </div>
                <div class="p-4 bg-black bg-opacity-60 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="text-white fw-bold mb-1">Maintenance Mode</h5>
                        <p class="text-gray-400 small mb-0 font-mono">Locks out all player connections. Admin API remains active.</p>
                    </div>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" name="maintenance_mode" style="width: 4em; height: 2em; cursor:pointer;" <?= $maint == '1' ? 'checked' : '' ?>>
                    </div>
                </div>
            </div>

            <!-- ECONOMY -->
            <div class="glass-card mb-4 border-secondary overflow-hidden">
                <div class="bg-black bg-opacity-50 text-white fw-bold p-3 border-b border-white border-opacity-10 tracking-widest italic flex items-center">
                    <i class="bi bi-cash-stack text-success me-2"></i> ECONOMY BASELINES
                </div>
                <div class="p-4 bg-black bg-opacity-30 row g-4">
                    <div class="col-md-6">
                        <label class="form-label text-gray-400 small fw-bold text-uppercase tracking-widest">Welcome Bonus</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-dark border-secondary text-success font-mono font-bold">MMK</span>
                            <input type="number" name="welcome_bonus" class="form-control bg-dark text-white border-secondary font-mono fw-bold" value="<?= $bonus ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-gray-400 small fw-bold text-uppercase tracking-widest">Min Deposit</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-dark border-secondary text-white font-mono font-bold">MMK</span>
                            <input type="number" name="min_deposit" class="form-control bg-dark text-white border-secondary font-mono fw-bold" value="<?= $minDep ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ANNOUNCEMENTS -->
            <div class="glass-card mb-4 border-secondary overflow-hidden">
                <div class="bg-black bg-opacity-50 text-white fw-bold p-3 border-b border-white border-opacity-10 tracking-widest italic flex items-center">
                    <i class="bi bi-megaphone text-warning me-2"></i> GLOBAL BROADCAST
                </div>
                <div class="p-4 bg-black bg-opacity-30">
                    <label class="form-label text-gray-400 small fw-bold text-uppercase tracking-widest">Lobby Marquee Text</label>
                    <textarea name="global_announcement" class="form-control bg-dark text-white border-secondary rounded-lg p-3 font-mono text-sm" rows="3" placeholder="Leave empty to hide ticker..."><?= htmlspecialchars($announcement) ?></textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-info w-100 fw-black py-4 shadow-[0_0_20px_rgba(13,202,240,0.3)] text-lg tracking-widest hover:scale-[1.02] transition-transform">
                <i class="bi bi-hdd-network"></i> PUSH CONFIGURATION
            </button>
        </form>
    </div>

    <div class="col-md-5">
        <div class="glass-card bg-black bg-opacity-80 border-info border-opacity-30 p-0 overflow-hidden">
            <div class="bg-info bg-opacity-10 text-info fw-bold p-3 border-b border-info border-opacity-20 tracking-widest italic">
                SERVER ENVIRONMENT
            </div>
            <ul class="list-group list-group-flush font-mono text-sm">
                <li class="list-group-item bg-transparent text-white border-white border-opacity-10 d-flex justify-content-between p-4">
                    <span class="text-gray-500 uppercase tracking-widest">PHP Engine</span>
                    <span class="text-info fw-bold">v<?= phpversion() ?></span>
                </li>
                <li class="list-group-item bg-transparent text-white border-white border-opacity-10 d-flex justify-content-between p-4">
                    <span class="text-gray-500 uppercase tracking-widest">Server IP</span>
                    <span class="text-white fw-bold"><?= $_SERVER['SERVER_ADDR'] ?? '127.0.0.1' ?></span>
                </li>
                <li class="list-group-item bg-transparent text-white border-white border-opacity-10 d-flex justify-content-between p-4">
                    <span class="text-gray-500 uppercase tracking-widest">Database</span>
                    <span class="text-success fw-bold flex items-center gap-2"><i class="bi bi-database-check"></i> Connected</span>
                </li>
                <li class="list-group-item bg-transparent text-white border-white border-opacity-10 d-flex justify-content-between p-4">
                    <span class="text-gray-500 uppercase tracking-widest">Load Average</span>
                    <span class="text-yellow-400 fw-bold"><?= function_exists('sys_getloadavg') ? implode(' ', sys_getloadavg()) : 'N/A' ?></span>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>