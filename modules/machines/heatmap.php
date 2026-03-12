<?php
// Ensure this is loaded via the router
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');

$pageTitle = "Floor Heatmap (Profitability)";
requireRole(['GOD', 'FINANCE']);

// Include Header via Base Path
require_once ADMIN_BASE_PATH . '/layout/main.php';

$islandId = isset($_GET['island']) ? (int)$_GET['island'] : 1;

// Fetch Machines with Profit Stats
$sql = "
    SELECT 
        m.id, m.machine_number, m.status,
        m.total_payout,
        COALESCE(SUM(g.bet), 0) as total_bet
    FROM machines m
    LEFT JOIN game_logs g ON m.id = g.machine_id
    WHERE m.island_id = ?
    GROUP BY m.id
    ORDER BY m.machine_number ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$islandId]);
$machines = $stmt->fetchAll();

$islands = $pdo->query("SELECT id, name FROM islands")->fetchAll();
?>

<!-- FILTER -->
<div class="card mb-4 border-secondary bg-dark">
    <div class="card-body py-3">
        <form method="GET" class="d-flex align-items-center gap-3">
            <input type="hidden" name="route" value="fleet/heatmap">
            <label class="fw-bold text-white"><i class="bi bi-map-fill text-info me-2"></i> Select Region:</label>
            <select name="island" class="form-select bg-black text-white border-secondary w-auto" onchange="this.form.submit()">
                <?php foreach($islands as $isl): ?>
                    <option value="<?= $isl['id'] ?>" <?= $islandId == $isl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($isl['name']) ?></option>
                <?php endforeach; ?>
            </select>
            
            <div class="ms-auto d-flex gap-2 text-xs">
                <span class="badge bg-success">PROFITABLE (HOUSE WIN)</span>
                <span class="badge bg-danger">BLEEDING (PLAYER WIN)</span>
                <span class="badge bg-secondary">NEUTRAL / COLD</span>
            </div>
        </form>
    </div>
</div>

<!-- HEATMAP GRID -->
<div class="card bg-transparent border-0">
    <div class="row g-1">
        <?php foreach($machines as $m): 
            $profit = $m['total_bet'] - $m['total_payout'];
            $roi = $m['total_bet'] > 0 ? ($profit / $m['total_bet']) * 100 : 0; 
            
            // Color Logic
            $color = 'bg-secondary';
            $opacity = '0.2';
            
            if ($profit > 0) {
                $color = 'bg-success';
                $opacity = min(1, max(0.2, $roi / 20)); 
            } elseif ($profit < 0) {
                $color = 'bg-danger';
                $opacity = min(1, max(0.2, abs($roi) / 20)); 
            }
            
            $style = "background-color: var(--bs-$color-rgb) !important; opacity: $opacity;";
        ?>
        <div class="col-1" style="width: 10%;"> <!-- 10x10 Grid layout -->
            <div class="position-relative border border-dark rounded-1 overflow-hidden transition-all hover:scale-110" style="height: 60px; background: #111;" title="Machine #<?= $m['machine_number'] ?> | Profit: <?= number_format($profit) ?> MMK">
                <!-- Heat Layer -->
                <div class="position-absolute w-100 h-100 <?= $color ?>" style="opacity: <?= $opacity ?>;"></div>
                
                <!-- Content -->
                <div class="position-relative z-10 w-100 h-100 d-flex flex-col justify-content-center align-items-center p-1">
                    <span class="fw-bold text-white small font-mono">#<?= $m['machine_number'] ?></span>
                    <?php if($m['status'] !== 'free'): ?>
                        <i class="bi bi-person-fill text-warning mt-1" style="font-size: 0.7rem;" title="Occupied"></i>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>