<?php
// Ensure this is loaded via the router
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');

$pageTitle = "Island Jackpot Control";
requireRole(['GOD', 'FINANCE']);

// --- 1. HANDLE UPDATES & GAMIFIED ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole(['GOD']);
    $action = $_POST['action'];
    $id = (int)$_POST['id'];
    $islandName = cleanInput($_POST['island_name'] ?? 'Unknown');

    try {
        if ($action === 'inject_funds' || $action === 'quick_inject') {
            $amount = (float)$_POST['amount'];
            $pdo->prepare("UPDATE global_jackpots SET current_amount = current_amount + ? WHERE id = ?")->execute([$amount, $id]);
            $success = "Mainframe pool for $islandName boosted by " . number_format($amount) . " MMK.";
            $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'global_jackpots')")->execute([$_SESSION['admin_id'], "Injected $amount MMK into $islandName GJP"]);
        } 
        elseif ($action === 'save_config') {
            $base = (float)$_POST['base_seed'];
            $trigger = (float)$_POST['trigger_amount'];
            $max = (float)$_POST['max_amount'];
            $rate = (float)$_POST['contribution_rate'];

            $pdo->prepare("UPDATE global_jackpots SET base_seed = ?, trigger_amount = ?, max_amount = ?, contribution_rate = ? WHERE id = ?")
                ->execute([$base, $trigger, $max, $rate, $id]);
                
            $success = "Configuration matrix re-calibrated for $islandName.";
            $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'global_jackpots')")->execute([$_SESSION['admin_id'], "Updated $islandName GJP Config Matrix"]);
        }
        elseif ($action === 'reset_pool') {
            $pdo->prepare("UPDATE global_jackpots SET current_amount = base_seed WHERE id = ?")->execute([$id]);
            $success = "Pool for $islandName has been hard-reset to its base seed.";
            $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'global_jackpots')")->execute([$_SESSION['admin_id'], "Hard Reset $islandName GJP"]);
        }
        elseif ($action === 'hyper_drop') {
            // Gamely Feature: Force the cap to be just slightly above the current amount, forcing an imminent drop.
            $pdo->prepare("UPDATE global_jackpots SET max_amount = current_amount + 50000 WHERE id = ?")->execute([$id]);
            $success = "HYPER DROP PROTOCOL ACTIVATED for $islandName. Cap lowered to force immediate payout.";
            $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'global_jackpots')")->execute([$_SESSION['admin_id'], "Triggered Hyper Drop on $islandName GJP"]);
        }
    } catch (Exception $e) {
        $error = "Action failed: " . $e->getMessage();
    }
}

// --- 3. FETCH DATA ---
$sql = "
    SELECT j.*, i.name as island_name, i.id as island_actual_id 
    FROM global_jackpots j
    JOIN islands i ON j.island_id = i.id
    WHERE i.id <= 5
    ORDER BY i.id ASC
";
$jackpots = $pdo->query($sql)->fetchAll();

require_once ADMIN_BASE_PATH . '/layout/main.php';
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h2 class="fw-black text-warning italic tracking-widest m-0 drop-shadow-[0_0_15px_rgba(234,179,8,0.3)]"><i class="bi bi-trophy-fill"></i> SECTOR JACKPOTS</h2>
        <p class="text-muted small mt-1 font-mono">Manage independent, escalating Grand Jackpot pools and payout thresholds for the 5 Active Worlds.</p>
    </div>
    
    <div class="badge bg-black border border-warning text-warning px-4 py-2 font-mono fs-6 shadow-[0_0_15px_rgba(234,179,8,0.2)]">
        <?= count($jackpots) ?> ACTIVE POOLS
    </div>
</div>

<?php if(isset($success)): ?><div class="alert bg-success bg-opacity-20 text-success border border-success fw-bold shadow-sm animate-pulse"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert bg-danger bg-opacity-20 text-danger border border-danger fw-bold shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?></div><?php endif; ?>

