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

// Fetch Configs for Simulation Environment
$payoutsQuery = $pdo->prepare("SELECT * FROM island_symbol_payouts WHERE island_id = ?");
$payoutsQuery->execute([$currentIsland]);
$payouts = $payoutsQuery->fetch(PDO::FETCH_ASSOC) ?: ['sym_1_mult'=>100, 'sym_2_mult'=>20, 'sym_3_mult'=>10, 'sym_4_mult'=>10, 'sym_5_mult'=>15, 'sym_6_mult'=>2, 'sym_7_mult'=>0];

$jackpotsQuery = $pdo->prepare("SELECT * FROM global_jackpots WHERE island_id = ?");
$jackpotsQuery->execute([$currentIsland]);
$gjpData = $jackpotsQuery->fetch(PDO::FETCH_ASSOC) ?: ['base_seed' => 3000000, 'trigger_amount' => 3600000, 'max_amount' => 7200000, 'contribution_rate' => 0.015];

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
    
    .analytic-badge { font-size: 0.6rem; padding: 2px 6px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.2); font-family: monospace; }
</style>

<div class="d-flex justify-content-between align-items-end mb-4 relative z-10 flex-wrap gap-3">
    <div>
        <h2 class="fw-black text-warning italic tracking-widest mb-0 drop-shadow-[0_0_10px_rgba(234,179,8,0.5)]">
            <i class="bi bi-vinyl"></i> VIRTUAL TAPE STUDIO
        </h2>
        <p class="text-muted small mt-1 font-mono">Design the physical 30-stop reel sequences. Changes immediately impact ecosystem volatility.</p>
    </div>
    
    <div class="d-flex gap-2">
        <button class="btn btn-outline-info fw-bold px-3 rounded-pill shadow-sm hover:scale-105 transition-transform" data-bs-toggle="modal" data-bs-target="#guideModal">
            <i class="bi bi-journal-code"></i> TAPE ARCHITECT GUIDE
        </button>
        <button class="btn btn-outline-danger fw-bold px-3 rounded-pill shadow-sm hover:scale-105 transition-transform" onclick="resetToOriginal()">
            <i class="bi bi-arrow-counterclockwise"></i> REVERT TO DB
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
            <div class="col-md-8 border-end border-white border-opacity-10">
                <h6 class="text-info fw-black tracking-widest uppercase mb-3"><i class="bi bi-calculator"></i> PURE MATH SANDBOX (V6.8)</h6>
                <div class="text-[10px] text-gray-400 font-mono mb-2 pe-4">
                    The simulator reads your DOM inputs below and constructs a physical 3x3 grid via cryptographic wrap-around logic. It organically evaluates all 5 paylines without forcing AI hits, giving you the true, raw baseline RTP of your tape design.
                </div>
            </div>

            <div class="col-md-4 ps-md-4">
                <button type="button" class="btn btn-outline-info w-100 fw-black tracking-widest py-4 rounded-pill shadow-[0_0_15px_rgba(13,202,240,0.3)] hover:scale-105 active:scale-95 transition-transform" onclick="startMatrixSim()">
                    <i class="bi bi-play-circle-fill me-1 fs-5"></i> TEST TAPE YIELD (10K)
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
                    
                    <!-- LIVE ANALYTICS BADGES -->
                    <div class="d-flex flex-wrap justify-content-center gap-1 mt-2 relative z-10" id="analytics-reel-<?= $reel ?>">
                        <!-- Populated by JS -->
                    </div>
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
                            class="form-select form-select-sm sym-select flex-1 rounded-0 border-0 <?= $def['bg'] ?> weight-input locked-input reel-dropdown"
                            data-reel="<?= $reel ?>"
                            onchange="updateSelectColor(this); updateStripAnalytics();"
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

