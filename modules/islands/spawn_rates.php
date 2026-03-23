<?php
// Ensure this is loaded via the router
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');

$pageTitle = "Reel Spawn Rates Control";
requireRole(['GOD']);

// Handle Saving New Weights
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_rates') {
    $islandId = (int)$_POST['island_id'];
    $applyToAll = isset($_POST['apply_to_all']) ? true : false;
    
    // Determine which islands we are updating
    $targetIslands = $applyToAll ? [1, 2, 3, 4, 5] : [$islandId];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($targetIslands as $tId) {
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
                $pdo->prepare($sql)->execute([$tId, $i, $sym1, $sym2, $sym3, $sym4, $sym5, $sym6, $sym7]);
            }
        }

        $pdo->commit();
        $targetMsg = $applyToAll ? "ALL 5 ISLANDS" : "Island #$islandId";
        $success = "Reel Spawn Matrix synced to active servers for $targetMsg.";
        $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'reel_spawn_rates')")->execute([$_SESSION['admin_id'], "Updated Spawn Matrix for $targetMsg"]);
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Matrix Sync Failed: " . $e->getMessage();
    }
}

// Fetch Selection
$currentIsland = isset($_GET['island']) ? (int)$_GET['island'] : 1;
$islands = $pdo->query("SELECT id, name FROM islands ORDER BY id ASC")->fetchAll();

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

