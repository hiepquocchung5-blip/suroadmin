<?php
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');
$pageTitle = "Floor Heatmap (Profitability)";
requireRole(['GOD', 'FINANCE']);
require_once ADMIN_BASE_PATH . '/layout/main.php';

$islandId = isset($_GET['island']) ? (int)$_GET['island'] : 1;

// Fetch Machines with Deep Profit Stats
$sql = "
    SELECT 
        m.id, m.machine_number, m.status,
        m.total_payout, m.total_laps,
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

$islands = $pdo->query("SELECT id, name FROM islands WHERE id <= 5 ORDER BY id ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-black text-white italic tracking-widest m-0"><i class="bi bi-thermometer-high text-danger"></i> THERMAL MAP</h2>
</div>

<!-- FILTER -->
<div class="glass-card mb-4 border-secondary p-3 shadow-sm">
    <form method="GET" class="d-flex align-items-center gap-4">
        <input type="hidden" name="route" value="fleet/heatmap">
        
        <div class="d-flex align-items-center gap-2">
            <label class="fw-bold text-gray-400 small text-uppercase tracking-widest"><i class="bi bi-map-fill text-info me-1"></i> Region:</label>
            <select name="island" class="form-select bg-black text-white border-secondary fw-bold rounded-pill shadow-inner w-auto" onchange="this.form.submit()">
                <?php foreach($islands as $isl): ?>
                    <option value="<?= $isl['id'] ?>" <?= $islandId == $isl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($isl['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="ms-auto d-none d-md-flex gap-3 text-[10px] font-bold tracking-widest uppercase">
            <span class="d-flex align-items-center gap-1 text-success"><span class="w-3 h-3 bg-success rounded-sm shadow-[0_0_10px_lime]"></span> PROFITABLE</span>
            <span class="d-flex align-items-center gap-1 text-danger"><span class="w-3 h-3 bg-danger rounded-sm shadow-[0_0_10px_red] animate-pulse"></span> BLEEDING (PLAYER WIN)</span>
            <span class="d-flex align-items-center gap-1 text-secondary"><span class="w-3 h-3 bg-secondary rounded-sm"></span> NEUTRAL</span>
        </div>
    </form>
</div>

<!-- HEATMAP 3D GRID -->
<div class="glass-card p-4 border border-white border-opacity-10 bg-black bg-opacity-80">
    <div class="row g-2">
        <?php foreach($machines as $m): 
            $profit = $m['total_bet'] - $m['total_payout'];
            $roi = $m['total_bet'] > 0 ? ($profit / $m['total_bet']) * 100 : 0; 
            
            // Thermal Color Logic
            $colorClass = 'bg-secondary';
            $opacity = 0.1;
            $pulse = '';
            $border = 'border-white border-opacity-5';

            if ($profit > 0) {
                $colorClass = 'bg-success';
                $opacity = min(0.8, max(0.2, $roi / 20)); 
                $border = 'border-success border-opacity-50';
            } elseif ($profit < 0) {
                $colorClass = 'bg-danger';
                $opacity = min(0.9, max(0.3, abs($roi) / 20)); 
                $border = 'border-danger shadow-[0_0_15px_red]';
                if ($opacity > 0.6) $pulse = 'animate-pulse';
            }
            
            // Tooltip data
            $tooltip = "Unit #{$m['machine_number']} | Laps: {$m['total_laps']}\nIn: " . number_format($m['total_bet']) . "\nOut: " . number_format($m['total_payout']) . "\nNet: " . number_format($profit);
        ?>
        <div class="col" style="width: 10%; flex: 0 0 10%; min-width: 60px;">
            <div 
                class="position-relative border rounded-2 overflow-hidden transition-all duration-300 hover:scale-[1.15] hover:z-10 cursor-crosshair <?= $border ?> <?= $pulse ?>" 
                style="height: 70px; background: #0a0a0a;" 
                title="<?= $tooltip ?>"
            >
                <!-- Heat Layer -->
                <div class="absolute inset-0 <?= $colorClass ?> transition-opacity duration-1000 mix-blend-screen" style="opacity: <?= $opacity ?>;"></div>
                
                <!-- Content -->
                <div class="relative z-10 w-100 h-100 d-flex flex-col justify-content-center align-items-center p-1">
                    <span class="fw-black text-white font-mono" style="font-size: 13px; text-shadow: 0 2px 4px rgba(0,0,0,0.8);"><?= $m['machine_number'] ?></span>
                    
                    <?php if($m['status'] === 'occupied'): ?>
                        <div class="mt-1 bg-black bg-opacity-80 rounded-circle p-1 border border-warning shadow-[0_0_5px_gold]">
                            <i class="bi bi-person-fill text-warning" style="font-size: 0.6rem;"></i>
                        </div>
                    <?php elseif($m['status'] === 'maintenance'): ?>
                        <i class="bi bi-wrench text-secondary mt-1" style="font-size: 0.7rem;"></i>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($machines)): ?>
            <div class="col-12 text-center text-muted py-5">No machines found in this sector.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>