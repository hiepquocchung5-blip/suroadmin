<?php
// Ensure this is loaded via the router
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');

$pageTitle = "Island Jackpot Control";
requireRole(['GOD', 'FINANCE']);

// --- 1. AUTO-MIGRATION & SYNC ---
// Ensure the table supports per-island jackpots
try {
    $pdo->exec("ALTER TABLE `global_jackpots` ADD COLUMN IF NOT EXISTS `island_id` INT DEFAULT NULL UNIQUE");
} catch(Exception $e) {}

// Automatically create an independent GJP for any island that doesn't have one yet
$pdo->exec("
    INSERT IGNORE INTO global_jackpots (name, island_id, current_amount, contribution_rate)
    SELECT CONCAT(name, ' GJP'), id, 3000000.00, 0.05 
    FROM islands 
    WHERE id NOT IN (SELECT island_id FROM global_jackpots WHERE island_id IS NOT NULL)
");

// --- 2. HANDLE UPDATES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole(['GOD']);
    $action = $_POST['action'];
    $id = (int)$_POST['id'];
    $islandName = cleanInput($_POST['island_name'] ?? 'Unknown');

    if ($action === 'update_seed') {
        $amount = (float)$_POST['amount'];
        $pdo->prepare("UPDATE global_jackpots SET current_amount = ? WHERE id = ?")->execute([$amount, $id]);
        $success = "Mainframe pool for $islandName manually injected with " . number_format($amount) . " MMK.";
        $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'global_jackpots')")->execute([$_SESSION['admin_id'], "Injected $amount MMK into $islandName GJP"]);
    } 
    elseif ($action === 'update_rate') {
        $rate = (float)$_POST['rate'];
        $pdo->prepare("UPDATE global_jackpots SET contribution_rate = ? WHERE id = ?")->execute([$rate, $id]);
        $success = "Siphon rate re-calibrated for $islandName to " . ($rate * 100) . "%.";
        $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'global_jackpots')")->execute([$_SESSION['admin_id'], "Changed $islandName GJP rate to $rate"]);
    }
    elseif ($action === 'reset_pool') {
        // Force reset back to 3 Million base
        $pdo->prepare("UPDATE global_jackpots SET current_amount = 3000000.00 WHERE id = ?")->execute([$id]);
        $success = "Pool for $islandName has been hard-reset to the 3,000,000 MMK base seed.";
        $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'global_jackpots')")->execute([$_SESSION['admin_id'], "Hard Reset $islandName GJP"]);
    }
}

// --- 3. FETCH DATA (Strictly limited to the 5 V3 Real-World Islands) ---
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
        <p class="text-muted small mt-1 font-mono">Manage independent Grand Jackpot pools and siphon rates for the 5 Active Worlds.</p>
    </div>
    
    <div class="badge bg-black border border-warning text-warning px-4 py-2 font-mono fs-6 shadow-[0_0_15px_rgba(234,179,8,0.2)]">
        <?= count($jackpots) ?> ACTIVE POOLS
    </div>
</div>

<?php if(isset($success)): ?><div class="alert bg-success bg-opacity-20 text-success border border-success fw-bold shadow-sm animate-pulse"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>