<style>
    /* Circuit Chaos / Neon Tech Enhancements */
    .circuit-bg {
        background-color: #050505;
        background-image: radial-gradient(rgba(0, 243, 255, 0.1) 1px, transparent 1px);
        background-size: 20px 20px;
    }
    .weight-bar { transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    .locked-input { opacity: 0.7; pointer-events: none; filter: grayscale(50%); }
</style>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h2 class="fw-black text-warning italic tracking-widest mb-0 drop-shadow-[0_0_10px_rgba(234,179,8,0.5)]">
            <i class="bi bi-cpu"></i> SPAWN MATRIX
        </h2>
        <p class="text-muted small mt-1 font-mono">Real-time symbol probability manipulation and theoretical telemetry.</p>
    </div>
    
    <div class="d-flex gap-2">
        <button class="btn btn-outline-danger fw-bold px-3 rounded-pill shadow-sm" onclick="resetToOriginal()">
            <i class="bi bi-arrow-counterclockwise"></i> REVERT
        </button>
        <button class="btn btn-outline-success fw-bold px-3 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="bi bi-download"></i> IMPORT
        </button>
        <button class="btn btn-outline-info fw-bold px-3 rounded-pill shadow-sm" onclick="exportMatrix()">
            <i class="bi bi-clipboard-data"></i> EXPORT
        </button>
    </div>
</div>

<div class="glass-card p-3 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3 border-warning border-opacity-30 shadow-[0_0_20px_rgba(234,179,8,0.15)] circuit-bg">
    <div class="d-flex gap-2 flex-wrap">
        <?php foreach($islands as $isl): ?>
            <a href="?route=content/spawn_rates&island=<?= $isl['id'] ?>" class="btn btn-sm <?= $isl['id'] == $currentIsland ? 'btn-warning fw-bold text-dark shadow-[0_0_15px_gold]' : 'btn-outline-secondary hover:text-white' ?> rounded-pill px-3">
                <i class="bi bi-map-fill me-1 opacity-50"></i> <?= htmlspecialchars($isl['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
    
    <div class="d-flex gap-3 align-items-center">
        <!-- Safety Lock -->
        <div class="form-check form-switch m-0 d-flex align-items-center gap-2 bg-danger bg-opacity-20 px-3 py-1 rounded-pill border border-danger border-opacity-50">
            <input class="form-check-input mt-0" type="checkbox" id="matrixLockToggle" onchange="toggleMatrixLock()" checked style="cursor:pointer; width: 2em; height: 1em;">
            <label class="form-check-label text-danger fw-black text-[10px] uppercase tracking-widest" for="matrixLockToggle">
                <i class="bi bi-lock-fill" id="lockIcon"></i> SAFE MODE
            </label>
        </div>
    </div>
</div>

<?php if(isset($success)): ?><div class="alert bg-success bg-opacity-20 text-success border border-success fw-bold shadow-sm animate-pulse"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert bg-danger bg-opacity-20 text-danger border border-danger fw-bold shadow-sm"><i class="bi bi-x-circle-fill me-2"></i><?= $error ?></div><?php endif; ?>

<form method="POST" id="ratesForm">
    <input type="hidden" name="action" value="save_rates">
    <input type="hidden" name="island_id" value="<?= $currentIsland ?>">

    <!-- LIVE TELEMETRY HUD -->
    <div class="glass-card mb-4 p-4 border-info border-opacity-50 bg-gradient-to-r from-blue-900/20 via-black to-black shadow-[0_0_30px_rgba(13,202,240,0.15)] position-relative overflow-hidden">
        <div class="position-absolute top-0 end-0 opacity-10"><i class="bi bi-graph-up-arrow display-1"></i></div>
        
        <div class="row align-items-center position-relative z-10">
            <div class="col-md-4 border-end border-white border-opacity-10">
                <h6 class="text-info fw-black tracking-widest uppercase mb-3"><i class="bi bi-calculator"></i> Live Telemetry</h6>
                <div class="d-flex justify-content-between align-items-end mb-2">
                    <span class="text-gray-400 text-[10px] uppercase font-bold tracking-widest">Est. Hit Freq (5 Lines)</span>
                    <span class="fs-3 fw-black font-mono text-white drop-shadow-[0_0_10px_cyan]" id="calcHitFreq">0.00%</span>
                </div>
                <div class="progress bg-dark border border-secondary rounded-pill" style="height: 6px;">
                    <div id="barHitFreq" class="progress-bar bg-info shadow-[0_0_10px_cyan]" style="width: 0%"></div>
                </div>
            </div>
            
            <div class="col-md-4 border-end border-white border-opacity-10 px-md-4">
                <div class="d-flex justify-content-between text-[10px] text-gray-400 fw-bold uppercase tracking-widest mb-2">
                    <span>Volatility DNA (GJP/LOGO/7)</span>
                    <span class="text-danger font-mono fw-black" id="calcHighYield">0% HIGH YIELD</span>
                </div>
                <div class="progress rounded-pill bg-dark border border-secondary shadow-inner" style="height: 12px;">
                    <div id="barDnaHigh" class="progress-bar bg-gradient-to-r from-red-600 to-orange-500 shadow-[0_0_10px_red]" style="width: 0%" title="High Value Symbols"></div>
                    <div id="barDnaLow" class="progress-bar bg-gradient-to-r from-green-600 to-cyan-500 opacity-75" style="width: 0%" title="Low Value Symbols"></div>
                </div>
                <div class="text-[9px] text-gray-500 mt-2 font-mono uppercase tracking-widest">
                    <i class="bi bi-robot text-purple-400 me-1"></i> <span id="calcInsight" class="text-gray-300">Calculating trajectory...</span>
                </div>
            </div>

            <div class="col-md-4 ps-md-4">
                <h6 class="text-gray-400 text-[10px] uppercase font-bold tracking-widest mb-2"><i class="bi bi-lightning-charge text-warning"></i> Quick Modifiers</h6>
                <div class="d-grid gap-2">
                    <div class="btn-group btn-group-sm w-100">
                        <button type="button" class="btn btn-outline-danger fw-bold text-[10px]" onclick="adjustWeights('high', 1.1)">+10% HIGH YIELD</button>
                        <button type="button" class="btn btn-outline-danger fw-bold text-[10px]" onclick="adjustWeights('high', 0.9)">-10% HIGH YIELD</button>
                    </div>
                    <div class="btn-group btn-group-sm w-100">
                        <button type="button" class="btn btn-outline-success fw-bold text-[10px]" onclick="adjustWeights('low', 1.1)">+10% LOW YIELD</button>
                        <button type="button" class="btn btn-outline-success fw-bold text-[10px]" onclick="adjustWeights('low', 0.9)">-10% LOW YIELD</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- REELS GRID -->
    <div class="row g-4" id="matrixContainer">
        <?php for ($reel = 1; $reel <= 3; $reel++): 
            $data = $formattedRates[$reel];
            $totalWeight = $data['sym_1'] + $data['sym_2'] + $data['sym_3'] + $data['sym_4'] + $data['sym_5'] + $data['sym_6'] + $data['sym_7'];
        ?>
        <div class="col-md-4">
            <div class="glass-card border-secondary h-100 overflow-hidden shadow-[0_10px_30px_rgba(0,0,0,0.5)]">
                <div class="bg-black bg-opacity-80 p-3 border-b border-white border-opacity-10 text-center relative overflow-hidden">
                    <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/circuit-board.png')] opacity-10 pointer-events-none mix-blend-color-dodge"></div>
                    <h4 class="fw-black text-white italic tracking-widest m-0 text-uppercase relative z-10">REEL <?= $reel ?></h4>
                    <div class="text-muted small font-mono mt-1 relative z-10">Net Weight: <span class="text-info fw-bold" id="total_weight_<?= $reel ?>"><?= $totalWeight ?></span></div>
                </div>

                <div class="p-4 space-y-4 bg-black bg-opacity-40">
                    <?php 
                    $symLabels = [
                        1 => ['Grand Jackpot (GJP)', 'text-red-500 border-red-500', 'bg-red-500'],
                        2 => ['LOGO', 'text-purple-400 border-purple-500', 'bg-purple-500'],
                        3 => ['7 (Seven)', 'text-orange-400 border-orange-500', 'bg-orange-500'],
                        4 => ['Melon', 'text-green-400 border-green-500', 'bg-green-500'],
                        5 => ['Bell', 'text-yellow-400 border-yellow-500', 'bg-yellow-500'],
                        6 => ['Cherry', 'text-pink-400 border-pink-500', 'bg-pink-500'],
                        7 => ['Replay', 'text-cyan-400 border-cyan-500', 'bg-cyan-500']
                    ];
                    
                    for ($s = 1; $s <= 7; $s++): 
                        $val = $data["sym_$s"];
                        $pct = $totalWeight > 0 ? round(($val / $totalWeight) * 100, 2) : 0;
                    ?>
                    <div class="position-relative">
                        <div class="d-flex justify-content-between mb-1 align-items-center">
                            <span class="text-[10px] fw-black tracking-widest uppercase <?= $symLabels[$s][1] ?> drop-shadow-sm">
                                <?= $symLabels[$s][0] ?>
                            </span>
                            <span class="small font-mono text-gray-400" id="pct_r<?= $reel ?>_s<?= $s ?>"><?= $pct ?>%</span>
                        </div>
                        <input type="number" name="reel_<?= $reel ?>_sym_<?= $s ?>" id="input_r<?= $reel ?>_s<?= $s ?>" value="<?= $val ?>" min="0" class="form-control bg-dark text-white border-secondary font-mono fw-bold text-sm shadow-inner weight-input transition-colors focus:border-info focus:ring-1 focus:ring-info locked-input" data-reel="<?= $reel ?>" data-sym="<?= $s ?>" required oninput="triggerRecalc()">
                        
                        <!-- Dynamic Visual Weight Bar -->
                        <div class="w-100 bg-black rounded-pill mt-1 overflow-hidden border border-white border-opacity-10" style="height: 3px;">
                            <div id="bar_r<?= $reel ?>_s<?= $s ?>" class="h-100 <?= $symLabels[$s][2] ?> weight-bar shadow-[0_0_5px_currentColor]" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <!-- SUBMISSION BAR -->
    <div class="glass-card mt-4 p-4 border border-warning border-opacity-50 d-flex justify-content-between align-items-center bg-black bg-opacity-80 flex-wrap gap-3 shadow-[0_0_30px_rgba(234,179,8,0.15)]">
        <div class="text-muted small font-mono text-[10px] d-flex align-items-center gap-4">
            <div class="d-none d-md-block">
                <i class="bi bi-shield-check text-success me-2"></i> 
                Matrix computations run instantly on the client. Click deploy to sync weights to the game servers.
            </div>
            
            <div class="form-check form-switch bg-warning bg-opacity-10 border border-warning border-opacity-50 px-3 py-2 rounded-3 d-flex align-items-center gap-2">
                <input class="form-check-input mt-0" type="checkbox" name="apply_to_all" id="applyAllSwitch" style="cursor:pointer; width: 2em; height: 1em;">
                <label class="form-check-label text-warning fw-black text-uppercase tracking-widest" for="applyAllSwitch">Apply to all 5 Islands</label>
            </div>
        </div>

        <button type="submit" id="deployBtn" class="btn btn-warning fw-black px-5 py-3 shadow-[0_0_20px_rgba(234,179,8,0.5)] text-dark hover:scale-105 active:scale-95 transition-transform tracking-widest disabled">
            <i class="bi bi-cloud-arrow-up-fill me-2"></i> DEPLOY TO SERVERS
        </button>
    </div>
</form>

<!-- IMPORT MODAL -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card bg-dark border-success">
            <div class="modal-header border-secondary bg-black bg-opacity-50">
                <h5 class="modal-title fw-black text-success italic tracking-widest"><i class="bi bi-file-code me-2"></i> IMPORT MATRIX JSON</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-gray-400 small mb-3">Paste a previously copied JSON matrix array below to instantly populate the 21 reel variables.</p>
                <textarea id="importJsonInput" class="form-control bg-black text-success font-mono border-secondary rounded-lg mb-3 shadow-inner" rows="6" placeholder='{"r1":[10,40,100,200,200,250,200], ...}'></textarea>
                <button type="button" onclick="importMatrixJson()" class="btn btn-success w-100 fw-bold shadow-[0_0_15px_rgba(34,197,94,0.4)]">INJECT DATA</button>
            </div>
        </div>
    </div>
</div>

<script>
// Original Data for Revert Failsafe
const ORIGINAL_RATES = <?= json_encode($formattedRates) ?>;

// --- MATRIX SAFETY LOCK ---
function toggleMatrixLock() {
    const isLocked = document.getElementById('matrixLockToggle').checked;
    const inputs = document.querySelectorAll('.weight-input');
    const deployBtn = document.getElementById('deployBtn');
    const lockIcon = document.getElementById('lockIcon');

    inputs.forEach(input => {
        if (isLocked) {
            input.classList.add('locked-input');
            input.readOnly = true;
        } else {
            input.classList.remove('locked-input');
            input.readOnly = false;
        }
    });

    if (isLocked) {
        deployBtn.classList.add('disabled');
        lockIcon.className = "bi bi-lock-fill";
    } else {
        deployBtn.classList.remove('disabled');
        lockIcon.className = "bi bi-unlock-fill";
    }
}

// --- QUICK SHIFT MODIFIERS ---
function adjustWeights(type, multiplier) {
    if (document.getElementById('matrixLockToggle').checked) {
        alert("Unlock Safe Mode first to apply modifiers.");
        return;
    }

    const highSymbols = [1, 2, 3]; // GJP, LOGO, 7
    const lowSymbols = [4, 5, 6, 7]; // Melon, Bell, Cherry, Replay

    const targetSymbols = type === 'high' ? highSymbols : lowSymbols;

    for (let r = 1; r <= 3; r++) {
        targetSymbols.forEach(s => {
            const input = document.getElementById(`input_r${r}_s${s}`);
            let currentVal = parseInt(input.value) || 0;
            input.value = Math.max(1, Math.round(currentVal * multiplier));
        });
    }

    // Flash border to indicate change
    document.querySelectorAll('.weight-input').forEach(el => {
        el.classList.add('border-warning', 'shadow-[0_0_10px_gold]');
        setTimeout(() => el.classList.remove('border-warning', 'shadow-[0_0_10px_gold]'), 300);
    });

    triggerRecalc();
}

function resetToOriginal() {
    if (!confirm("Revert all inputs to the current database settings?")) return;
    
    for (let r = 1; r <= 3; r++) {
        for (let s = 1; s <= 7; s++) {
            if (ORIGINAL_RATES[r] && ORIGINAL_RATES[r][`sym_${s}`]) {
                document.getElementById(`input_r${r}_s${s}`).value = ORIGINAL_RATES[r][`sym_${s}`];
            }
        }
    }
    triggerRecalc();
}

// --- LIVE MATHEMATICS ENGINE (Client-Side Simulation) ---
function triggerRecalc() {
    let rTotals = {1:0, 2:0, 3:0};
    let weights = {1:{}, 2:{}, 3:{}};

    // 1. Gather all weights and calc totals
    for (let r = 1; r <= 3; r++) {
        for (let s = 1; s <= 7; s++) {
            let val = parseInt(document.getElementById(`input_r${r}_s${s}`).value) || 0;
            weights[r][s] = val;
            rTotals[r] += val;
        }
        
        // Safety Warning
        const totalEl = document.getElementById(`total_weight_${r}`);
        totalEl.innerText = rTotals[r];
        if (rTotals[r] === 0) {
            totalEl.className = "text-danger fw-black animate-pulse";
            totalEl.innerText = "0 (FATAL ERROR)";
        } else {
            totalEl.className = "text-info fw-bold";
        }
        
        // Update individual percentages & visual bars
        for (let s = 1; s <= 7; s++) {
            let pct = rTotals[r] > 0 ? ((weights[r][s] / rTotals[r]) * 100).toFixed(1) : 0;
            document.getElementById(`pct_r${r}_s${s}`).innerText = pct + '%';
            document.getElementById(`bar_r${r}_s${s}`).style.width = pct + '%';
        }
    }

    // 2. Calculate Theoretical Hit Frequency (P(Win) across 5 lines)
    let totalHitProbLine = 0;
    if (rTotals[1] > 0 && rTotals[2] > 0 && rTotals[3] > 0) {
        for (let s = 1; s <= 7; s++) {
            let p1 = weights[1][s] / rTotals[1];
            let p2 = weights[2][s] / rTotals[2];
            let p3 = weights[3][s] / rTotals[3];
            totalHitProbLine += (p1 * p2 * p3);
        }
    }
    
    // Approx across 5 paylines
    let estHitFreq = Math.min(100, (totalHitProbLine * 5) * 100);
    document.getElementById('calcHitFreq').innerText = estHitFreq.toFixed(2) + '%';
    document.getElementById('barHitFreq').style.width = estHitFreq + '%';

    // 3. Volatility DNA Calculation
    let highYieldWeights = 0;
    let lowYieldWeights = 0;
    
    for (let r = 1; r <= 3; r++) {
        highYieldWeights += weights[r][1] + weights[r][2] + weights[r][3]; // GJP, LOGO, 7
        lowYieldWeights += weights[r][4] + weights[r][5] + weights[r][6] + weights[r][7];
    }
    
    let totalAll = highYieldWeights + lowYieldWeights;
    let highPct = totalAll > 0 ? Math.round((highYieldWeights / totalAll) * 100) : 0;
    let lowPct = 100 - highPct;
    
    document.getElementById('calcHighYield').innerText = highPct + '% HIGH YIELD';
    document.getElementById('barDnaHigh').style.width = highPct + '%';
    document.getElementById('barDnaLow').style.width = lowPct + '%';

    // 4. AI Insight Generator
    let insight = "";
    if (highPct >= 20) insight = "<span class='text-danger'>High variance detected. Expect rare, but massive payouts.</span>";
    else if (highPct <= 10) insight = "<span class='text-success'>Low variance drip-feed. Constant small wins to increase retention.</span>";
    else insight = "<span class='text-info'>Balanced mathematical ecosystem. Steady progression.</span>";
    document.getElementById('calcInsight').innerHTML = insight;
}

// Intelligent Presets Engine
function applyPreset(type) {
    if (document.getElementById('matrixLockToggle').checked) {
        alert("Unlock Safe Mode first to apply presets.");
        return;
    }

    const presets = {
        balanced: { r1: [10, 40, 100, 200, 200, 250, 200], r2: [5, 30, 80, 220, 220, 245, 200], r3: [2, 20, 60, 250, 250, 218, 200] },
        high_vol: { r1: [25, 60, 150, 100, 100, 50, 50], r2: [10, 40, 100, 150, 150, 80, 80], r3: [1, 10, 30, 300, 300, 100, 100] },
        low_vol:  { r1: [1, 10, 30, 300, 300, 500, 400], r2: [1, 10, 30, 300, 300, 500, 400], r3: [1, 10, 30, 300, 300, 500, 400] },
        teaser:   { r1: [100, 100, 100, 150, 150, 200, 200], r2: [100, 100, 100, 150, 150, 200, 200], r3: [1, 5, 10, 300, 300, 200, 200] }
    };

    const target = presets[type];
    if (!target) return;

    for (let r = 1; r <= 3; r++) {
        for (let s = 1; s <= 7; s++) {
            document.getElementById(`input_r${r}_s${s}`).value = target[`r${r}`][s-1];
        }
    }
    
    document.querySelectorAll('.weight-input').forEach(el => {
        el.classList.add('border-info', 'shadow-[0_0_10px_cyan]');
        setTimeout(() => el.classList.remove('border-info', 'shadow-[0_0_10px_cyan]'), 500);
    });

    triggerRecalc();
}

// JSON Matrix Export
function exportMatrix() {
    let matrix = { r1: [], r2: [], r3: [] };
    for(let r=1; r<=3; r++) {
        for(let s=1; s<=7; s++) {
            matrix[`r${r}`].push(parseInt(document.getElementById(`input_r${r}_s${s}`).value) || 0);
        }
    }
    navigator.clipboard.writeText(JSON.stringify(matrix));
    alert("Matrix JSON copied to clipboard!");
}

// JSON Matrix Import
function importMatrixJson() {
    if (document.getElementById('matrixLockToggle').checked) {
        alert("Unlock Safe Mode first to import data.");
        return;
    }

    try {
        const jsonStr = document.getElementById('importJsonInput').value;
        const matrix = JSON.parse(jsonStr);
        
        if (matrix.r1 && matrix.r2 && matrix.r3) {
            for(let r=1; r<=3; r++) {
                for(let s=1; s<=7; s++) {
                    if(matrix[`r${r}`][s-1] !== undefined) {
                        document.getElementById(`input_r${r}_s${s}`).value = matrix[`r${r}`][s-1];
                    }
                }
            }
            triggerRecalc();
            
            document.querySelectorAll('.weight-input').forEach(el => {
                el.classList.add('border-success', 'shadow-[0_0_10px_lime]');
                setTimeout(() => el.classList.remove('border-success', 'shadow-[0_0_10px_lime]'), 500);
            });
            
            const modalEl = document.getElementById('importModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            
            document.getElementById('importJsonInput').value = '';
        } else {
            alert("Invalid Matrix Format. Expected {r1:[], r2:[], r3:[]}");
        }
    } catch(e) {
        alert("Invalid JSON data. Please check syntax.");
    }
}

// Init calculation & lock state on load
document.addEventListener("DOMContentLoaded", () => {
    triggerRecalc();
    toggleMatrixLock(); // Initialize safe mode on load
});
</script>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>