<!-- ARCHITECT GUIDE MODAL -->
<div class="modal fade" id="guideModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content glass-card bg-dark border-info shadow-[0_0_50px_rgba(13,202,240,0.3)]">
            <div class="modal-header border-bottom border-secondary bg-black bg-opacity-50">
                <h5 class="modal-title fw-black text-info italic tracking-widest"><i class="bi bi-journal-code me-2"></i> TAPE ARCHITECT MANUAL</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-sm font-mono text-gray-300 space-y-4" style="max-height: 70vh; overflow-y: auto;">
                
                <div class="bg-black bg-opacity-50 p-3 rounded border border-secondary border-opacity-50">
                    <h6 class="text-white fw-bold"><i class="bi bi-1-square-fill text-info me-2"></i> 1. The Physical Grid (Wrap-Around)</h6>
                    <p class="mb-0 text-xs">The engine selects a random index (0-29) as the <strong>Middle Row</strong>. It then pulls index -1 for the Top Row, and index +1 for the Bottom Row, wrapping around the ends (0 wraps to 29). Do not clump all your premium symbols together, or they will appear on the same spin and waste theoretical RTP.</p>
                </div>

                <div class="bg-black bg-opacity-50 p-3 rounded border border-secondary border-opacity-50">
                    <h6 class="text-white fw-bold"><i class="bi bi-2-square-fill text-warning me-2"></i> 2. Engineering Teasers (Near-Misses)</h6>
                    <p class="mb-0 text-xs">To create psychological excitement, place premium symbols (like 7s or LOGOs) adjacently on Reels 1 and 2, but offset them heavily on Reel 3. This causes the engine to frequently drop "7-7-Cherry" combos, heightening player retention without bleeding funds.</p>
                </div>

                <div class="bg-black bg-opacity-50 p-3 rounded border border-secondary border-opacity-50">
                    <h6 class="text-white fw-bold"><i class="bi bi-3-square-fill text-danger me-2"></i> 3. The Decoupled Grand Jackpot [1]</h6>
                    <p class="mb-0 text-xs">The Grand Jackpot (GJP - Symbol 1) is now mathematically decoupled from the physical strips. Putting a [1] on the strips will allow it to naturally drop (and pay the fixed SYM_1 multiplier), but it will <strong>NOT</strong> trigger the progressive jackpot pool unless the independent engine RNG rolls the jackpot target. Use [1] sparingly as a teaser.</p>
                </div>

                <div class="bg-black bg-opacity-50 p-3 rounded border border-secondary border-opacity-50">
                    <h6 class="text-white fw-bold"><i class="bi bi-4-square-fill text-success me-2"></i> 4. Bleed Fillers (Cherries)</h6>
                    <p class="mb-0 text-xs">A healthy tape consists of ~40-50% Cherry (Sym 6) or Replay (Sym 7) stops. These provide the frequent small "bleed" hits required to keep players engaged during cold streaks.</p>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- V6.8 QUICK SIMULATION MODAL -->
<div class="modal fade" id="simModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-centered">
        <div class="modal-content border-info" style="background-color: #050505; box-shadow: 0 0 50px rgba(0,243,255,0.3);">
            <div class="modal-header border-info border-opacity-50 bg-info bg-opacity-10 py-3">
                <h6 class="modal-title font-mono text-info fw-bold"><i class="bi bi-cpu me-2"></i> PURE MATH SANDBOX (V6.8)</h6>
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
                                <span class="text-info" style="font-size: 10px;">NATURAL TAPE RTP</span>
                                <span class="text-info fs-3 fw-black drop-shadow-md" id="resActualRtp">0%</span>
                            </div>
                            <div class="p-2 border border-secondary rounded bg-dark d-flex justify-content-between align-items-center">
                                <span class="text-gray-400" style="font-size: 10px;">HIT FREQUENCY</span>
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

const SYM_NAMES = {
    1: 'GJP', 2: 'LOGO', 3: '7SEV', 4: 'MELN', 5: 'BELL', 6: 'CHER', 7: 'REPL'
};

const SYM_COLORS = {
    1: '#ef4444', 2: '#a855f7', 3: '#f97316', 4: '#22c55e', 5: '#eab308', 6: '#ec4899', 7: '#06b6d4'
};

function updateSelectColor(selectEl) {
    Object.values(SYM_CLASSES).forEach(cls => {
        selectEl.classList.remove(...cls.split(' '));
    });
    const newClasses = SYM_CLASSES[selectEl.value];
    if (newClasses) selectEl.classList.add(...newClasses.split(' '));
}

// --- LIVE ANALYTICS TRACKER ---
function updateStripAnalytics() {
    for (let r = 1; r <= 3; r++) {
        let counts = {1:0, 2:0, 3:0, 4:0, 5:0, 6:0, 7:0};
        const selects = document.querySelectorAll(`select[data-reel="${r}"]`);
        
        selects.forEach(sel => {
            let val = parseInt(sel.value);
            if(counts[val] !== undefined) counts[val]++;
        });

        let html = '';
        for (let symId = 1; symId <= 7; symId++) {
            if (counts[symId] > 0) {
                html += `<span class="analytic-badge" style="color: ${SYM_COLORS[symId]}">${counts[symId]}x ${SYM_NAMES[symId]}</span>`;
            }
        }
        document.getElementById(`analytics-reel-${r}`).innerHTML = html;
    }
}

// Original Data for Revert Failsafe
const ORIGINAL_STRIPS = <?= json_encode($virtualReels) ?>;

const ISL_DATA = <?= json_encode($islands[array_search($currentIsland, array_column($islands, 'id'))] ?? $islands[0]) ?>;
const PAYOUTS = <?= json_encode($payouts) ?>;
const GJP_DATA = <?= json_encode($gjpData) ?>;

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
    updateStripAnalytics();
}

