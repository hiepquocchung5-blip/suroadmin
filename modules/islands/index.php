<?php
// Ensure this is loaded via the router
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');

$pageTitle = "World Engine & Simulations";
requireRole(['GOD']);

// --- 1. HANDLE FORM UPDATES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_island') {
    $id = (int)$_POST['id'];
    try {
        $sql = "UPDATE islands SET 
                name=?, `desc`=?, req_deposit=?, rtp_rate=?, 
                hostess_char_id=?, atmosphere_type=? 
                WHERE id=?";
        $pdo->prepare($sql)->execute([
            cleanInput($_POST['name']), cleanInput($_POST['desc']), 
            (float)$_POST['req_deposit'], (float)$_POST['rtp_rate'], 
            cleanInput($_POST['hostess_char_id']), $_POST['atmosphere_type'], $id
        ]);
        $success = "Island #$id config updated.";
        $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'islands')")->execute([$_SESSION['admin_id'], "Updated Island #$id"]);
    } catch (Exception $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}

// --- 2. FETCH ISLANDS & SPAWN RATES FOR JS SIMULATION ---
// Strictly limit to the 5 production V3 islands
$islands = $pdo->query("SELECT * FROM islands WHERE id <= 5 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$ratesQuery = $pdo->query("SELECT * FROM reel_spawn_rates");
$allRates = $ratesQuery->fetchAll(PDO::FETCH_ASSOC);
$spawnRatesByIsland = [];

// Initialize default fallback arrays in case an island has no rates set yet
foreach($islands as $isl) {
    $spawnRatesByIsland[$isl['id']] = [
        1 => ['sym_1'=>10, 'sym_2'=>40, 'sym_3'=>100, 'sym_4'=>200, 'sym_5'=>200, 'sym_6'=>250, 'sym_7'=>200],
        2 => ['sym_1'=>10, 'sym_2'=>40, 'sym_3'=>100, 'sym_4'=>200, 'sym_5'=>200, 'sym_6'=>250, 'sym_7'=>200],
        3 => ['sym_1'=>10, 'sym_2'=>40, 'sym_3'=>100, 'sym_4'=>200, 'sym_5'=>200, 'sym_6'=>250, 'sym_7'=>200]
    ];
}
// Map actual DB rates
foreach ($allRates as $r) {
    $spawnRatesByIsland[$r['island_id']][$r['reel_index']] = $r;
}

require_once ADMIN_BASE_PATH . '/layout/main.php';
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h2 class="fw-black text-info italic tracking-widest mb-0"><i class="bi bi-globe-americas"></i> WORLD ENGINE</h2>
        <p class="text-muted small mt-1">Manage environmental variables, economy gates, and mathematically simulate RTP.</p>
    </div>
</div>

<?php if(isset($success)): ?><div class="alert bg-success bg-opacity-20 text-success border border-success fw-bold shadow-sm animate-pulse"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert bg-danger bg-opacity-20 text-danger border border-danger fw-bold shadow-sm"><i class="bi bi-x-circle-fill me-2"></i><?= $error ?></div><?php endif; ?>

<div class="row g-4">
    <?php foreach($islands as $isl): 
        $rtpColor = $isl['rtp_rate'] > 85 ? 'text-danger' : ($isl['rtp_rate'] < 60 ? 'text-info' : 'text-success');
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="glass-card h-100 overflow-hidden position-relative group">
            
            <!-- Card Header -->
            <div class="bg-black bg-opacity-50 p-4 border-b border-white border-opacity-10 d-flex justify-content-between align-items-start">
                <div>
                    <span class="badge bg-dark border border-secondary text-info mb-2 font-mono">SYS_ID: 00<?= $isl['id'] ?></span>
                    <h4 class="fw-black text-white italic text-uppercase m-0"><?= htmlspecialchars($isl['name']) ?></h4>
                    <div class="text-muted small mt-1"><i class="bi bi-person-heart"></i> Hostess: <strong class="text-white"><?= strtoupper($isl['hostess_char_id']) ?></strong></div>
                </div>
                <div class="text-end">
                    <div class="fs-2 mb-0 <?= $rtpColor ?> fw-black font-mono lh-1 drop-shadow-md" id="rtp-display-<?= $isl['id'] ?>"><?= number_format($isl['rtp_rate'], 1) ?>%</div>
                    <small class="text-muted fw-bold" style="font-size: 10px; letter-spacing: 1px;">TARGET RTP</small>
                </div>
            </div>

            <!-- Card Body / Stats -->
            <div class="card-body p-4">
                <div class="bg-black bg-opacity-30 border border-white border-opacity-5 rounded p-3 mb-4 text-center">
                    <small class="text-muted d-block text-[9px] text-uppercase fw-bold mb-1">Required Deposit</small>
                    <span class="text-warning fw-bold font-mono fs-5"><?= number_format($isl['req_deposit']) ?> <small class="text-[10px]">MMK</small></span>
                </div>

                <div class="d-grid gap-2">
                    <button class="btn btn-outline-info w-100 fw-bold py-2 shadow-sm text-[11px] tracking-widest" onclick='openEditModal(<?= json_encode($isl) ?>)'>
                        <i class="bi bi-sliders"></i> EDIT CONFIGURATION
                    </button>
                    <a href="?route=content/spawn_rates&island=<?= $isl['id'] ?>" class="btn btn-outline-warning w-100 fw-bold py-2 shadow-sm text-[11px] tracking-widest">
                        <i class="bi bi-gear-wide-connected"></i> ADJUST SPAWN RATES
                    </a>
                    <button class="btn w-100 fw-black py-2 shadow-[0_0_15px_rgba(34,197,94,0.3)] text-[11px] tracking-widest" style="background: linear-gradient(90deg, #10b981, #059669); color: #fff;" onclick="startSimulation(<?= $isl['id'] ?>)">
                        <i class="bi bi-terminal-fill me-1"></i> RUN 10K SPIN SIMULATION
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editIslandModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" class="modal-content glass-card border-info shadow-[0_0_50px_rgba(13,202,240,0.2)]">
            <div class="modal-header border-secondary bg-black bg-opacity-50">
                <h5 class="modal-title fw-black text-info italic tracking-widest"><i class="bi bi-cpu"></i> NODE CONFIGURATION</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="update_island">
                <input type="hidden" name="id" id="editId">
                
                <div class="row g-4">
                    <!-- Column 1: Identity -->
                    <div class="col-md-6 border-end border-secondary border-opacity-50">
                        <h6 class="text-muted fw-bold small text-uppercase mb-3">Identity & Content</h6>
                        
                        <div class="mb-3">
                            <label class="form-label small text-white fw-bold">Display Name</label>
                            <input type="text" name="name" id="editName" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-white fw-bold">Description</label>
                            <textarea name="desc" id="editDesc" class="form-control bg-dark text-white border-secondary" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-white fw-bold">Hostess Character (Key)</label>
                            <input type="text" name="hostess_char_id" id="editHostess" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-white fw-bold">Atmosphere Particle FX</label>
                            <select name="atmosphere_type" id="editAtm" class="form-select bg-dark text-white border-secondary">
                                <option value="none">None</option> <option value="neon_rain">Neon Rain</option>
                                <option value="sunset">Sunset/Fireflies</option> <option value="ash">Volcanic Ash</option>
                                <option value="snow">Snow</option> <option value="clouds">Moving Clouds</option>
                                <option value="spores">Bio Spores</option> <option value="static">Cyber Static</option>
                                <option value="steam">Steam & Sparks</option> <option value="stars">Cosmic Stars</option>
                            </select>
                        </div>
                    </div>

                    <!-- Column 2: Math & Economy -->
                    <div class="col-md-6">
                        <h6 class="text-warning fw-bold small text-uppercase mb-3">Economy & Math Model</h6>
                        
                        <div class="mb-4">
                            <label class="form-label small text-white fw-bold">Required Lifetime Deposit (MMK)</label>
                            <input type="number" name="req_deposit" id="editReqDep" class="form-control bg-black text-warning border-warning font-mono fw-bold fs-5" required>
                            <div class="form-text text-muted">Amount user must deposit to unlock this tier.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label d-flex justify-content-between">
                                <span class="fw-bold text-white small">Target Base RTP (%)</span>
                                <span class="text-info fw-black font-mono fs-5" id="rtpDisplay">70%</span>
                            </label>
                            <input type="range" name="rtp_rate" id="editRtp" class="form-range" min="10" max="95" step="0.5" oninput="document.getElementById('rtpDisplay').innerText = this.value + '%'">
                            <div class="form-text text-info fw-bold" style="font-size: 10px;">
                                * Volatility is now dynamically controlled by Reel Spawn Rates.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary bg-black bg-opacity-50">
                <button type="button" class="btn btn-dark fw-bold px-4" data-bs-dismiss="modal">CANCEL</button>
                <button type="submit" class="btn btn-info fw-black px-5 shadow-[0_0_15px_rgba(13,202,240,0.4)]">DEPLOY CONFIGURATION</button>
            </div>
        </form>
    </div>
</div>

<!-- SIMULATION TERMINAL MODAL -->
<div class="modal fade" id="simModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-success" style="background-color: #050505;">
            <div class="modal-header border-success border-opacity-50 bg-success bg-opacity-10 py-2">
                <h6 class="modal-title font-mono text-success fw-bold"><i class="bi bi-terminal me-2"></i> MATH_SIMULATOR_V3.exe - <span id="simTitle"></span></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="stopSimulation()"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Terminal Output -->
                <div id="simTerminal" class="p-3 font-mono text-xs hide-scrollbar" style="height: 300px; overflow-y: auto; background-color: #000; color: #0f0; white-space: pre-wrap; line-height: 1.2;">
                    > Initialization sequence started...
                </div>
                
                <!-- Results Dashboard (Hidden until done) -->
                <div id="simResults" class="p-4 bg-dark border-top border-success border-opacity-50 d-none">
                    <div class="row text-center g-2 font-mono">
                        <div class="col-4">
                            <div class="p-2 border border-secondary rounded">
                                <div class="text-muted" style="font-size: 10px;">TOTAL SPINS</div>
                                <div class="text-white fs-5">10,000</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 border border-secondary rounded">
                                <div class="text-muted" style="font-size: 10px;">THEORETICAL RTP</div>
                                <div class="text-info fs-5" id="resTheory">0%</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 border border-success rounded bg-success bg-opacity-10">
                                <div class="text-success" style="font-size: 10px;">SIMULATED RTP</div>
                                <div class="text-success fs-5 fw-bold" id="resActual">0%</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3 border border-secondary rounded p-2 text-[10px] text-gray-400">
                        <div class="row text-center" id="symDistro">
                            <!-- Injected by JS -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Inject PHP Data to JS -->
<script>
    const DB_ISLANDS = <?= json_encode($islands) ?>;
    const DB_SPAWN_RATES = <?= json_encode($spawnRatesByIsland) ?>;
    
    let simInterval = null;

    function openEditModal(data) {
        document.getElementById('editId').value = data.id;
        document.getElementById('editName').value = data.name;
        document.getElementById('editDesc').value = data.desc;
        document.getElementById('editHostess').value = data.hostess_char_id;
        document.getElementById('editAtm').value = data.atmosphere_type;
        document.getElementById('editReqDep').value = data.req_deposit;
        document.getElementById('editRtp').value = data.rtp_rate;
        document.getElementById('rtpDisplay').innerText = data.rtp_rate + '%';
        new bootstrap.Modal(document.getElementById('editIslandModal')).show();
    }

    // --- MATHEMATICAL SIMULATION ENGINE (Replicates spin.php) ---
    function startSimulation(islandId) {
        const island = DB_ISLANDS.find(i => parseInt(i.id) === islandId);
        const rates = DB_SPAWN_RATES[islandId];
        
        document.getElementById('simTitle').innerText = island.name;
        const term = document.getElementById('simTerminal');
        document.getElementById('simResults').classList.add('d-none');
        term.innerHTML = `> Establishing mathematical sandbox for ${island.name}...\n> Target RTP: ${island.rtp_rate}%\n> Executing 10,000 continuous spins at 1,000 MMK per spin...\n\n`;
        
        new bootstrap.Modal(document.getElementById('simModal')).show();

        // Math Config
        const bet = 1000;
        const targetRtp = parseFloat(island.rtp_rate);
        
        // General Win Weights (Matches spin.php)
        const winSymWeights = {2: 5, 3: 10, 4: 25, 5: 20, 6: 25, 7: 15};
        const multipliers = {2: 20, 3: 10, 4: 10, 5: 15, 6: 2, 7: 0};
        const symbolIcons = {1:'[7]', 2:'[CHAR]', 3:'[BAR]', 4:'[BELL]', 5:'[MELON]', 6:'[CHERRY]', 7:'[REPLAY]'};
        
        // We also want to see what naturally drops from the DB spawn rates
        const pickSymbol = (weightsObj) => {
            const arr = Object.values(weightsObj);
            const total = arr.reduce((a,b)=>a+parseInt(b), 0);
            let rand = Math.floor(Math.random() * total) + 1;
            let sum = 0;
            for(let i=1; i<=7; i++) {
                sum += parseInt(weightsObj[`sym_${i}`]);
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
        let totalIn = 0;
        let totalOut = 0;
        
        // Stats trackers
        let hits = {1:0, 2:0, 3:0, 4:0, 5:0, 6:0, 7:0}; // Natural reel drops

        simInterval = setInterval(() => {
            // Run a batch of 250 spins per tick to animate
            let batchOutput = '';
            for(let b=0; b<250 && spins < MAX_SPINS; b++) {
                spins++;
                totalIn += bet;

                // 1. Natural Reel Drop (Just for stats distribution, what the user sees)
                let r1 = pickSymbol(rates[1]);
                let r2 = pickSymbol(rates[2]);
                let r3 = pickSymbol(rates[3]);
                hits[r1]++; hits[r2]++; hits[r3]++;

                // 2. Hit Check
                let isHit = (Math.random() * 10000) <= (targetRtp * 100);
                
                if (isHit) {
                    let winSym = pickWinSymbol();
                    let winAmt = bet * multipliers[winSym];
                    totalOut += winAmt;
                    
                    if (spins % 100 === 0 || winAmt >= 10000) { // Log occasional hits and all big hits
                        const reelDisplay = `${symbolIcons[winSym]} ${symbolIcons[winSym]} ${symbolIcons[winSym]}`;
                        batchOutput += `[SPIN ${spins.toString().padStart(5, '0')}] ${reelDisplay} -> Payout: +${winAmt.toLocaleString()} MMK\n`;
                    }
                }
            }

            if (batchOutput !== '') {
                term.innerHTML += batchOutput;
                term.scrollTop = term.scrollHeight;
            }

            if (spins >= MAX_SPINS) {
                clearInterval(simInterval);
                term.innerHTML += `\n> SIMULATION COMPLETE.\n> Calculating physical return matrix...`;
                term.scrollTop = term.scrollHeight;
                
                let actualRtp = ((totalOut / totalIn) * 100).toFixed(2);
                
                document.getElementById('resTheory').innerText = `${targetRtp.toFixed(2)}%`;
                document.getElementById('resActual').innerText = `${actualRtp}%`;
                
                // Color code the result based on variance
                const diff = actualRtp - targetRtp;
                const actEl = document.getElementById('resActual');
                if (diff > 3) actEl.className = "text-danger fs-5 fw-bold animate-pulse";
                else if (diff < -3) actEl.className = "text-warning fs-5 fw-bold";
                else actEl.className = "text-success fs-5 fw-bold";

                // Build Symbol Distribution
                const totalSyms = MAX_SPINS * 3;
                const names = {1:'GJP', 2:'Char', 3:'BAR', 4:'Bell', 5:'Melon', 6:'Cherry', 7:'Replay'};
                let distHtml = '';
                for(let i=1; i<=7; i++) {
                    let pct = ((hits[i] / totalSyms) * 100).toFixed(1);
                    distHtml += `<div class="col"><div class="fw-bold text-white">${names[i]}</div><div>${pct}%</div></div>`;
                }
                document.getElementById('symDistro').innerHTML = distHtml;

                document.getElementById('simResults').classList.remove('d-none');
            }
        }, 50); // 50ms per batch tick
    }

    function stopSimulation() {
        if (simInterval) clearInterval(simInterval);
    }
</script>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>