<div class="row g-4">
    <?php foreach($jackpots as $jp): 
        $base = (float)$jp['base_seed'];
        $trigger = (float)$jp['trigger_amount'];
        $max = (float)$jp['max_amount'];
        $current = (float)$jp['current_amount'];
        
        $isHot = $current >= $trigger && $current < $max;
        $isCritical = $current >= $max;
        $distanceToDrop = $max - $current;
        
        $range = $max - $base;
        $progressPct = $range > 0 ? min(100, max(0, (($current - $base) / $range) * 100)) : 100;

        $colors = [1 => 'danger', 2 => 'info', 3 => 'warning', 4 => 'pink-500', 5 => 'purple-500'];
        $themeColor = $colors[$jp['island_actual_id']] ?? 'secondary';
    ?>
    <div class="col-md-6 col-xl-4 col-xxl-3">
        <div class="glass-card p-0 border-<?= $themeColor ?> border-opacity-50 overflow-hidden shadow-[0_0_30px_rgba(0,0,0,0.4)] position-relative h-100 flex flex-col transition-transform hover:-translate-y-2">
            
            <!-- Dynamic Status Header -->
            <div class="bg-black bg-opacity-80 p-3 border-b border-<?= $themeColor ?> border-opacity-30 d-flex justify-content-between align-items-center relative z-10">
                <span class="text-white fw-bold tracking-widest uppercase fs-6"><i class="bi bi-map-fill text-<?= $themeColor ?> me-1"></i> <?= htmlspecialchars($jp['island_name']) ?></span>
                <?php if($isCritical): ?>
                    <span class="badge bg-danger text-white border border-danger px-2 py-1 rounded-pill font-mono text-[9px] animate-pulse shadow-[0_0_10px_red]">
                        <i class="bi bi-exclamation-circle-fill me-1"></i>CRITICAL
                    </span>
                <?php elseif($isHot): ?>
                    <span class="badge bg-warning text-dark border border-warning px-2 py-1 rounded-pill font-mono text-[9px] animate-pulse shadow-[0_0_10px_gold]">
                        <i class="bi bi-fire me-1"></i>HOT
                    </span>
                <?php else: ?>
                    <span class="badge bg-success bg-opacity-20 text-success border border-success px-2 py-1 rounded-pill font-mono text-[9px]">
                        <i class="bi bi-battery-charging animate-pulse me-1"></i>BUILDING
                    </span>
                <?php endif; ?>
            </div>
            
            <!-- Ticker / Amount -->
            <div class="card-body text-center pt-4 pb-3 bg-gradient-to-b from-<?= $themeColor ?>/10 to-transparent relative z-10">
                <h6 class="text-<?= $themeColor ?> fw-bold tracking-widest mb-2 small text-[10px] uppercase">Current Liquidity Pool</h6>
                <h2 class="fw-black text-white font-mono drop-shadow-[0_0_15px_rgba(255,255,255,0.3)] mb-0 lh-1 <?= $isCritical ? 'text-danger drop-shadow-[0_0_20px_red]' : '' ?>" style="font-size: 2.2rem;">
                    <?= number_format($current) ?>
                </h2>
                <div class="text-muted mt-1 fw-bold tracking-widest font-mono text-[10px] mb-3">MMK</div>

                <!-- Limits Indicator -->
                <div class="px-4">
                    <div class="d-flex justify-content-between text-[9px] text-gray-500 font-mono mb-1">
                        <span>Base: <?= number_format($base / 1000000, 1) ?>M</span>
                        <span class="text-danger fw-bold">Cap: <?= number_format($max / 1000000, 1) ?>M</span>
                    </div>
                    <div class="progress bg-dark border border-secondary rounded-pill overflow-hidden" style="height: 6px;">
                        <div class="progress-bar <?= $isCritical ? 'bg-danger' : ($isHot ? 'bg-warning' : 'bg-success') ?> shadow-[0_0_10px_currentColor] <?= $isHot ? 'animate-pulse' : '' ?>" style="width: <?= $progressPct ?>%"></div>
                    </div>
                    <div class="text-[9px] text-gray-400 mt-2 tracking-widest uppercase">
                        Distance to Cap: <span class="text-white fw-bold"><?= number_format($distanceToDrop) ?> MMK</span>
                    </div>
                </div>
            </div>
            
            <!-- Dynamic Controls & Gamified Tools -->
            <div class="bg-black bg-opacity-60 p-4 border-t border-white border-opacity-5 mt-auto relative z-10 space-y-4">
                
                <!-- Gamely Feature: Quick Chips -->
                <div>
                    <label class="text-muted text-[9px] fw-bold text-uppercase tracking-widest mb-2"><i class="bi bi-plus-circle-fill text-success"></i> Quick Juice (Hype The Pot)</label>
                    <div class="d-flex gap-2">
                        <?php 
                        $chips = [100000 => '100K', 500000 => '500K', 1000000 => '1M'];
                        foreach($chips as $val => $label): ?>
                            <form method="POST" class="flex-fill m-0" onsubmit="return confirm('Instantly add <?= $label ?> to <?= $jp['island_name'] ?>?');">
                                <input type="hidden" name="id" value="<?= $jp['id'] ?>">
                                <input type="hidden" name="island_name" value="<?= htmlspecialchars($jp['island_name']) ?>">
                                <input type="hidden" name="action" value="quick_inject">
                                <input type="hidden" name="amount" value="<?= $val ?>">
                                <button type="submit" class="btn btn-dark w-100 border border-success text-success fw-black text-[10px] py-2 hover:bg-success hover:text-dark transition-colors shadow-sm rounded-pill">
                                    +<?= $label ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Gamely Feature: Hyper Drop Protocol -->
                <form method="POST" onsubmit="return confirm('DANGER: This will forcefully lower the cap to trigger an immediate jackpot payout. Proceed?');">
                    <input type="hidden" name="id" value="<?= $jp['id'] ?>">
                    <input type="hidden" name="island_name" value="<?= htmlspecialchars($jp['island_name']) ?>">
                    <input type="hidden" name="action" value="hyper_drop">
                    <button type="submit" class="btn btn-outline-danger w-100 fw-black tracking-widest text-[10px] py-2 rounded-pill shadow-[0_0_10px_rgba(239,68,68,0.3)] hover:scale-105 active:scale-95 transition-transform" <?= $isCritical ? 'disabled' : '' ?>>
                        <i class="bi bi-radioactive me-1"></i> FORCE HYPER-DROP
                    </button>
                </form>

                <!-- Advanced Configuration Matrix -->
                <form method="POST" class="bg-black bg-opacity-40 p-3 rounded-xl border border-white border-opacity-10 shadow-inner">
                    <input type="hidden" name="id" value="<?= $jp['id'] ?>">
                    <input type="hidden" name="island_name" value="<?= htmlspecialchars($jp['island_name']) ?>">
                    <input type="hidden" name="action" value="save_config">
                    
                    <label class="text-info text-[9px] fw-bold text-uppercase tracking-widest d-block mb-2"><i class="bi bi-sliders"></i> Configuration Matrix</label>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="text-muted text-[8px] text-uppercase">Base Seed</label>
                            <input type="number" name="base_seed" class="form-control form-control-sm bg-dark text-white border-secondary font-mono text-[10px]" value="<?= $base ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="text-muted text-[8px] text-uppercase">Hot Trigger</label>
                            <input type="number" name="trigger_amount" class="form-control form-control-sm bg-dark text-white border-secondary font-mono text-[10px]" value="<?= $trigger ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="text-muted text-[8px] text-uppercase text-danger">Must-Hit Cap</label>
                            <input type="number" name="max_amount" class="form-control form-control-sm bg-dark text-danger border-secondary font-mono text-[10px]" value="<?= $max ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="text-muted text-[8px] text-uppercase">Siphon Rate (0.01 = 1%)</label>
                            <input type="number" step="0.001" name="contribution_rate" class="form-control form-control-sm bg-dark text-white border-secondary font-mono text-[10px]" value="<?= $jp['contribution_rate'] ?>" required>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-info w-100 fw-bold border-opacity-50 tracking-widest text-[10px] py-2 text-dark">
                        SAVE CONFIGURATION
                    </button>
                </form>
                
                <div class="bg-white bg-opacity-5 rounded p-3 border border-white border-opacity-10 text-[10px]">
                    <div class="d-flex justify-content-between mb-2 border-b border-white border-opacity-10 pb-1">
                        <span class="text-gray-500 uppercase fw-bold">Last Cracked By</span>
                        <span class="text-info fw-bold font-mono"><?= $jp['last_won_by'] ?? '---' ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-gray-500 uppercase fw-bold">Amount Yielded</span>
                        <span class="text-success fw-bold font-mono"><?= $jp['last_won_amount'] ? '+'.number_format($jp['last_won_amount']) : '0' ?></span>
                    </div>
                    
                    <!-- Hard Reset Action (Moved to bottom, less prominent) -->
                    <form method="POST" onsubmit="return confirm('Hard reset this jackpot back to its configured base seed of <?= number_format($base) ?> MMK?');" class="mt-3">
                        <input type="hidden" name="id" value="<?= $jp['id'] ?>">
                        <input type="hidden" name="island_name" value="<?= htmlspecialchars($jp['island_name']) ?>">
                        <input type="hidden" name="action" value="reset_pool">
                        <button class="btn btn-sm btn-dark text-danger border border-danger border-opacity-30 w-100 fw-bold tracking-widest text-[9px] py-1.5 hover:bg-danger hover:text-white transition-colors">
                            <i class="bi bi-arrow-counterclockwise"></i> HARD RESET (<?= number_format($base/1000000, 1) ?>M)
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>