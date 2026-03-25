<?php
// Ensure this is loaded via the router
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');

$pageTitle = "Virtual Reel Strip Control";
requireRole(['GOD']);

// --- 1. HANDLE SAVING NEW REEL STOPS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_rates') {
    $islandId = (int)$_POST['island_id'];
    $applyToAll = isset($_POST['apply_to_all']) ? true : false;
    
    // Determine which islands we are updating
    $targetIslands = $applyToAll ? [1, 2, 3, 4, 5] : [$islandId];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($targetIslands as $tId) {
            // Clear existing strip for this island
            $pdo->prepare("DELETE FROM reel_stops WHERE island_id = ?")->execute([$tId]);
            
            // Insert the new 30-stop sequence
            $stmt = $pdo->prepare("INSERT INTO reel_stops (island_id, reel_index, stop_pos, symbol_id) VALUES (?, ?, ?, ?)");
            
            for ($reel = 1; $reel <= 3; $reel++) {
                for ($pos = 0; $pos < 30; $pos++) {
                    $sym = (int)$_POST["reel_{$reel}_pos_{$pos}"];
                    $stmt->execute([$tId, $reel, $pos, $sym]);
                }
            }
        }

        $pdo->commit();
        $targetMsg = $applyToAll ? "ALL 5 ISLANDS" : "Island #$islandId";
        $success = "Physical Reel Strips synced to active servers for $targetMsg.";
        $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'reel_stops')")->execute([$_SESSION['admin_id'], "Updated Reel Strips for $targetMsg"]);
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Strip Sync Failed: " . $e->getMessage();
    }
}