// --- PURE MATH SANDBOX SIMULATION (V6.8) ---
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

    const multipliers = {
        1: parseFloat(PAYOUTS.sym_1_mult||100), 2: parseFloat(PAYOUTS.sym_2_mult||20), 3: parseFloat(PAYOUTS.sym_3_mult||10),
        4: parseFloat(PAYOUTS.sym_4_mult||10), 5: parseFloat(PAYOUTS.sym_5_mult||15), 6: parseFloat(PAYOUTS.sym_6_mult||2), 7: parseFloat(PAYOUTS.sym_7_mult||0)
    };
    
    let logBuffer = [
        `> Initializing Pure Math Sandbox...`,
        `> Reading custom DOM Tapes into Memory...`,
        `> Evaluating true organic grid intersections without AI forcing...`,
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
    const paylines = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 4, 8], [6, 4, 2]];
    
    // GJP State
    let simJackpot = parseFloat(GJP_DATA.base_seed || 3000000);
    const gjpMax = parseFloat(GJP_DATA.max_amount || 7200000);
    const gjpTrigger = parseFloat(GJP_DATA.trigger_amount || 3600000);
    const gjpContrib = parseFloat(GJP_DATA.contribution_rate || 0.015);

    simInterval = setInterval(() => {
        let batchLogs = [];
        for(let b=0; b<BATCH_SIZE && spins < MAX_SPINS; b++) {
            spins++;
            totalIn += bet;
            simJackpot += (bet * gjpContrib);

            // V6.8 Cryptographic Mapping
            const entropy = [Math.random(), Math.random(), Math.random(), Math.random(), Math.random()];
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

            // GJP Independent Roll
            let isGrandJackpot = false;
            let progress = Math.max(0, (simJackpot - gjpTrigger) / Math.max(1, (gjpMax - gjpTrigger)));
            let noise = (entropy[4] * 0.2);
            let baseOdds = Math.max(500, Math.floor(15000000 / Math.max(1, bet)));
            let adjustedOdds = Math.max(2, Math.floor(baseOdds * (1 - progress + noise)));
            
            if (entropy[3] <= (1 / adjustedOdds) || simJackpot >= gjpMax) {
                isGrandJackpot = true;
                totalOut += simJackpot;
                totalWinningSpins++;
                batchLogs.push(`<div class="terminal-line"><span style="color:#f0f">[#${spins.toString().padStart(5,'0')}]</span> ASTRONOMICAL! GJP INDEPENDENT TRIGGER -> <span style="color:#ff0">+${Math.floor(simJackpot).toLocaleString()} MMK</span></div>`);
                simJackpot = parseFloat(GJP_DATA.base_seed || 3000000);
            }

            // Pure Payline Evaluation
            let spinWin = 0;
            let isLineWin = false;

            for (let line of paylines) {
                let s1 = result[line[0]];
                let s2 = result[line[1]];
                let s3 = result[line[2]];

                if (s1 === s2 && s2 === s3) {
                    isLineWin = true;
                    // If GJP independently triggered, don't double count the physical 1s
                    if (s1 === 1 && !isGrandJackpot) {
                        spinWin += bet * multipliers[s1];
                    } else if (s1 !== 1) {
                        spinWin += bet * multipliers[s1];
                    }
                }
            }

            if (isLineWin && spinWin > 0) {
                totalOut += spinWin;
                totalWinningSpins++;
                if (spinWin >= bet * 10) {
                    // Just grab first symbol of line 0 for log display reference
                    let dispSym = result[paylines[0][0]]; 
                    batchLogs.push(`<div class="terminal-line"><span style="color:#0aa">[#${spins.toString().padStart(5,'0')}]</span> NATURAL HIT! [${SYM_NAMES[dispSym]}] -> <span style="color:#0f0">+${spinWin.toLocaleString()} MMK</span></div>`);
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
            logBuffer.push(`<div class="terminal-line mt-3" style="color:#0f0; font-weight:900;">> BASELINE SANDBOX COMPLETE.</div>`);
            term.innerHTML = logBuffer.join('');
            term.scrollTop = term.scrollHeight;
            
            let actualRtp = ((totalOut / totalIn) * 100).toFixed(2);
            let hitFreq = ((totalWinningSpins / MAX_SPINS) * 100).toFixed(2);
            document.getElementById('resActualRtp').innerText = `${actualRtp}%`;
            document.getElementById('resHitFreq').innerText = `${hitFreq}%`;
            
            document.getElementById('simResults').classList.remove('d-none');
            document.getElementById('simResults').classList.add('d-flex');
        }
    }, 40); 
}

function stopSimulation() {
    if (simInterval) clearInterval(simInterval);
}

// Init lock state and analytics on load
document.addEventListener("DOMContentLoaded", () => {
    toggleMatrixLock(); 
    updateStripAnalytics();
});
</script>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>