<div class="row g-4">
    <?php foreach($jackpots as $jp): 
        // Thematic UI styling mapped strictly to the 5 V3 Core Islands
        $colors = [
            1 => 'danger',        // Kyoto (Red)
            2 => 'info',          // Arcade (Cyan)
            3 => 'warning',       // Edo (Gold/Orange)
            4 => 'pink-500',      // Hanami (Pink)
            5 => 'purple-500',    // Yokai (Purple)
        ];
        $themeColor = $colors[$jp['island_actual_id']] ?? 'secondary';
    ?>
    <div class="col-md-6 col-xl-4 col-xxl-3">
        <div class="glass-card p-0 border-<?= $themeColor ?> border-opacity-50 overflow-hidden shadow-[0_0_30px_rgba(0,0,0,0.4)] position-relative h-100 flex flex-col transition-transform hover:-translate-y-2">
            
            <!-- Header -->
            <div class="bg-black bg-opacity-80 p-3 border-b border-<?= $themeColor ?> border-opacity-30 d-flex justify-content-between align-items-center relative z-10">
                <span class="text-white fw-bold tracking-widest uppercase fs-6"><i class="bi bi-map-fill text-<?= $themeColor ?> me-1"></i> <?= htmlspecialchars($jp['island_name']) ?></span>
                <span class="badge bg-success bg-opacity-20 text-success border border-success px-2 py-1 rounded-pill font-mono text-[9px]">
                    <span class="w-1.5 h-1.5 bg-success rounded-full inline-block animate-pulse me-1"></span>LINKED
                </span>
            </div>
            
            <!-- Ticker / Amount -->
            <div class="card-body text-center py-4 bg-gradient-to-b from-<?= $themeColor ?>/10 to-transparent relative z-10">
                <h6 class="text-<?= $themeColor ?> fw-bold tracking-widest mb-2 small text-[10px] uppercase">Current Liquidity Pool</h6>
                <h2 class="fw-black text-white font-mono drop-shadow-[0_0_15px_rgba(255,255,255,0.3)] mb-0 lh-1">
                    <?= number_format($jp['current_amount']) ?>
                </h2>
                <div class="text-muted mt-1 fw-bold tracking-widest font-mono text-[10px]">MMK</div>
            </div>
            
            <!-- Controls -->
            <div class="bg-black bg-opacity-60 p-4 border-t border-white border-opacity-5 mt-auto relative z-10">
                <form method="POST" class="mb-3">
                    <input type="hidden" name="id" value="<?= $jp['id'] ?>">
                    <input type="hidden" name="island_name" value="<?= htmlspecialchars($jp['island_name']) ?>">
                    
                    <label class="text-muted text-[9px] fw-bold text-uppercase tracking-widest mb-1"><i class="bi bi-pencil-square"></i> Manual Override (Seed)</label>
                    <div class="input-group input-group-sm mb-3 shadow-sm">
                        <input type="number" name="amount" class="form-control bg-dark text-white border-secondary font-mono" value="<?= $jp['current_amount'] ?>" step="10000">
                        <button name="action" value="update_seed" class="btn btn-<?= $themeColor ?> fw-black px-3 text-dark">INJECT</button>
                    </div>

                    <label class="text-muted text-[9px] fw-bold text-uppercase tracking-widest mb-1"><i class="bi bi-funnel"></i> Siphon Rate (%)</label>
                    <div class="input-group input-group-sm shadow-sm mb-3">
                        <span class="input-group-text bg-dark border-secondary text-gray-500">%</span>
                        <input type="number" step="0.001" name="rate" class="form-control bg-dark text-white border-secondary font-mono" value="<?= $jp['contribution_rate'] ?>">
                        <button name="action" value="update_rate" class="btn btn-outline-<?= $themeColor ?> fw-black px-3 bg-black">SET</button>
                    </div>
                </form>
                
                <!-- Hard Reset Action -->
                <div class="mb-3">
                    <form method="POST" onsubmit="return confirm('Hard reset this jackpot back to the 3,000,000 MMK base seed?');">
                        <input type="hidden" name="id" value="<?= $jp['id'] ?>">
                        <input type="hidden" name="island_name" value="<?= htmlspecialchars($jp['island_name']) ?>">
                        <input type="hidden" name="action" value="reset_pool">
                        <button class="btn btn-sm btn-outline-danger w-100 fw-bold border-opacity-50 tracking-widest text-[10px] py-2">
                            <i class="bi bi-arrow-counterclockwise"></i> HARD RESET TO BASE (3M)
                        </button>
                    </form>
                </div>

                <div class="bg-white bg-opacity-5 rounded p-2 border border-white border-opacity-10 text-[10px]">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-gray-500 uppercase fw-bold">Last Cracked By</span>
                        <span class="text-info fw-bold font-mono"><?= $jp['last_won_by'] ?? '---' ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-gray-500 uppercase fw-bold">Amount Yielded</span>
                        <span class="text-success fw-bold font-mono"><?= $jp['last_won_amount'] ? '+'.number_format($jp['last_won_amount']) : '0' ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>