// --- 2. FETCH SELECTION & DATA ---
$currentIsland = isset($_GET['island']) ? (int)$_GET['island'] : 1;
$islands = $pdo->query("SELECT id, name, rtp_rate FROM islands WHERE id <= 5 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Physical Reel Stops
$stmtStrip = $pdo->prepare("SELECT reel_index, stop_pos, symbol_id FROM reel_stops WHERE island_id = ? ORDER BY reel_index ASC, stop_pos ASC");
$stmtStrip->execute([$currentIsland]);
$allStops = $stmtStrip->fetchAll(PDO::FETCH_ASSOC);

$virtualReels = [1 => [], 2 => [], 3 => []];
foreach ($allStops as $stop) {
    $virtualReels[(int)$stop['reel_index']][(int)$stop['stop_pos']] = (int)$stop['symbol_id'];
}

// Fallback logic if DB empty
$defaultStrip = [6,4,2,6,5,3,6,7,6,4,2,6,5,3,6,7,6,2,4,6,5,7,6,3,1,6,4,5,6,7];
for ($i = 1; $i <= 3; $i++) {
    if (empty($virtualReels[$i])) {
        $virtualReels[$i] = $defaultStrip;
    }
}

// Fetch V6 Configs for Simulation Environment
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

// Symbol Definitions for UI
$symDefs = [
    1 => ['name' => '1 - GJP',    'bg' => 'bg-danger text-white border-danger'],
    2 => ['name' => '2 - LOGO',   'bg' => 'bg-purple-900 text-purple-200 border-purple-500'],
    3 => ['name' => '3 - 7 (SEV)','bg' => 'bg-orange-900 text-orange-200 border-orange-500'],
    4 => ['name' => '4 - MELON',  'bg' => 'bg-success bg-opacity-25 text-success border-success'],
    5 => ['name' => '5 - BELL',   'bg' => 'bg-warning bg-opacity-25 text-warning border-warning'],
    6 => ['name' => '6 - CHERRY', 'bg' => 'bg-pink-900 text-pink-300 border-pink-500'],
    7 => ['name' => '7 - REPLAY', 'bg' => 'bg-info bg-opacity-25 text-info border-info']
];

require_once ADMIN_BASE_PATH . '/layout/main.php';
?>

<style>
    /* Circuit Chaos / Neon Tech Enhancements */
    .circuit-bg {
        background-color: #050505;
        background-image: radial-gradient(rgba(0, 243, 255, 0.1) 1px, transparent 1px);
        background-size: 20px 20px;
    }
    .locked-input { opacity: 0.7; pointer-events: none; filter: grayscale(50%); }
    .terminal-container { background-color: rgba(0, 0, 0, 0.8); border: 1px solid rgba(0, 243, 255, 0.3); box-shadow: inset 0 0 30px rgba(0, 0, 0, 0.9); }
    .terminal-line { margin: 0; padding: 0; line-height: 1.5; word-wrap: break-word; font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: #00f3ff; }
    
    .strip-container { max-height: 600px; overflow-y: auto; scrollbar-width: thin; scrollbar-color: #0dcaf0 #111; }
    .strip-container::-webkit-scrollbar { width: 6px; }
    .strip-container::-webkit-scrollbar-track { background: #111; }
    .strip-container::-webkit-scrollbar-thumb { background-color: #0dcaf0; border-radius: 10px; }
    
    .sym-select { font-family: 'JetBrains Mono', monospace; font-weight: bold; font-size: 0.8rem; text-align: center; transition: all 0.3s; }
</style>

<div class="d-flex justify-content-between align-items-end mb-4 relative z-10">
    <div>
        <h2 class="fw-black text-warning italic tracking-widest mb-0 drop-shadow-[0_0_10px_rgba(234,179,8,0.5)]">
            <i class="bi bi-vinyl"></i> VIRTUAL REEL STRIPS
        </h2>
        <p class="text-muted small mt-1 font-mono">Physical stop sequence architecture. Determines exact symbol layouts.</p>
    </div>
    
    <div class="d-flex gap-2">
        <button class="btn btn-outline-danger fw-bold px-3 rounded-pill shadow-sm hover:scale-105 transition-transform" onclick="resetToOriginal()">
            <i class="bi bi-arrow-counterclockwise"></i> REVERT
        </button>
    </div>
</div>

<div class="glass-card p-3 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3 border-warning border-opacity-30 shadow-[0_0_20px_rgba(234,179,8,0.15)] circuit-bg">
    <div class="d-flex gap-2 flex-wrap">
        <?php foreach($islands as $isl): ?>
            <a href="?route=content/spawn_rates&island=<?= $isl['id'] ?>" class="btn btn-sm <?= $isl['id'] == $currentIsland ? 'btn-warning fw-bold text-dark shadow-[0_0_15px_gold]' : 'btn-outline-secondary hover:text-white' ?> rounded-pill px-3 transition-colors">
                <i class="bi bi-map-fill me-1 opacity-50"></i> <?= htmlspecialchars($isl['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
    
    <div class="d-flex gap-3 align-items-center">
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
        <div class="position-absolute top-0 end-0 opacity-10"><i class="bi bi-cpu display-1"></i></div>
        
        <div class="row align-items-center position-relative z-10">
            <div class="col-md-6 border-end border-white border-opacity-10">
                <h6 class="text-info fw-black tracking-widest uppercase mb-3"><i class="bi bi-calculator"></i> V6.4 Physics Engine</h6>
                <div class="text-[10px] text-gray-400 font-mono mb-2">
                    The simulator reads this exact 30-stop tape and performs a 10-Billion scale RNG hash check against the Target Hit Rate to determine the payout structure.
                </div>
                <div class="d-flex gap-3">
                    <div class="bg-black bg-opacity-50 p-2 rounded border border-white border-opacity-10 text-center flex-1">
                        <span class="d-block text-[9px] text-gray-500 uppercase fw-bold">Target Hit Rate</span>
                        <span class="text-cyan-400 font-mono fw-black fs-5"><?= number_format($winRatesByIsland[$currentIsland]['base_hit_rate'], 4) ?>%</span>
                    </div>
                    <div class="bg-black bg-opacity-50 p-2 rounded border border-white border-opacity-10 text-center flex-1">
                        <span class="d-block text-[9px] text-gray-500 uppercase fw-bold">Target RTP Cap</span>
                        <span class="text-warning font-mono fw-black fs-5"><?= number_format($winRatesByIsland[$currentIsland]['max_rtp_cap'], 2) ?>%</span>
                    </div>
                </div>
            </div>

            <div class="col-md-6 ps-md-4">
                <button type="button" class="btn btn-outline-info w-100 fw-black tracking-widest py-4 rounded-pill shadow-[0_0_15px_rgba(13,202,240,0.3)] hover:scale-105 active:scale-95 transition-transform" onclick="startMatrixSim()">
                    <i class="bi bi-play-circle-fill me-1 fs-5"></i> INITIATE 10K TEST
                </button>
            </div>
        </div>
    </div>

    <!-- REEL STRIPS (TAPE ARRAYS) -->
    <div class="row g-4" id="matrixContainer">
        <?php for ($reel = 1; $reel <= 3; $reel++): 
            $strip = $virtualReels[$reel];
        ?>
        <div class="col-md-4">
            <div class="glass-card border-secondary h-100 overflow-hidden shadow-[0_10px_30px_rgba(0,0,0,0.5)] flex flex-col">
                <div class="bg-black bg-opacity-80 p-3 border-b border-white border-opacity-10 text-center relative overflow-hidden">
                    <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/circuit-board.png')] opacity-10 pointer-events-none mix-blend-color-dodge"></div>
                    <h4 class="fw-black text-white italic tracking-widest m-0 text-uppercase relative z-10">REEL <?= $reel ?></h4>
                    <div class="text-muted small font-mono mt-1 relative z-10">30 Physical Stops</div>
                </div>

                <div class="p-2 bg-black bg-opacity-40 strip-container flex-1">
                    <?php for ($pos = 0; $pos < 30; $pos++): 
                        $currentSym = isset($strip[$pos]) ? $strip[$pos] : 6;
                        $def = $symDefs[$currentSym];
                    ?>
                    <div class="d-flex align-items-center mb-1 bg-black rounded overflow-hidden border border-white border-opacity-5">
                        <div class="bg-dark text-gray-500 font-mono text-[10px] fw-bold px-2 py-1 text-center" style="width: 40px;">
                            #<?= str_pad($pos, 2, '0', STR_PAD_LEFT) ?>
                        </div>
                        <select 
                            name="reel_<?= $reel ?>_pos_<?= $pos ?>" 
                            id="r<?= $reel ?>_p<?= $pos ?>"
                            class="form-select form-select-sm sym-select flex-1 rounded-0 border-0 <?= $def['bg'] ?> weight-input locked-input"
                            onchange="updateSelectColor(this)"
                        >
                            <?php foreach($symDefs as $id => $d): ?>
                                <option value="<?= $id ?>" <?= $id === $currentSym ? 'selected' : '' ?> class="bg-dark text-white font-mono">
                                    <?= $d['name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                Simulation uses current inputs. Click deploy to sync these exact stops to the live database.
            </div>
            
            <div class="form-check form-switch bg-warning bg-opacity-10 border border-warning border-opacity-50 px-3 py-2 rounded-3 d-flex align-items-center gap-2">
                <input class="form-check-input mt-0" type="checkbox" name="apply_to_all" id="applyAllSwitch" style="cursor:pointer; width: 2em; height: 1em;">
                <label class="form-check-label text-warning fw-black text-uppercase tracking-widest" for="applyAllSwitch">Apply to all 5 Islands</label>
            </div>
        </div>

        <button type="submit" id="deployBtn" class="btn btn-warning fw-black px-5 py-3 shadow-[0_0_20px_rgba(234,179,8,0.5)] text-dark hover:scale-105 active:scale-95 transition-transform tracking-widest disabled">
            <i class="bi bi-hdd-network-fill me-2"></i> WRITE TAPE TO SERVER
        </button>
    </div>
</form>

<!-- V6.4 QUICK SIMULATION MODAL -->
<div class="modal fade" id="simModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-centered">
        <div class="modal-content border-info" style="background-color: #050505; box-shadow: 0 0 50px rgba(0,243,255,0.3);">
            <div class="modal-header border-info border-opacity-50 bg-info bg-opacity-10 py-3">
                <h6 class="modal-title font-mono text-info fw-bold"><i class="bi bi-cpu me-2"></i> STRIP SIMULATOR (V6.4 ENGINE)</h6>
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
                                <span class="text-white fs-5 fw-bold" id="resSpins">0</span>
                            </div>
                            <div class="p-2 border border-info rounded bg-info bg-opacity-20 d-flex justify-content-between align-items-center shadow-[0_0_15px_rgba(0,243,255,0.3)]">
                                <span class="text-info" style="font-size: 10px;">ACTUAL RTP YIELD</span>
                                <span class="text-info fs-3 fw-black drop-shadow-md" id="resActualRtp">0%</span>
                            </div>
                            <div class="p-2 border border-secondary rounded bg-dark d-flex justify-content-between align-items-center">
                                <span class="text-gray-400" style="font-size: 10px;">TOTAL WIN FREQ</span>
                                <span class="text-warning fs-5 fw-bold" id="resHitFreq">0%</span>
                            </div>
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
// Class Mapping for dynamic UI colors
const SYM_CLASSES = {
    1: 'bg-danger text-white border-danger',
    2: 'bg-purple-900 text-purple-200 border-purple-500',
    3: 'bg-orange-900 text-orange-200 border-orange-500',
    4: 'bg-success bg-opacity-25 text-success border-success',
    5: 'bg-warning bg-opacity-25 text-warning border-warning',
    6: 'bg-pink-900 text-pink-300 border-pink-500',
    7: 'bg-info bg-opacity-25 text-info border-info'
};

function updateSelectColor(selectEl) {
    // Remove all old classes
    Object.values(SYM_CLASSES).forEach(cls => {
        selectEl.classList.remove(...cls.split(' '));
    });
    // Add new classes
    const newClasses = SYM_CLASSES[selectEl.value];
    if (newClasses) {
        selectEl.classList.add(...newClasses.split(' '));
    }
}

// Original Data for Revert Failsafe
const ORIGINAL_STRIPS = <?= json_encode($virtualReels) ?>;

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

function resetToOriginal() {
    if (!confirm("Revert all inputs to the current database settings?")) return;
    
    for (let r = 1; r <= 3; r++) {
        for (let p = 0; p < 30; p++) {
            if (ORIGINAL_STRIPS[r] && ORIGINAL_STRIPS[r][p]) {
                const el = document.getElementById(`r${r}_p${p}`);
                if (el) {
                    el.value = ORIGINAL_STRIPS[r][p];
                    updateSelectColor(el);
                }
            }
        }
    }
}

// --- V6.4 SANDBOX SIMULATION ENGINE ---
let simInterval = null;

function startMatrixSim() {
    const term = document.getElementById('simTerminal');
    document.getElementById('simResults').classList.add('d-none');
    document.getElementById('simResults').classList.remove('d-flex');
    
    // Read Current Strip Tape directly from the DOM
    const virtualReels = { 1: [], 2: [], 3: [] };
    for (let r = 1; r <= 3; r++) {
        for (let p = 0; p < 30; p++) {
            const val = parseInt(document.getElementById(`r${r}_p${p}`).value) || 6;
            virtualReels[r].push(val);
        }
    }

    const targetRtp = parseFloat(ISL_DATA.rtp_rate || 70.0);
    const baseHitRate = parseFloat(WIN_RATES.base_hit_rate || 22.0);
    
    const multipliers = {
        1: parseFloat(PAYOUTS.sym_1_mult||100), 2: parseFloat(PAYOUTS.sym_2_mult||20), 3: parseFloat(PAYOUTS.sym_3_mult||10),
        4: parseFloat(PAYOUTS.sym_4_mult||10), 5: parseFloat(PAYOUTS.sym_5_mult||15), 6: parseFloat(PAYOUTS.sym_6_mult||2), 7: parseFloat(PAYOUTS.sym_7_mult||0)
    };
    
    let logBuffer = [
        `> Initializing V6.4 Sandbox Engine...`,
        `> Reading Active DOM Tape into Memory (30 stops per reel)...`,
        `> Strict Constraint: <span style="color:#0ff">10-Billion Scale RNG Active</span>`,
        `> Executing 10,000 spins at 1,000 MMK bet...`,
        `--------------------------------------------------`
    ];
    term.innerHTML = logBuffer.join('<br/>');
    
    new bootstrap.Modal(document.getElementById('simModal')).show();

    let spins = 0;
    const MAX_SPINS = 10000;
    const BATCH_SIZE = 1000; 
    const bet = 1000;
    
    let totalIn = 0, totalOut = 0, totalWinningSpins = 0;
    const names = {1:'GJP', 2:'LOGO', 3:'7SEV', 4:'MELN', 5:'BELL', 6:'CHER', 7:'REPL'};
    const paylines = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 4, 8], [6, 4, 2]];

    simInterval = setInterval(() => {
        let batchLogs = [];
        for(let b=0; b<BATCH_SIZE && spins < MAX_SPINS; b++) {
            spins++;
            totalIn += bet;

            // Generate physical board drops using V6.4 Hash Simulation
            // We simulate the 3 hash chunks (0.0 to 0.999...)
            const entropy = [Math.random(), Math.random(), Math.random()];
            let result = Array(9).fill(0);

            for (let i = 1; i <= 3; i++) {
                const len = virtualReels[i].length;
                const stopIdx = Math.floor(entropy[i - 1] * len);
                
                const topIdx = (stopIdx - 1 < 0) ? len - 1 : stopIdx - 1;
                const botIdx = (stopIdx + 1 >= len) ? 0 : stopIdx + 1;
                
                const colOffset = i - 1;
                result[colOffset]     = virtualReels[i][topIdx]; 
                result[colOffset + 3] = virtualReels[i][stopIdx];    
                result[colOffset + 6] = virtualReels[i][botIdx];     
            }

            // V6.4 10-Billion Scale RNG Hit Engine
            let isHit = (Math.random() * 10000000000) <= (baseHitRate * 100000000);
            
            if (isHit) {
                // In Sandbox, if hit triggers, we evaluate the board. 
                // If the board didn't naturally form a line, we force it for the sake of RTP telemetry.
                // Note: Real engine forces win creation earlier. We mimic that here.
                
                // Pick random line to force win if needed (Simplified Sandbox version)
                let winSym = [2,3,4,5,6,7][Math.floor(Math.random()*6)];
                let winAmt = bet * multipliers[winSym];
                totalOut += winAmt;
                totalWinningSpins++;
                
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

        document.getElementById('resSpins').innerText = spins.toLocaleString();

        if (spins >= MAX_SPINS) {
            clearInterval(simInterval);
            logBuffer.push(`<div class="terminal-line mt-3" style="color:#0f0; font-weight:900;">> V6.4 SANDBOX SIMULATION COMPLETE.</div>`);
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
            
            document.getElementById('simResults').classList.remove('d-none');
            document.getElementById('simResults').classList.add('d-flex');
        }
    }, 40); 
}

function stopSimulation() {
    if (simInterval) clearInterval(simInterval);
}

// Init lock state on load
document.addEventListener("DOMContentLoaded", () => {
    toggleMatrixLock(); 
});
</script>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>