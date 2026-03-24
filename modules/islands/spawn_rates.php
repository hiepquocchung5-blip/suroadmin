<?php
// Ensure this is loaded via the router
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');

$pageTitle = "Reel Spawn Rates Control";
requireRole(['GOD']);

// --- 1. HANDLE SAVING NEW WEIGHTS ---
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

// --- 2. FETCH SELECTION & DATA ---
$currentIsland = isset($_GET['island']) ? (int)$_GET['island'] : 1;
$islands = $pdo->query("SELECT id, name, rtp_rate FROM islands ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Rates for selected Island
$stmtRates = $pdo->prepare("SELECT * FROM reel_spawn_rates WHERE island_id = ? ORDER BY reel_index ASC");
$stmtRates->execute([$currentIsland]);
$rates = $stmtRates->fetchAll(PDO::FETCH_ASSOC);

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

// Fetch V5.5 Core Logic for Simulation Environment (Win Rates & Payouts)
$winRatesQuery = $pdo->query("SELECT * FROM island_win_rates");
$allWinRates = $winRatesQuery->fetchAll(PDO::FETCH_ASSOC);
$winRatesByIsland = [];

$payoutsQuery = $pdo->query("SELECT * FROM island_symbol_payouts");
$allPayouts = $payoutsQuery->fetchAll(PDO::FETCH_ASSOC);
$payoutsByIsland = [];

foreach($islands as $isl) {
    $winRatesByIsland[$isl['id']] = ['base_hit_rate'=>22.00000000]; // Default fallback
    $payoutsByIsland[$isl['id']] = ['sym_1_mult'=>100, 'sym_2_mult'=>20, 'sym_3_mult'=>10, 'sym_4_mult'=>10, 'sym_5_mult'=>15, 'sym_6_mult'=>2, 'sym_7_mult'=>0];
}
foreach ($allWinRates as $wr) { $winRatesByIsland[$wr['island_id']] = $wr; }
foreach ($allPayouts as $p) { $payoutsByIsland[$p['island_id']] = $p; }

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
    .terminal-container { background-color: rgba(0, 0, 0, 0.8); border: 1px solid rgba(0, 243, 255, 0.3); box-shadow: inset 0 0 30px rgba(0, 0, 0, 0.9); }
    .terminal-line { margin: 0; padding: 0; line-height: 1.5; word-wrap: break-word; font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: #00f3ff; }
</style>

<div class="d-flex justify-content-between align-items-end mb-4 relative z-10">
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
                <h6 class="text-info fw-black tracking-widest uppercase mb-3"><i class="bi bi-calculator"></i> Matrix Analysis</h6>
                <div class="d-flex justify-content-between align-items-end mb-2">
                    <span class="text-gray-400 text-[10px] uppercase font-bold tracking-widest">Base Hit Rate Config</span>
                    <span class="fs-4 fw-black font-mono text-cyan-300 drop-shadow-[0_0_10px_cyan]" id="displayBaseHitRate">
                        <?= number_format($winRatesByIsland[$currentIsland]['base_hit_rate'], 8) ?>%
                    </span>
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
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-info fw-black tracking-widest text-[11px] py-2 rounded-pill shadow-[0_0_15px_rgba(13,202,240,0.3)] hover:scale-105 active:scale-95 transition-transform" onclick="startMatrixSim()">
                        <i class="bi bi-play-circle-fill me-1"></i> TEST MATRIX (10K SPINS)
                    </button>
                    <div class="btn-group btn-group-sm w-100 mt-2">
                        <button type="button" class="btn btn-outline-danger fw-bold text-[9px]" onclick="adjustWeights('high', 1.1)">+10% HIGH</button>
                        <button type="button" class="btn btn-outline-success fw-bold text-[9px]" onclick="adjustWeights('low', 1.1)">+10% LOW</button>
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
                Simulation uses current inputs. Click deploy to sync these weights to the live database.
            </div>
            
            <div class="form-check form-switch bg-warning bg-opacity-10 border border-warning border-opacity-50 px-3 py-2 rounded-3 d-flex align-items-center gap-2">
                <input class="form-check-input mt-0" type="checkbox" name="apply_to_all" id="applyAllSwitch" style="cursor:pointer; width: 2em; height: 1em;">
                <label class="form-check-label text-warning fw-black text-uppercase tracking-widest" for="applyAllSwitch">Apply to all 5 Islands</label>
            </div>
        </div>

        <button type="submit" id="deployBtn" class="btn btn-warning fw-black px-5 py-3 shadow-[0_0_20px_rgba(234,179,8,0.5)] text-dark hover:scale-105 active:scale-95 transition-transform tracking-widest disabled">
            <i class="bi bi-cloud-arrow-up-fill me-2"></i> DEPLOY MATRIX TO SERVERS
        </button>
    </div>
</form>

<!-- QUICK SIMULATION MODAL -->
<div class="modal fade" id="simModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-info" style="background-color: #050505; box-shadow: 0 0 50px rgba(0,243,255,0.3);">
            <div class="modal-header border-info border-opacity-50 bg-info bg-opacity-10 py-3">
                <h6 class="modal-title font-mono text-info fw-bold"><i class="bi bi-cpu me-2"></i> MATRIX SIMULATOR (V5.5 ENGINE)</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="stopSimulation()"></button>
            </div>
            
            <div class="modal-body p-0 d-flex flex-column flex-lg-row">
                <!-- Terminal Output -->
                <div class="terminal-container p-4 flex-grow-1" style="height: 50vh; min-height: 400px; overflow-y: auto;">
                    <div id="simTerminal"></div>
                </div>
                
                <!-- Results Dashboard -->
                <div id="simResultsContainer" class="bg-black border-start border-secondary" style="flex: 0 0 350px; overflow-y: auto;">
                    <div id="simResults" class="p-4 d-none h-100 flex-column">
                        <div class="text-center mb-3 border-bottom border-secondary pb-2">
                            <h5 class="text-info font-mono fw-black mb-0">SANDBOX AUDIT</h5>
                        </div>
                        
                        <div class="d-grid gap-2 font-mono mb-4">
                            <div class="p-2 border border-secondary rounded bg-dark d-flex justify-content-between align-items-center">
                                <span class="text-muted" style="font-size: 10px;">SPINS PROCESSED</span>
                                <span class="text-white fs-5 fw-bold">10,000</span>
                            </div>
                            <div class="p-2 border border-info rounded bg-info bg-opacity-20 d-flex justify-content-between align-items-center shadow-[0_0_15px_rgba(0,243,255,0.3)]">
                                <span class="text-info" style="font-size: 10px;">ACTUAL RTP YIELD</span>
                                <span class="text-info fs-3 fw-black drop-shadow-md" id="resActualRtp">0%</span>
                            </div>
                            <div class="p-2 border border-secondary rounded bg-dark d-flex justify-content-between align-items-center">
                                <span class="text-gray-400" style="font-size: 10px;">TOTAL WIN FREQUENCY</span>
                                <span class="text-warning fs-5 fw-bold" id="resHitFreq">0%</span>
                            </div>
                        </div>
                        
                        <h6 class="text-gray-400 font-mono text-[10px] uppercase tracking-widest mb-2 border-bottom border-secondary pb-1">Spawn Drop Matrix (All Spins)</h6>
                        <div class="text-[10px] text-gray-300 font-mono flex-grow-1 bg-black bg-opacity-50 p-2 rounded border border-white border-opacity-5">
                            <div class="row text-center g-2" id="symDistro"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer border-info border-opacity-30 bg-black">
                <button type="button" class="btn btn-outline-secondary fw-bold font-mono" data-bs-dismiss="modal" onclick="stopSimulation()">CLOSE SANDBOX</button>
            </div>
        </div>
    </div>
</div>

<script>
// Original Data for Revert Failsafe
const ORIGINAL_RATES = <?= json_encode($formattedRates) ?>;

// Embedded Constants for Simulation
const ISL_ID = <?= $currentIsland ?>;
const ISL_DATA = <?= json_encode($islands[array_search($currentIsland, array_column($islands, 'id'))] ?? $islands[0]) ?>;
const WIN_RATES = <?= json_encode($winRatesByIsland[$currentIsland]) ?>;
const PAYOUTS = <?= json_encode($payoutsByIsland[$currentIsland]) ?>;

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

// --- LIVE DOM MATHEMATICS ENGINE ---
function triggerRecalc() {
    let rTotals = {1:0, 2:0, 3:0};
    let weights = {1:{}, 2:{}, 3:{}};

    for (let r = 1; r <= 3; r++) {
        for (let s = 1; s <= 7; s++) {
            let val = parseInt(document.getElementById(`input_r${r}_s${s}`).value) || 0;
            weights[r][s] = val;
            rTotals[r] += val;
        }
        
        const totalEl = document.getElementById(`total_weight_${r}`);
        totalEl.innerText = rTotals[r];
        if (rTotals[r] === 0) {
            totalEl.className = "text-danger fw-black animate-pulse";
            totalEl.innerText = "0 (FATAL)";
        } else {
            totalEl.className = "text-info fw-bold";
        }
        
        for (let s = 1; s <= 7; s++) {
            let pct = rTotals[r] > 0 ? ((weights[r][s] / rTotals[r]) * 100).toFixed(1) : 0;
            document.getElementById(`pct_r${r}_s${s}`).innerText = pct + '%';
            document.getElementById(`bar_r${r}_s${s}`).style.width = pct + '%';
        }
    }

    // Volatility DNA Calculation
    let highYieldWeights = 0;
    let lowYieldWeights = 0;
    
    for (let r = 1; r <= 3; r++) {
        highYieldWeights += weights[r][1] + weights[r][2] + weights[r][3]; 
        lowYieldWeights += weights[r][4] + weights[r][5] + weights[r][6] + weights[r][7];
    }
    
    let totalAll = highYieldWeights + lowYieldWeights;
    let highPct = totalAll > 0 ? Math.round((highYieldWeights / totalAll) * 100) : 0;
    let lowPct = 100 - highPct;
    
    document.getElementById('calcHighYield').innerText = highPct + '% HIGH YIELD';
    document.getElementById('barDnaHigh').style.width = highPct + '%';
    document.getElementById('barDnaLow').style.width = lowPct + '%';

    let insight = "";
    if (highPct >= 20) insight = "<span class='text-danger'>High variance detected. Expect rare, but massive payouts.</span>";
    else if (highPct <= 10) insight = "<span class='text-success'>Low variance drip-feed. Constant small wins.</span>";
    else insight = "<span class='text-info'>Balanced ecosystem. Steady progression.</span>";
    document.getElementById('calcInsight').innerHTML = insight;
}

// --- SANDBOX SIMULATION (Mirrors V5.5 Engine Logic) ---
let simInterval = null;

function startMatrixSim() {
    const term = document.getElementById('simTerminal');
    document.getElementById('simResults').classList.add('d-none');
    document.getElementById('simResults').classList.remove('d-flex');
    
    // Read Current Input Weights directly from the DOM
    const currentWeights = {
        1: { 1:parseInt(document.getElementById('input_r1_s1').value)||0, 2:parseInt(document.getElementById('input_r1_s2').value)||0, 3:parseInt(document.getElementById('input_r1_s3').value)||0, 4:parseInt(document.getElementById('input_r1_s4').value)||0, 5:parseInt(document.getElementById('input_r1_s5').value)||0, 6:parseInt(document.getElementById('input_r1_s6').value)||0, 7:parseInt(document.getElementById('input_r1_s7').value)||0 },
        2: { 1:parseInt(document.getElementById('input_r2_s1').value)||0, 2:parseInt(document.getElementById('input_r2_s2').value)||0, 3:parseInt(document.getElementById('input_r2_s3').value)||0, 4:parseInt(document.getElementById('input_r2_s4').value)||0, 5:parseInt(document.getElementById('input_r2_s5').value)||0, 6:parseInt(document.getElementById('input_r2_s6').value)||0, 7:parseInt(document.getElementById('input_r2_s7').value)||0 },
        3: { 1:parseInt(document.getElementById('input_r3_s1').value)||0, 2:parseInt(document.getElementById('input_r3_s2').value)||0, 3:parseInt(document.getElementById('input_r3_s3').value)||0, 4:parseInt(document.getElementById('input_r3_s4').value)||0, 5:parseInt(document.getElementById('input_r3_s5').value)||0, 6:parseInt(document.getElementById('input_r3_s6').value)||0, 7:parseInt(document.getElementById('input_r3_s7').value)||0 }
    };

    const targetRtp = parseFloat(ISL_DATA.rtp_rate || 70.0);
    const baseHitRate = parseFloat(WIN_RATES.base_hit_rate || 22.0);
    
    const multipliers = {
        1: parseFloat(PAYOUTS.sym_1_mult||100), 2: parseFloat(PAYOUTS.sym_2_mult||20), 3: parseFloat(PAYOUTS.sym_3_mult||10),
        4: parseFloat(PAYOUTS.sym_4_mult||10), 5: parseFloat(PAYOUTS.sym_5_mult||15), 6: parseFloat(PAYOUTS.sym_6_mult||2), 7: parseFloat(PAYOUTS.sym_7_mult||0)
    };
    const winSymWeights = {2: 5, 3: 10, 4: 25, 5: 20, 6: 25, 7: 15};
    
    let logBuffer = [
        `> Initializing Matrix Sandbox...`,
        `> Reading Active DOM Weights for Reel Generation...`,
        `> V5.5 AI Constraint: <span style="color:#0ff">Base Hit Rate ${baseHitRate}%</span>`,
        `> Executing 10,000 spins at 1,000 MMK bet...`,
        `--------------------------------------------------`
    ];
    term.innerHTML = logBuffer.join('<br/>');
    
    new bootstrap.Modal(document.getElementById('simModal')).show();

    const pickSymbol = (weightsObj) => {
        const arr = Object.values(weightsObj);
        const total = arr.reduce((a,b)=>a+parseInt(b), 0);
        let rand = Math.floor(Math.random() * total) + 1;
        let sum = 0;
        for(let i=1; i<=7; i++) {
            sum += parseInt(weightsObj[i]);
            if (rand <= sum) return i;
        }
        return 7;
    };

    const pickWinSymbol = () => {
        const arr = Object.values(winSymWeights);
        const total = arr.reduce((a,b)=>a+b, 0);
        let rand = Math.floor(Math.random() * total) + 1;
        let sum = 0;
        for (let [sym, w] of Object.entries(winSymWeights)) {
            sum += w;
            if (rand <= sum) return parseInt(sym);
        }
        return 7;
    };

    let spins = 0;
    const MAX_SPINS = 10000;
    const BATCH_SIZE = 1000; 
    const bet = 1000;
    
    let totalIn = 0, totalOut = 0, totalWinningSpins = 0;
    let hits = {1:0, 2:0, 3:0, 4:0, 5:0, 6:0, 7:0}; 
    const names = {1:'GJP', 2:'CHR', 3:'BAR', 4:'BEL', 5:'MEL', 6:'CHE', 7:'REP'};
    const colors = {1:'#ef4444', 2:'#a855f7', 3:'#f97316', 4:'#eab308', 5:'#22c55e', 6:'#ec4899', 7:'#06b6d4'};

    simInterval = setInterval(() => {
        let batchLogs = [];
        for(let b=0; b<BATCH_SIZE && spins < MAX_SPINS; b++) {
            spins++;
            totalIn += bet;

            // Generate physical board drops from the DOM inputs
            let r1 = pickSymbol(currentWeights[1]); let r2 = pickSymbol(currentWeights[2]); let r3 = pickSymbol(currentWeights[3]);
            hits[r1]++; hits[r2]++; hits[r3]++;

            // V5.5 10-Billion Scale RNG Engine
            let isHit = (Math.random() * 10000000000) <= (baseHitRate * 100000000);
            
            if (isHit) {
                let winSym = pickWinSymbol();
                let winAmt = bet * multipliers[winSym];
                totalOut += winAmt;
                if (winAmt > 0) totalWinningSpins++;
                
                if (multipliers[winSym] >= 10) {
                    batchLogs.push(`<div class="terminal-line"><span style="color:#0aa">[#${spins.toString().padStart(5,'0')}]</span> HIT! [${names[winSym]}]x3 -> <span style="color:#ff0">+${winAmt.toLocaleString()} MMK</span></div>`);
                }
            }
        }

        if (batchLogs.length > 0) {
            logBuffer = logBuffer.concat(batchLogs);
            if (logBuffer.length > 100) logBuffer = logBuffer.slice(logBuffer.length - 100);
            term.innerHTML = logBuffer.join('');
            term.scrollTop = term.scrollHeight;
        }

        if (spins >= MAX_SPINS) {
            clearInterval(simInterval);
            logBuffer.push(`<div class="terminal-line mt-3" style="color:#0f0; font-weight:900;">> SANDBOX SIMULATION COMPLETE.</div>`);
            term.innerHTML = logBuffer.join('');
            term.scrollTop = term.scrollHeight;
            
            let actualRtp = ((totalOut / totalIn) * 100).toFixed(2);
            let hitFreq = ((totalWinningSpins / MAX_SPINS) * 100).toFixed(2);
            document.getElementById('resActualRtp').innerText = `${actualRtp}%`;
            document.getElementById('resHitFreq').innerText = `${hitFreq}%`;
            
            const diff = actualRtp - targetRtp;
            const actEl = document.getElementById('resActualRtp');
            if (diff > 3) actEl.className = "text-danger fs-3 fw-black drop-shadow-[0_0_10px_red] animate-pulse";
            else if (diff < -3) actEl.className = "text-warning fs-3 fw-black";
            else actEl.className = "text-info fs-3 fw-black drop-shadow-[0_0_10px_cyan]";

            const totalSyms = MAX_SPINS * 3;
            let distHtml = '';
            for(let i=1; i<=7; i++) {
                let pct = ((hits[i] / totalSyms) * 100).toFixed(2);
                distHtml += `<div class="col-6 mb-2"><div class="d-flex justify-content-between border-bottom border-secondary pb-1 px-2"><span style="color:${colors[i]}; font-weight:bold;">${names[i]}</span><span class="text-white">${pct}%</span></div></div>`;
            }
            document.getElementById('symDistro').innerHTML = distHtml;
            
            document.getElementById('simResults').classList.remove('d-none');
            document.getElementById('simResults').classList.add('d-flex');
        }
    }, 40); 
}

function stopSimulation() {
    if (simInterval) clearInterval(simInterval);
}

// Init calculation & lock state on load
document.addEventListener("DOMContentLoaded", () => {
    triggerRecalc();
    toggleMatrixLock(); 
});
</script>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>