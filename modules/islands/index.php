<?php
// Ensure this is loaded via the router
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');

$pageTitle = "World Engine & Simulations";
requireRole(['GOD']);

// --- 1. HANDLE FORM UPDATES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_island') {
    $id = (int)$_POST['id'];
    try {
        $pdo->beginTransaction();

        // Update Island Core
        $sql = "UPDATE islands SET 
                name=?, `desc`=?, req_deposit=?, rtp_rate=?, 
                hostess_char_id=?, atmosphere_type=? 
                WHERE id=?";
        $pdo->prepare($sql)->execute([
            cleanInput($_POST['name']), cleanInput($_POST['desc']), 
            (float)$_POST['req_deposit'], (float)$_POST['rtp_rate'], 
            cleanInput($_POST['hostess_char_id']), $_POST['atmosphere_type'], $id
        ]);

        // Update Symbol Payout Multipliers
        $sqlPayouts = "INSERT INTO island_symbol_payouts 
                       (island_id, sym_1_mult, sym_2_mult, sym_3_mult, sym_4_mult, sym_5_mult, sym_6_mult, sym_7_mult) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                       ON DUPLICATE KEY UPDATE 
                       sym_1_mult=VALUES(sym_1_mult), sym_2_mult=VALUES(sym_2_mult), sym_3_mult=VALUES(sym_3_mult), 
                       sym_4_mult=VALUES(sym_4_mult), sym_5_mult=VALUES(sym_5_mult), sym_6_mult=VALUES(sym_6_mult), sym_7_mult=VALUES(sym_7_mult)";
        $pdo->prepare($sqlPayouts)->execute([
            $id, 
            (float)$_POST['sym_1_mult'], (float)$_POST['sym_2_mult'], (float)$_POST['sym_3_mult'], 
            (float)$_POST['sym_4_mult'], (float)$_POST['sym_5_mult'], (float)$_POST['sym_6_mult'], (float)$_POST['sym_7_mult']
        ]);

        $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'islands')")->execute([$_SESSION['admin_id'], "Updated Island & Payouts #$id"]);
        
        $pdo->commit();
        $success = "Island #$id configuration and multipliers synced successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Update failed: " . $e->getMessage();
    }
}

