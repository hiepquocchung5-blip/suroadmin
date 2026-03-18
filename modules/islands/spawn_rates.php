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

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h2 class="fw-black text-warning italic tracking-widest mb-0"><i class="bi bi-gear-wide-connected"></i> SPAWN MATRIX</h2>
        <p class="text-muted small mt-1 font-mono">Real-time symbol probability manipulation and theoretical telemetry.</p>
    </div>
    
    <div class="d-flex gap-2">
        <button class="btn btn-outline-success fw-bold px-4 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="bi bi-download me-1"></i> IMPORT JSON
        </button>
        <button class="btn btn-outline-info fw-bold px-4 rounded-pill shadow-sm" onclick="exportMatrix()">
            <i class="bi bi-clipboard-data me-1"></i> COPY JSON
        </button>
    </div>
</div>

<!-- HEADER NAV (Island Select) -->
<div class="glass-card p-3 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3 border-warning border-opacity-30 shadow-[0_0_20px_rgba(234,179,8,0.1)]">
    <div class="d-flex gap-2 flex-wrap">
        <?php foreach($islands as $isl): ?>
            <a href="?route=content/spawn_rates&island=<?= $isl['id'] ?>" class="btn btn-sm <?= $isl['id'] == $currentIsland ? 'btn-warning fw-bold text-dark shadow-[0_0_10px_gold]' : 'btn-outline-secondary hover:text-white' ?>">
                <i class="bi bi-map-fill me-1 opacity-50"></i> <?= htmlspecialchars($isl['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
    
    <!-- INTELLIGENT PRESETS ENGINE -->
    <div class="d-flex gap-2 align-items-center bg-black bg-opacity-50 p-1 rounded-pill border border-white border-opacity-10">
        <span class="text-muted small fw-bold text-uppercase tracking-widest ms-3 me-1"><i class="bi bi-cpu text-info"></i> AI PRESETS:</span>
        <button type="button" class="btn btn-sm btn-dark text-white rounded-pill px-3 hover:bg-gray-800 transition-colors" onclick="applyPreset('balanced')">Balanced</button>
        <button type="button" class="btn btn-sm btn-dark text-danger rounded-pill px-3 hover:bg-red-900 hover:text-white transition-colors" onclick="applyPreset('high_vol')">High Volatility</button>
        <button type="button" class="btn btn-sm btn-dark text-success rounded-pill px-3 hover:bg-green-900 hover:text-white transition-colors" onclick="applyPreset('low_vol')">Low Vol (Drip)</button>
        <button type="button" class="btn btn-sm btn-dark text-warning rounded-pill px-3 hover:bg-yellow-900 hover:text-white transition-colors" onclick="applyPreset('teaser')">Teaser Heavy</button>
    </div>
</div>

<?php if(isset($success)): ?><div class="alert bg-success bg-opacity-20 text-success border border-success fw-bold shadow-sm animate-pulse"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert bg-danger bg-opacity-20 text-danger border border-danger fw-bold shadow-sm"><i class="bi bi-x-circle-fill me-2"></i><?= $error ?></div><?php endif; ?>

<form method="POST" id="ratesForm">
    <input type="hidden" name="action" value="save_rates">
    <input type="hidden" name="island_id" value="<?= $currentIsland ?>">

    <!-- LIVE TELEMETRY DASHBOARD -->
    <div class="glass-card mb-4 p-4 border-info border-opacity-30 bg-gradient-to-r from-blue-900/20 to-black">
        <div class="row align-items-center">
            <div class="col-md-5 border-end border-white border-opacity-10">
                <h6 class="text-info fw-black tracking-widest uppercase mb-3"><i class="bi bi-calculator"></i> Live Theoretical Math</h6>
                <div class="d-flex justify-content-between align-items-end mb-2">
                    <span class="text-gray-400 text-[10px] uppercase font-bold tracking-widest">Est. Hit Frequency (5 Lines)</span>
                    <span class="fs-3 fw-black font-mono text-white drop-shadow-md" id="calcHitFreq">0.00%</span>
                </div>
                <div class="progress bg-dark border border-secondary" style="height: 4px;">
                    <div id="barHitFreq" class="progress-bar bg-info shadow-[0_0_10px_cyan]" style="width: 0%"></div>
                </div>
            </div>
            <div class="col-md-7 ps-md-4">
                <div class="d-flex justify-content-between text-[10px] text-gray-400 fw-bold uppercase tracking-widest mb-2">
                    <span>Volatility DNA</span>
                    <span class="text-danger font-mono" id="calcHighYield">0% HIGH YIELD</span>
                </div>
                <div class="progress rounded-pill bg-dark border border-secondary shadow-inner" style="height: 10px;">
                    <div id="barDnaHigh" class="progress-bar bg-danger" style="width: 0%" title="High Value Symbols"></div>
                    <div id="barDnaLow" class="progress-bar bg-success opacity-75" style="width: 0%" title="Low Value Symbols"></div>
                </div>
                <div class="text-[10px] text-gray-400 mt-2 font-mono fst-italic">
                    <i class="bi bi-robot text-purple-400 me-1"></i> <span id="calcInsight" class="text-gray-300">Calculating matrix trajectory...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- REELS GRID -->
    <div class="row g-4">
        <?php for ($reel = 1; $reel <= 3; $reel++): 
            $data = $formattedRates[$reel];
            $totalWeight = $data['sym_1'] + $data['sym_2'] + $data['sym_3'] + $data['sym_4'] + $data['sym_5'] + $data['sym_6'] + $data['sym_7'];
        ?>
        <div class="col-md-4">
            <div class="glass-card border-secondary h-100 overflow-hidden shadow-[0_10px_30px_rgba(0,0,0,0.5)]">
                <div class="bg-black bg-opacity-80 p-3 border-b border-white border-opacity-10 text-center relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-b from-white/5 to-transparent pointer-events-none"></div>
                    <h4 class="fw-black text-white italic tracking-widest m-0 text-uppercase relative z-10">REEL <?= $reel ?></h4>
                    <div class="text-muted small font-mono mt-1 relative z-10">Net Weight: <span class="text-info fw-bold" id="total_weight_<?= $reel ?>"><?= $totalWeight ?></span></div>
                </div>

                <div class="p-4 space-y-3 bg-black bg-opacity-40">
                    <?php 
                    $symLabels = [
                        1 => ['7 (Grand JP)', 'text-danger', 'High variance trigger'],
                        2 => ['Character', 'text-purple-400', 'Medium-High multiplier'],
                        3 => ['BAR', 'text-orange-400', 'Medium multiplier'],
                        4 => ['Bell', 'text-yellow-400', 'Standard hit'],
                        5 => ['Melon', 'text-success', 'Standard hit'],
                        6 => ['Cherry', 'text-pink-400', 'Frequent small hit'],
                        7 => ['Replay', 'text-cyan-400', 'Free spin trigger']
                    ];
                    
                    for ($s = 1; $s <= 7; $s++): 
                        $val = $data["sym_$s"];
                        $pct = $totalWeight > 0 ? round(($val / $totalWeight) * 100, 2) : 0;
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1 align-items-end">
                            <div>
                                <span class="small fw-bold <?= $symLabels[$s][1] ?> text-uppercase"><?= $symLabels[$s][0] ?></span>
                            </div>
                            <span class="small font-mono text-gray-400 bg-black px-2 py-0.5 rounded border border-white border-opacity-10" id="pct_r<?= $reel ?>_s<?= $s ?>"><?= $pct ?>%</span>
                        </div>
                        <input type="number" name="reel_<?= $reel ?>_sym_<?= $s ?>" id="input_r<?= $reel ?>_s<?= $s ?>" value="<?= $val ?>" min="0" class="form-control bg-dark text-white border-secondary font-mono text-sm shadow-inner weight-input transition-colors focus:border-info focus:ring-1 focus:ring-info" data-reel="<?= $reel ?>" required oninput="triggerRecalc()">
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <!-- SUBMISSION BAR -->
    <div class="glass-card mt-4 p-4 border border-warning border-opacity-30 d-flex justify-content-between align-items-center bg-black bg-opacity-60 flex-wrap gap-3">
        <div class="text-muted small font-mono text-[10px] d-flex align-items-center gap-4">
            <div>
                <i class="bi bi-shield-check text-success me-2"></i> 
                Matrix computations run instantly on the client. Click deploy to sync these exact weights to the game servers.
            </div>
            
            <!-- NEW: Apply to All Toggle -->
            <div class="form-check form-switch bg-warning bg-opacity-10 border border-warning border-opacity-25 px-3 py-2 rounded-3 d-flex align-items-center gap-2">
                <input class="form-check-input mt-0" type="checkbox" name="apply_to_all" id="applyAllSwitch" style="cursor:pointer; width: 2em; height: 1em;">
                <label class="form-check-label text-warning fw-bold text-uppercase tracking-widest" for="applyAllSwitch">Apply to all 5 Islands</label>
            </div>
        </div>

        <button type="submit" class="btn btn-warning fw-black px-5 py-3 shadow-[0_0_20px_rgba(234,179,8,0.5)] text-dark hover:scale-105 active:scale-95 transition-transform tracking-widest">
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
                <textarea id="importJsonInput" class="form-control bg-black text-success font-mono border-secondary rounded-lg mb-3" rows="6" placeholder='{"r1":[10,40,100,200,200,250,200], ...}'></textarea>
                <button type="button" onclick="importMatrixJson()" class="btn btn-success w-100 fw-bold shadow-lg">INJECT DATA</button>
            </div>
        </div>
    </div>
</div>

<script>
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
        document.getElementById(`total_weight_${r}`).innerText = rTotals[r];
        
        // Update individual percentages visually
        for (let s = 1; s <= 7; s++) {
            let pct = rTotals[r] > 0 ? ((weights[r][s] / rTotals[r]) * 100).toFixed(1) : 0;
            document.getElementById(`pct_r${r}_s${s}`).innerText = pct + '%';
        }
    }

    // 2. Calculate Theoretical Hit Frequency (P(Win) across 5 lines)
    // P(Win for Symbol X on 1 line) = (Weight X on R1 / Total R1) * (Weight X on R2 / Total R2) * (Weight X on R3 / Total R3)
    let totalHitProbLine = 0;
    
    // Check all winnable symbols (1 to 7)
    for (let s = 1; s <= 7; s++) {
        let p1 = rTotals[1] > 0 ? weights[1][s] / rTotals[1] : 0;
        let p2 = rTotals[2] > 0 ? weights[2][s] / rTotals[2] : 0;
        let p3 = rTotals[3] > 0 ? weights[3][s] / rTotals[3] : 0;
        totalHitProbLine += (p1 * p2 * p3);
    }
    
    // Approx across 5 paylines (Very rough theoretical estimate for admin gauge)
    let estHitFreq = Math.min(100, (totalHitProbLine * 5) * 100);
    document.getElementById('calcHitFreq').innerText = estHitFreq.toFixed(2) + '%';
    document.getElementById('barHitFreq').style.width = estHitFreq + '%';

    // 3. Volatility DNA Calculation
    let highYieldWeights = 0;
    let lowYieldWeights = 0;
    
    for (let r = 1; r <= 3; r++) {
        highYieldWeights += weights[r][1] + weights[r][2] + weights[r][3]; // 7, Char, BAR
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
    if (highPct >= 20) insight = "High variance detected. Expect rare, but massive payouts. High risk of bleeding if players get lucky.";
    else if (highPct <= 10) insight = "Low variance drip-feed. Constant small wins to keep players engaged and seated longer.";
    else insight = "Balanced mathematical ecosystem. Steady progression with occasional exciting spikes.";
    document.getElementById('calcInsight').innerText = insight;
}

// Intelligent Presets Engine
function applyPreset(type) {
    const presets = {
        balanced: {
            r1: [10, 40, 100, 200, 200, 250, 200],
            r2: [5, 30, 80, 220, 220, 245, 200],
            r3: [2, 20, 60, 250, 250, 218, 200]
        },
        high_vol: {
            r1: [25, 60, 150, 100, 100, 50, 50],
            r2: [10, 40, 100, 150, 150, 80, 80],
            r3: [1, 10, 30, 300, 300, 100, 100]
        },
        low_vol: {
            r1: [1, 10, 30, 300, 300, 500, 400],
            r2: [1, 10, 30, 300, 300, 500, 400],
            r3: [1, 10, 30, 300, 300, 500, 400]
        },
        teaser: {
            // High chance of hitting Sym 1 on first two reels, almost impossible on third (Max Suspense)
            r1: [100, 100, 100, 150, 150, 200, 200],
            r2: [100, 100, 100, 150, 150, 200, 200],
            r3: [1, 5, 10, 300, 300, 200, 200]
        }
    };

    const target = presets[type];
    if (!target) return;

    for (let r = 1; r <= 3; r++) {
        for (let s = 1; s <= 7; s++) {
            document.getElementById(`input_r${r}_s${s}`).value = target[`r${r}`][s-1];
        }
    }
    
    // Highlight inputs to show they changed
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
            
            // Visual Highlight for Success
            document.querySelectorAll('.weight-input').forEach(el => {
                el.classList.add('border-success', 'shadow-[0_0_10px_lime]');
                setTimeout(() => el.classList.remove('border-success', 'shadow-[0_0_10px_lime]'), 500);
            });
            
            // Close Modal
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

// Init calculation on load
document.addEventListener("DOMContentLoaded", triggerRecalc);
</script>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>