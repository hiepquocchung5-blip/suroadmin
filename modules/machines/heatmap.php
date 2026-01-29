<?php
$pageTitle = "Floor Heatmap (Profitability)";
require_once '../../layout/main.php';

$islandId = isset($_GET['island']) ? (int)$_GET['island'] : 1;

// Fetch Machines with Profit Stats
// Profit = Total Bets - Total Payouts
// Since we store total_payout and total_laps, we can estimate Total Bets 
// Estimate: Avg Bet * Laps (We don't store Total Bet on machine table in V10 schema, so we calculate via Logs or use estimate)
// CORRECT APPROACH: Join with Logs Aggregation
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
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" class="d-flex align-items-center gap-3">
            <label class="fw-bold text-white">Select Region:</label>
            <select name="island" class="form-select bg-dark text-white border-secondary w-auto" onchange="this.form.submit()">
                <?php foreach($islands as $isl): ?>
                    <option value="<?= $isl['id'] ?>" <?= $islandId == $isl['id'] ? 'selected' : '' ?>><?= $isl['name'] ?></option>
                <?php endforeach; ?>
            </select>
            
            <div class="ms-auto d-flex gap-2 text-xs">
                <span class="badge bg-success">PROFITABLE (HOUSE WIN)</span>
                <span class="badge bg-danger">BLEEDING (PLAYER WIN)</span>
                <span class="badge bg-secondary">NEUTRAL/COLD</span>
            </div>
        </form>
    </div>
</div>

<!-- HEATMAP GRID -->
<div class="card bg-transparent border-0">
    <div class="row g-1">
        <?php foreach($machines as $m): 
            $profit = $m['total_bet'] - $m['total_payout'];
            $roi = $m['total_bet'] > 0 ? ($profit / $m['total_bet']) * 100 : 0; // House Edge %
            
            // Color Logic
            // High Positive ROI = Green (Good for House)
            // Negative ROI = Red (Bad for House, Good for Player)
            $color = 'bg-secondary';
            $opacity = '0.2';
            
            if ($profit > 0) {
                $color = 'bg-success';
                $opacity = min(1, max(0.2, $roi / 20)); // Stronger green for higher hold
            } elseif ($profit < 0) {
                $color = 'bg-danger';
                $opacity = min(1, max(0.2, abs($roi) / 20)); // Stronger red for higher loss
            }
            
            $style = "background-color: var(--bs-$color-rgb) !important; opacity: $opacity;";
        ?>
        <div class="col-1" style="width: 10%;"> <!-- 10x10 Grid for 100 machines -->
            <div class="position-relative border border-dark" style="height: 60px; background: #111;" title="Machine #<?= $m['machine_number'] ?> | Profit: <?= number_format($profit) ?>">
                <!-- Heat Layer -->
                <div class="position-absolute w-100 h-100 <?= $color ?>" style="opacity: <?= $opacity ?>;"></div>
                
                <!-- Content -->
                <div class="position-relative z-10 w-100 h-100 d-flex flex-col justify-content-center align-items-center p-1">
                    <span class="fw-bold text-white small">#<?= $m['machine_number'] ?></span>
                    <?php if($m['status'] !== 'free'): ?>
                        <i class="bi bi-person-fill text-warning" style="font-size: 0.7rem;"></i>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once '../../layout/footer.php'; ?>