// --- 2. FETCH ISLANDS, SPAWN RATES & PAYOUTS ---
$islands = $pdo->query("SELECT * FROM islands WHERE id <= 5 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Spawn Rates
$ratesQuery = $pdo->query("SELECT * FROM reel_spawn_rates");
$allRates = $ratesQuery->fetchAll(PDO::FETCH_ASSOC);
$spawnRatesByIsland = [];

// Fetch Payout Multipliers
$payoutsQuery = $pdo->query("SELECT * FROM island_symbol_payouts");
$allPayouts = $payoutsQuery->fetchAll(PDO::FETCH_ASSOC);
$payoutsByIsland = [];

foreach($islands as $isl) {
    // Defaults for spawn rates
    $spawnRatesByIsland[$isl['id']] = [
        1 => ['sym_1'=>10, 'sym_2'=>40, 'sym_3'=>100, 'sym_4'=>200, 'sym_5'=>200, 'sym_6'=>250, 'sym_7'=>200],
        2 => ['sym_1'=>10, 'sym_2'=>40, 'sym_3'=>100, 'sym_4'=>200, 'sym_5'=>200, 'sym_6'=>250, 'sym_7'=>200],
        3 => ['sym_1'=>10, 'sym_2'=>40, 'sym_3'=>100, 'sym_4'=>200, 'sym_5'=>200, 'sym_6'=>250, 'sym_7'=>200]
    ];
    // Defaults for multipliers
    $payoutsByIsland[$isl['id']] = [
        'sym_1_mult'=>100, 'sym_2_mult'=>20, 'sym_3_mult'=>10, 'sym_4_mult'=>10, 'sym_5_mult'=>15, 'sym_6_mult'=>2, 'sym_7_mult'=>0
    ];
}

foreach ($allRates as $r) { $spawnRatesByIsland[$r['island_id']][$r['reel_index']] = $r; }
foreach ($allPayouts as $p) { $payoutsByIsland[$p['island_id']] = $p; }

// --- 3. VOLATILITY DNA & AI INSIGHTS ---
$islandInsights = [];
foreach($islands as $isl) {
    $rates = $spawnRatesByIsland[$isl['id']];
    $totalHigh = 0; $totalLow = 0;
    
    for ($reel = 1; $reel <= 3; $reel++) {
        if (isset($rates[$reel])) {
            $totalHigh += $rates[$reel]['sym_1'] + $rates[$reel]['sym_2'] + $rates[$reel]['sym_3'];
            $totalLow += $rates[$reel]['sym_4'] + $rates[$reel]['sym_5'] + $rates[$reel]['sym_6'] + $rates[$reel]['sym_7'];
        }
    }
    
    $total = $totalHigh + $totalLow;
    $highPct = $total > 0 ? round(($totalHigh / $total) * 100) : 0;
    $lowPct = 100 - $highPct;
    
    if ($highPct >= 20) {
        $insight = "High variance detected. Expect rare, but massive payouts."; $color = "danger";
    } elseif ($highPct <= 10) {
        $insight = "Low variance drip-feed. Constant small wins for high retention."; $color = "success";
    } else {
        $insight = "Balanced mathematical ecosystem. Steady progression."; $color = "info";
    }
    
    $islandInsights[$isl['id']] = ['high_pct' => $highPct, 'low_pct' => $lowPct, 'text' => $insight, 'color' => $color];
}

require_once ADMIN_BASE_PATH . '/layout/main.php';
?>

<!-- HTML2PDF CDN FOR REPORT GENERATION -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h2 class="fw-black text-info italic tracking-widest mb-0"><i class="bi bi-globe-americas"></i> WORLD ENGINE</h2>
        <p class="text-muted small mt-1">Manage environmental variables, economy gates, multipliers, and mathematically simulate RTP.</p>
    </div>
</div>

<?php if(isset($success)): ?><div class="alert bg-success bg-opacity-20 text-success border border-success fw-bold shadow-sm animate-pulse"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert bg-danger bg-opacity-20 text-danger border border-danger fw-bold shadow-sm"><i class="bi bi-x-circle-fill me-2"></i><?= $error ?></div><?php endif; ?>

<div class="row g-4">
    <?php foreach($islands as $isl): 
        $rtpColor = $isl['rtp_rate'] > 85 ? 'text-danger' : ($isl['rtp_rate'] < 60 ? 'text-info' : 'text-success');
        $insightData = $islandInsights[$isl['id']];
        $pouts = $payoutsByIsland[$isl['id']];
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="glass-card h-100 overflow-hidden position-relative group d-flex flex-column border-info border-opacity-30 hover:border-opacity-100 transition-colors shadow-[0_0_20px_rgba(0,0,0,0.4)]">
            
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

            <div class="card-body p-4 flex-grow-1">
                <div class="bg-black bg-opacity-30 border border-white border-opacity-5 rounded p-3 mb-4 text-center shadow-inner">
                    <small class="text-muted d-block text-[9px] text-uppercase fw-bold mb-1">Required Deposit</small>
                    <span class="text-warning fw-bold font-mono fs-5"><?= number_format($isl['req_deposit']) ?> <small class="text-[10px]">MMK</small></span>
                </div>

                <div class="mb-4">
                    <div class="d-flex justify-content-between text-[9px] text-gray-400 fw-bold uppercase tracking-widest mb-1">
                        <span>Volatility DNA</span>
                        <span class="text-<?= $insightData['color'] ?> font-mono"><?= $insightData['high_pct'] ?>% HIGH YIELD</span>
                    </div>
                    <div class="progress rounded-pill bg-dark border border-secondary" style="height: 6px;">
                        <div class="progress-bar bg-danger" style="width: <?= $insightData['high_pct'] ?>%"></div>
                        <div class="progress-bar bg-success opacity-75" style="width: <?= $insightData['low_pct'] ?>%"></div>
                    </div>
                </div>

                <div class="d-grid gap-2 mt-auto">
                    <button class="btn btn-outline-info w-100 fw-bold py-2 shadow-sm text-[11px] tracking-widest hover:bg-info hover:text-black transition-colors" onclick='openEditModal(<?= json_encode($isl) ?>, <?= json_encode($pouts) ?>)'>
                        <i class="bi bi-sliders"></i> TWEAK CONFIG & MULTIPLIERS
                    </button>
                    <a href="?route=content/spawn_rates&island=<?= $isl['id'] ?>" class="btn btn-outline-warning w-100 fw-bold py-2 shadow-sm text-[11px] tracking-widest hover:bg-warning hover:text-black transition-colors">
                        <i class="bi bi-gear-wide-connected"></i> ADJUST SPAWN RATES
                    </a>
                    <button class="btn w-100 fw-black py-2 shadow-[0_0_15px_rgba(34,197,94,0.3)] text-[11px] tracking-widest hover:scale-105 active:scale-95 transition-transform" style="background: linear-gradient(90deg, #10b981, #059669); color: #fff;" onclick="startSimulation(<?= $isl['id'] ?>)">
                        <i class="bi bi-cpu-fill me-1"></i> SIMULATE 1,000,000 SPINS
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- EDIT CONFIG & MULTIPLIERS MODAL -->
<div class="modal fade" id="editIslandModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <form method="POST" class="modal-content glass-card border-info shadow-[0_0_50px_rgba(13,202,240,0.2)]">
            <div class="modal-header border-secondary bg-black bg-opacity-50">
                <h5 class="modal-title fw-black text-info italic tracking-widest"><i class="bi bi-sliders"></i> SYSTEM PARAMETERS</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="update_island">
                <input type="hidden" name="id" id="editId">
                
                <div class="row g-4">
                    <!-- Column 1: Core Config -->
                    <div class="col-lg-6 border-end border-secondary border-opacity-50 pe-lg-4">
                        <h6 class="text-muted fw-bold small text-uppercase mb-3"><i class="bi bi-globe me-2"></i> Environment Variables</h6>
                        
                        <div class="mb-3">
                            <label class="form-label small text-white fw-bold">Display Name</label>
                            <input type="text" name="name" id="editName" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-white fw-bold">Description</label>
                            <textarea name="desc" id="editDesc" class="form-control bg-dark text-white border-secondary" rows="2"></textarea>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label small text-white fw-bold">Hostess Character (Key)</label>
                                <input type="text" name="hostess_char_id" id="editHostess" class="form-control bg-dark text-white border-secondary" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-white fw-bold">Atmosphere FX</label>
                                <select name="atmosphere_type" id="editAtm" class="form-select bg-dark text-white border-secondary">
                                    <option value="none">None</option> <option value="neon_rain">Neon Rain</option>
                                    <option value="sunset">Sunset/Fireflies</option> <option value="ash">Volcanic Ash</option>
                                    <option value="snow">Snow</option> <option value="clouds">Moving Clouds</option>
                                    <option value="spores">Bio Spores</option> <option value="static">Cyber Static</option>
                                    <option value="steam">Steam & Sparks</option> <option value="stars">Cosmic Stars</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3 bg-black bg-opacity-40 p-3 rounded border border-white border-opacity-5">
                            <label class="form-label small text-warning fw-bold">Required Lifetime Deposit (MMK)</label>
                            <input type="number" name="req_deposit" id="editReqDep" class="form-control bg-black text-warning border-warning font-mono fw-bold fs-5" required>
                        </div>

                        <div class="mb-3 bg-black bg-opacity-40 p-3 rounded border border-white border-opacity-5">
                            <label class="form-label d-flex justify-content-between">
                                <span class="fw-bold text-info small">Target Base RTP (%)</span>
                                <span class="text-info fw-black font-mono fs-5" id="rtpInputDisplay">70%</span>
                            </label>
                            <input type="range" name="rtp_rate" id="editRtp" class="form-range" min="10" max="95" step="0.5" oninput="document.getElementById('rtpInputDisplay').innerText = this.value + '%'">
                        </div>
                    </div>

                    <!-- Column 2: Payout Multipliers -->
                    <div class="col-lg-6 ps-lg-4">
                        <h6 class="text-success fw-bold small text-uppercase mb-3"><i class="bi bi-cash-stack me-2"></i> Symbol Payout Multipliers (xBet)</h6>
                        
                        <div class="row g-3 font-mono">
                            <div class="col-6">
                                <label class="small text-danger fw-bold text-[10px] tracking-widest">SYM 1 (7 / GJP)</label>
                                <input type="number" step="0.01" name="sym_1_mult" id="editMult1" class="form-control bg-dark text-white border-danger shadow-[inset_0_0_10px_rgba(239,68,68,0.2)]" required>
                            </div>
                            <div class="col-6">
                                <label class="small text-purple-400 fw-bold text-[10px] tracking-widest">SYM 2 (Character)</label>
                                <input type="number" step="0.01" name="sym_2_mult" id="editMult2" class="form-control bg-dark text-white border-purple-500 shadow-[inset_0_0_10px_rgba(168,85,247,0.2)]" required>
                            </div>
                            <div class="col-6">
                                <label class="small text-orange-400 fw-bold text-[10px] tracking-widest">SYM 3 (BAR)</label>
                                <input type="number" step="0.01" name="sym_3_mult" id="editMult3" class="form-control bg-dark text-white border-secondary" required>
                            </div>
                            <div class="col-6">
                                <label class="small text-yellow-400 fw-bold text-[10px] tracking-widest">SYM 4 (Bell)</label>
                                <input type="number" step="0.01" name="sym_4_mult" id="editMult4" class="form-control bg-dark text-white border-secondary" required>
                            </div>
                            <div class="col-6">
                                <label class="small text-green-400 fw-bold text-[10px] tracking-widest">SYM 5 (Melon)</label>
                                <input type="number" step="0.01" name="sym_5_mult" id="editMult5" class="form-control bg-dark text-white border-secondary" required>
                            </div>
                            <div class="col-6">
                                <label class="small text-pink-400 fw-bold text-[10px] tracking-widest">SYM 6 (Cherry)</label>
                                <input type="number" step="0.01" name="sym_6_mult" id="editMult6" class="form-control bg-dark text-white border-secondary" required>
                            </div>
                            <div class="col-12">
                                <label class="small text-cyan-400 fw-bold text-[10px] tracking-widest">SYM 7 (Replay / Free Spin)</label>
                                <input type="number" step="0.01" name="sym_7_mult" id="editMult7" class="form-control bg-dark text-white border-secondary" required>
                                <div class="form-text text-muted text-[10px]">Replay typically pays 0x but triggers a free spin logic in engine.</div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary bg-black bg-opacity-50">
                <button type="button" class="btn btn-dark fw-bold px-4" data-bs-dismiss="modal">CANCEL</button>
                <button type="submit" class="btn btn-info fw-black px-5 shadow-[0_0_15px_rgba(13,202,240,0.4)]">OVERWRITE CORE CONFIG</button>
            </div>
        </form>
    </div>
</div>

<!-- 1 MILLION SPIN SIMULATION TERMINAL -->
<div class="modal fade" id="simModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-success" style="background-color: #050505; box-shadow: 0 0 50px rgba(16,185,129,0.2);">
            <div class="modal-header border-success border-opacity-50 bg-success bg-opacity-10 py-3">
                <h6 class="modal-title font-mono text-success fw-bold"><i class="bi bi-cpu me-2"></i> LEVIATHAN CORE - 1M SPIN SIMULATOR - <span id="simTitle"></span></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="stopSimulation()"></button>
            </div>
            <div class="modal-body p-0 d-flex flex-column flex-lg-row">
                
                <!-- Terminal Output -->
                <div id="simTerminal" class="p-4 font-mono text-xs hide-scrollbar flex-grow-1" style="height: 450px; overflow-y: auto; background-color: #000; color: #0f0; white-space: pre-wrap; line-height: 1.5; border-right: 1px solid rgba(16,185,129,0.3);">
                    > Initialization sequence started...
                </div>
                
                <!-- Exportable Results Dashboard -->
                <div id="simResultsContainer" class="bg-dark" style="width: 350px; min-width: 300px;">
                    <div id="simResults" class="p-4 d-none bg-dark h-100">
                        <div class="text-center mb-3 border-bottom border-secondary pb-2">
                            <h5 class="text-success font-mono fw-black mb-0">RTP AUDIT REPORT</h5>
                            <small class="text-muted font-mono" id="reportDate"></small>
                        </div>
                        
                        <div class="d-grid gap-2 font-mono mb-4">
                            <div class="p-2 border border-secondary rounded bg-black bg-opacity-50 d-flex justify-content-between align-items-center">
                                <span class="text-muted" style="font-size: 10px;">TOTAL SPINS</span>
                                <span class="text-white fs-5 fw-bold">1,000,000</span>
                            </div>
                            <div class="p-2 border border-secondary rounded bg-black bg-opacity-50 d-flex justify-content-between align-items-center">
                                <span class="text-muted" style="font-size: 10px;">THEORETICAL RTP</span>
                                <span class="text-info fs-5 fw-bold" id="resTheory">0%</span>
                            </div>
                            <div class="p-2 border border-success rounded bg-success bg-opacity-20 d-flex justify-content-between align-items-center shadow-[0_0_15px_rgba(16,185,129,0.3)]">
                                <span class="text-success" style="font-size: 10px;">ACTUAL RTP</span>
                                <span class="text-success fs-3 fw-black drop-shadow-md" id="resActual">0%</span>
                            </div>
                            <div class="p-2 border border-secondary rounded bg-black bg-opacity-50 d-flex justify-content-between align-items-center">
                                <span class="text-muted" style="font-size: 10px;">HIT FREQUENCY</span>
                                <span class="text-warning fs-5 fw-bold" id="resHitFreq">0%</span>
                            </div>
                        </div>
                        
                        <h6 class="text-gray-400 font-mono text-[10px] uppercase tracking-widest mb-2 border-bottom border-secondary pb-1">Symbol Drop Matrix</h6>
                        <div class="text-[10px] text-gray-300 font-mono">
                            <div class="row text-center g-2" id="symDistro">
                                <!-- Injected by JS -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer border-success border-opacity-30 bg-black">
                <button type="button" id="btnPdfExport" class="btn btn-outline-success fw-bold font-mono d-none shadow-[0_0_10px_lime]" onclick="exportPDF()">
                    <i class="bi bi-file-earmark-pdf-fill me-2"></i> DOWNLOAD PDF REPORT
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Inject PHP Data to JS -->
<script>
    const DB_ISLANDS = <?= json_encode($islands) ?>;
    const DB_SPAWN_RATES = <?= json_encode($spawnRatesByIsland) ?>;
    const DB_PAYOUTS = <?= json_encode($payoutsByIsland) ?>;
    
    let simInterval = null;

    function openEditModal(island, payouts) {
        document.getElementById('editId').value = island.id;
        document.getElementById('editName').value = island.name;
        document.getElementById('editDesc').value = island.desc;
        document.getElementById('editHostess').value = island.hostess_char_id;
        document.getElementById('editAtm').value = island.atmosphere_type;
        document.getElementById('editReqDep').value = island.req_deposit;
        document.getElementById('editRtp').value = island.rtp_rate;
        document.getElementById('rtpInputDisplay').innerText = island.rtp_rate + '%';

        // Payouts
        document.getElementById('editMult1').value = payouts.sym_1_mult;
        document.getElementById('editMult2').value = payouts.sym_2_mult;
        document.getElementById('editMult3').value = payouts.sym_3_mult;
        document.getElementById('editMult4').value = payouts.sym_4_mult;
        document.getElementById('editMult5').value = payouts.sym_5_mult;
        document.getElementById('editMult6').value = payouts.sym_6_mult;
        document.getElementById('editMult7').value = payouts.sym_7_mult;

        new bootstrap.Modal(document.getElementById('editIslandModal')).show();
    }

    // --- LEVIATHAN 1 MILLION SPIN SIMULATION ENGINE ---
    function startSimulation(islandId) {
        const island = DB_ISLANDS.find(i => parseInt(i.id) === islandId);
        const rates = DB_SPAWN_RATES[islandId];
        const pouts = DB_PAYOUTS[islandId];
        
        document.getElementById('simTitle').innerText = island.name;
        document.getElementById('reportDate').innerText = new Date().toLocaleString();
        
        const term = document.getElementById('simTerminal');
        document.getElementById('simResults').classList.add('d-none');
        document.getElementById('btnPdfExport').classList.add('d-none');
        
        term.innerHTML = `> Establishing mathematical sandbox for [${island.name}]...\n> Fetching DNA Spawn Rates...\n> Fetching Database Multipliers...\n> Target Base RTP: ${island.rtp_rate}%\n> Executing ONE MILLION (1,000,000) spins at 1,000 MMK bet...\n\n`;
        
        new bootstrap.Modal(document.getElementById('simModal')).show();

        const bet = 1000;
        const targetRtp = parseFloat(island.rtp_rate);
        
        // Multipliers from DB
        const multipliers = {
            1: parseFloat(pouts.sym_1_mult), 2: parseFloat(pouts.sym_2_mult), 3: parseFloat(pouts.sym_3_mult),
            4: parseFloat(pouts.sym_4_mult), 5: parseFloat(pouts.sym_5_mult), 6: parseFloat(pouts.sym_6_mult), 7: parseFloat(pouts.sym_7_mult)
        };
        
        // Standard win weights mapping (mirrors spin.php)
        const winSymWeights = {2: 5, 3: 10, 4: 25, 5: 20, 6: 25, 7: 15};
        
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
        const MAX_SPINS = 1000000; // 1 MILLION
        const BATCH_SIZE = 50000; // 50k per tick to prevent browser crash
        
        let totalIn = 0;
        let totalOut = 0;
        let totalWinningSpins = 0;
        let hits = {1:0, 2:0, 3:0, 4:0, 5:0, 6:0, 7:0}; 

        simInterval = setInterval(() => {
            let massiveWins = [];
            
            for(let b=0; b<BATCH_SIZE && spins < MAX_SPINS; b++) {
                spins++;
                totalIn += bet;

                // Visual background math
                let r1 = pickSymbol(rates[1]); let r2 = pickSymbol(rates[2]); let r3 = pickSymbol(rates[3]);
                hits[r1]++; hits[r2]++; hits[r3]++;

                // Hit Engine
                let isHit = (Math.random() * 10000) <= (targetRtp * 100);
                
                if (isHit) {
                    let winSym = pickWinSymbol();
                    let winAmt = bet * multipliers[winSym];
                    totalOut += winAmt;
                    
                    if (winAmt > 0) totalWinningSpins++;
                    
                    // Log mega hits for terminal visual flair
                    if (multipliers[winSym] >= 20) {
                        massiveWins.push(`[SPIN ${spins}] MEGA HIT: Symbol ${winSym} Paid +${winAmt.toLocaleString()} MMK`);
                    }
                }
            }

            // Update terminal with batch progress
            let pct = ((spins / MAX_SPINS) * 100).toFixed(0);
            let termMsg = `> Processing Batch... [${pct}%] - ${spins.toLocaleString()} spins completed.\n`;
            if (massiveWins.length > 0) {
                termMsg += `<span style="color:#ff0;">   ↳ Caught ${massiveWins.length} high-tier multiplier hits in this batch.</span>\n`;
            }
            
            term.innerHTML += termMsg;
            term.scrollTop = term.scrollHeight;

            // END OF SIMULATION
            if (spins >= MAX_SPINS) {
                clearInterval(simInterval);
                term.innerHTML += `\n<span style="color:#0f0; font-weight:bold;">> 1,000,000 SPINS COMPLETE. COMPILING PDF REPORT...</span>\n`;
                term.scrollTop = term.scrollHeight;
                
                let actualRtp = ((totalOut / totalIn) * 100).toFixed(2);
                let hitFrequency = ((totalWinningSpins / MAX_SPINS) * 100).toFixed(2);
                
                document.getElementById('resTheory').innerText = `${targetRtp.toFixed(2)}%`;
                document.getElementById('resActual').innerText = `${actualRtp}%`;
                document.getElementById('resHitFreq').innerText = `${hitFrequency}%`;
                
                // Color Code
                const diff = actualRtp - targetRtp;
                const actEl = document.getElementById('resActual');
                if (diff > 3) actEl.className = "text-danger fs-3 fw-black drop-shadow-[0_0_10px_red] animate-pulse";
                else if (diff < -3) actEl.className = "text-warning fs-3 fw-black";
                else actEl.className = "text-success fs-3 fw-black drop-shadow-[0_0_10px_lime]";

                // Distribution Matrix
                const totalSyms = MAX_SPINS * 3;
                const names = {1:'GJP', 2:'Char', 3:'BAR', 4:'Bell', 5:'Melon', 6:'Cherry', 7:'Replay'};
                const colors = {1:'#ef4444', 2:'#a855f7', 3:'#f97316', 4:'#eab308', 5:'#22c55e', 6:'#ec4899', 7:'#06b6d4'};
                
                let distHtml = '';
                for(let i=1; i<=7; i++) {
                    let pct = ((hits[i] / totalSyms) * 100).toFixed(2);
                    distHtml += `
                        <div class="col-6 mb-2">
                            <div class="d-flex justify-content-between align-items-center border-bottom border-secondary pb-1">
                                <span style="color:${colors[i]}; font-weight:bold;">${names[i]}</span>
                                <span class="text-white">${pct}%</span>
                            </div>
                        </div>`;
                }
                document.getElementById('symDistro').innerHTML = distHtml;

                document.getElementById('simResults').classList.remove('d-none');
                document.getElementById('btnPdfExport').classList.remove('d-none');
            }
        }, 50); 
    }

    function stopSimulation() {
        if (simInterval) clearInterval(simInterval);
    }

    // --- PDF EXPORT FUNCTION ---
    function exportPDF() {
        const element = document.getElementById('simResults');
        
        // Add a temporary white background just for the PDF render
        element.classList.add('bg-dark', 'p-4');
        
        const opt = {
            margin:       0.5,
            filename:     'Leviathan_RTP_Simulation_Report.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true, backgroundColor: '#050505' },
            jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).save().then(() => {
            // Optional: Revert styles if needed
        });
    }
</script>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>