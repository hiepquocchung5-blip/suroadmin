<?php
// Ensure this is loaded via the router
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');

$pageTitle = "Reel Spawn Rates Control";
requireRole(['GOD']);

// Handle Saving New Weights
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_rates') {
    $islandId = (int)$_POST['island_id'];
    
    try {
        $pdo->beginTransaction();
        
        for ($i = 1; $i <= 3; $i++) {
            $sym1 = (int)$_POST["reel_{$i}_sym_1"];
            $sym2 = (int)$_POST["reel_{$i}_sym_2"];
            $sym3 = (int)$_POST["reel_{$i}_sym_3"];
            $sym4 = (int)$_POST["reel_{$i}_sym_4"];
            $sym5 = (int)$_POST["reel_{$i}_sym_5"];
            $sym6 = (int)$_POST["reel_{$i}_sym_6"];
            $sym7 = (int)$_POST["reel_{$i}_sym_7"];

            $sql = "INSERT INTO reel_spawn_rates (island_id, reel_index, sym_1, sym_2, sym_3, sym_4, sym_5, sym_6, sym_7) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE sym_1=VALUES(sym_1), sym_2=VALUES(sym_2), sym_3=VALUES(sym_3), sym_4=VALUES(sym_4), sym_5=VALUES(sym_5), sym_6=VALUES(sym_6), sym_7=VALUES(sym_7)";
            $pdo->prepare($sql)->execute([$islandId, $i, $sym1, $sym2, $sym3, $sym4, $sym5, $sym6, $sym7]);
        }

        $pdo->commit();
        $success = "Reel Spawn Rates updated for Island #$islandId.";
        $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'reel_spawn_rates')")->execute([$_SESSION['admin_id'], "Updated Spawn Rates for Island #$islandId"]);
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Update failed: " . $e->getMessage();
    }
}

// Fetch Selection
$currentIsland = isset($_GET['island']) ? (int)$_GET['island'] : 1;
$islands = $pdo->query("SELECT id, name FROM islands WHERE id <= 5 ORDER BY id ASC")->fetchAll();

// Fetch Rates for selected Island
$stmtRates = $pdo->prepare("SELECT * FROM reel_spawn_rates WHERE island_id = ? ORDER BY reel_index ASC");
$stmtRates->execute([$currentIsland]);
$rates = $stmtRates->fetchAll(PDO::FETCH_ASSOC);

// Format into easily accessible array or fallback
$formattedRates = [];
foreach ($rates as $r) {
    $formattedRates[$r['reel_index']] = $r;
}

// Fallback logic if DB empty
for ($i = 1; $i <= 3; $i++) {
    if (!isset($formattedRates[$i])) {
        $formattedRates[$i] = ['sym_1'=>10, 'sym_2'=>40, 'sym_3'=>100, 'sym_4'=>200, 'sym_5'=>200, 'sym_6'=>250, 'sym_7'=>200];
    }
}

require_once ADMIN_BASE_PATH . '/layout/main.php';
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h2 class="fw-black text-warning italic tracking-widest mb-0"><i class="bi bi-gear-wide-connected"></i> MATHEMATICS ENGINE</h2>
        <p class="text-muted small mt-1">Control exact symbol spawn probabilities per reel.</p>
    </div>
</div>

<!-- HEADER NAV -->
<div class="glass-card p-3 mb-4 d-flex gap-2 flex-wrap">
    <?php foreach($islands as $isl): ?>
        <a href="?route=content/spawn_rates&island=<?= $isl['id'] ?>" class="btn btn-sm <?= $isl['id'] == $currentIsland ? 'btn-warning fw-bold text-dark shadow-lg' : 'btn-outline-secondary' ?>">
            <?= htmlspecialchars($isl['name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if(isset($success)): ?><div class="alert bg-success bg-opacity-20 text-success border border-success fw-bold shadow-sm animate-pulse"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert bg-danger bg-opacity-20 text-danger border border-danger fw-bold shadow-sm"><i class="bi bi-x-circle-fill me-2"></i><?= $error ?></div><?php endif; ?>

<form method="POST">
    <input type="hidden" name="action" value="save_rates">
    <input type="hidden" name="island_id" value="<?= $currentIsland ?>">

    <div class="row g-4">
        <?php for ($reel = 1; $reel <= 3; $reel++): 
            $data = $formattedRates[$reel];
            $totalWeight = $data['sym_1'] + $data['sym_2'] + $data['sym_3'] + $data['sym_4'] + $data['sym_5'] + $data['sym_6'] + $data['sym_7'];
        ?>
        <div class="col-md-4">
            <div class="glass-card border-secondary h-100 overflow-hidden">
                <div class="bg-black bg-opacity-50 p-3 border-b border-white border-opacity-10 text-center">
                    <h4 class="fw-black text-white italic tracking-widest m-0 text-uppercase">REEL <?= $reel ?></h4>
                    <div class="text-muted small font-mono mt-1">Total Weight: <span class="text-info"><?= $totalWeight ?></span></div>
                </div>

                <div class="p-4 space-y-3 bg-black bg-opacity-30">
                    <?php 
                    $symLabels = [
                        1 => ['7 (Jackpot)', 'text-danger'],
                        2 => ['Character', 'text-purple-400'],
                        3 => ['BAR', 'text-orange-400'],
                        4 => ['Bell', 'text-yellow-400'],
                        5 => ['Melon', 'text-success'],
                        6 => ['Cherry', 'text-pink-400'],
                        7 => ['Replay', 'text-cyan-400']
                    ];
                    
                    for ($s = 1; $s <= 7; $s++): 
                        $val = $data["sym_$s"];
                        $pct = $totalWeight > 0 ? round(($val / $totalWeight) * 100, 2) : 0;
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small fw-bold <?= $symLabels[$s][1] ?> text-uppercase"><?= $symLabels[$s][0] ?></span>
                            <span class="small font-mono text-gray-400"><?= $pct ?>%</span>
                        </div>
                        <input type="number" name="reel_<?= $reel ?>_sym_<?= $s ?>" value="<?= $val ?>" min="0" class="form-control bg-dark text-white border-secondary font-mono text-sm shadow-inner" required>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <div class="mt-4 text-end">
        <button type="submit" class="btn btn-warning fw-black px-5 py-3 shadow-[0_0_15px_rgba(234,179,8,0.4)] text-dark hover:scale-105 transition-transform">
            <i class="bi bi-save-fill me-2"></i> PUSH SPAWN RATES TO LIVE ENGINE
        </button>
    </div>
</form>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>