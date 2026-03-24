<?php
// Ensure this is loaded via the router
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');

$pageTitle = "Leviathan Simulation Lab";
requireRole(['GOD']);

// --- FETCH ISLANDS & CURRENT DATABASE CONFIGS ---
$islands = $pdo->query("SELECT * FROM islands WHERE id <= 5 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$ratesQuery = $pdo->query("SELECT * FROM reel_spawn_rates");
$allRates = $ratesQuery->fetchAll(PDO::FETCH_ASSOC);
$spawnRatesByIsland = [];

$payoutsQuery = $pdo->query("SELECT * FROM island_symbol_payouts");
$allPayouts = $payoutsQuery->fetchAll(PDO::FETCH_ASSOC);
$payoutsByIsland = [];

foreach($islands as $isl) {
    $spawnRatesByIsland[$isl['id']] = [
        1 => ['sym_1'=>10, 'sym_2'=>40, 'sym_3'=>100, 'sym_4'=>200, 'sym_5'=>200, 'sym_6'=>250, 'sym_7'=>200],
        2 => ['sym_1'=>10, 'sym_2'=>40, 'sym_3'=>100, 'sym_4'=>200, 'sym_5'=>200, 'sym_6'=>250, 'sym_7'=>200],
        3 => ['sym_1'=>10, 'sym_2'=>40, 'sym_3'=>100, 'sym_4'=>200, 'sym_5'=>200, 'sym_6'=>250, 'sym_7'=>200]
    ];
    $payoutsByIsland[$isl['id']] = [
        'sym_1_mult'=>100, 'sym_2_mult'=>20, 'sym_3_mult'=>10, 'sym_4_mult'=>10, 'sym_5_mult'=>15, 'sym_6_mult'=>2, 'sym_7_mult'=>0
    ];
}

foreach ($allRates as $r) { $spawnRatesByIsland[$r['island_id']][$r['reel_index']] = $r; }
foreach ($allPayouts as $p) { $payoutsByIsland[$p['island_id']] = $p; }

require_once ADMIN_BASE_PATH . '/layout/main.php';
?>

<!-- HTML2PDF CDN FOR REPORT GENERATION -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
    /* Fullscreen Circuit Chaos Aesthetics */
    .sim-lab-bg {
        background-color: #050505;
        background-image: 
            radial-gradient(rgba(239, 68, 68, 0.15) 2px, transparent 2px),
            radial-gradient(rgba(239, 68, 68, 0.1) 1px, transparent 1px);
        background-size: 40px 40px, 20px 20px;
        background-position: 0 0, 20px 20px;
    }
    
    .terminal-container {
        background-color: rgba(0, 0, 0, 0.8);
        border: 1px solid rgba(239, 68, 68, 0.3);
        box-shadow: inset 0 0 30px rgba(0, 0, 0, 0.9);
        color: #ef4444; /* Terminal Red for Deep Sim */
    }
    .terminal-line { margin: 0; padding: 0; line-height: 1.5; word-wrap: break-word; }
    
    .odometer-box {
        background: #000; border: 2px solid #333; padding: 10px 15px; border-radius: 8px;
        font-family: 'JetBrains Mono', monospace; font-size: 2rem; font-weight: 900; letter-spacing: 2px;
        box-shadow: inset 0 0 15px rgba(0,0,0,0.9);
    }

    .matrix-table th, .matrix-table td {
        border-color: rgba(255,255,255,0.1);
        padding: 0.5rem;
        vertical-align: middle;
    }
</style>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h2 class="fw-black text-danger italic tracking-widest mb-0 drop-shadow-[0_0_15px_rgba(239,68,68,0.5)]">
            <i class="bi bi-radioactive"></i> LEVIATHAN DEEP SIMULATION LAB
        </h2>
        <p class="text-muted small mt-1 font-mono">Execute high-volume mathematical integrity audits across isolated server environments.</p>
    </div>
    <a href="?route=content/islands" class="btn btn-outline-secondary fw-bold rounded-pill px-4 shadow-sm hover:text-white transition-colors">
        <i class="bi bi-arrow-left me-2"></i> EXIT LAB
    </a>
</div>

<div class="row g-4">
    <!-- CONTROLS SIDEBAR -->
    <div class="col-xl-3">
        <div class="glass-card border-danger border-opacity-50 p-4 shadow-[0_0_30px_rgba(239,68,68,0.15)] h-100 bg-black bg-opacity-80">
            <h5 class="text-danger fw-black tracking-widest italic border-bottom border-danger border-opacity-30 pb-2 mb-4">
                <i class="bi bi-sliders"></i> SIM PARAMETERS
            </h5>
            
            <div class="mb-4">
                <label class="text-gray-400 small fw-bold text-uppercase tracking-widest mb-2">Target Island Ecosystem</label>
                <select id="simIsland" class="form-select bg-dark text-white border-secondary fw-bold shadow-inner">
                    <?php foreach($islands as $isl): ?>
                        <option value="<?= $isl['id'] ?>"><?= htmlspecialchars($isl['name']) ?> (Target: <?= $isl['rtp_rate'] ?>%)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-4">
                <label class="text-gray-400 small fw-bold text-uppercase tracking-widest mb-2">Spin Volume (Iterations)</label>
                <select id="simSpins" class="form-select bg-dark text-white border-secondary font-mono shadow-inner">
                    <option value="100000">100,000 Spins</option>
                    <option value="500000">500,000 Spins</option>
                    <option value="1000000" selected>1,000,000 Spins</option>
                    <option value="5000000">5,000,000 Spins (Heavy)</option>
                    <option value="10000000">10,000,000 Spins (Extreme)</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="text-gray-400 small fw-bold text-uppercase tracking-widest mb-2">Bet Amount (MMK)</label>
                <input type="number" id="simBet" class="form-control bg-dark text-warning border-secondary font-mono fw-bold shadow-inner" value="1000" step="100">
            </div>

            <hr class="border-secondary opacity-50 my-4">

            <button id="btnStartSim" class="btn btn-danger w-100 py-3 fw-black tracking-widest shadow-[0_0_20px_rgba(239,68,68,0.4)] hover:scale-105 active:scale-95 transition-transform" onclick="startDeepSim()">
                <i class="bi bi-play-fill me-1"></i> INITIALIZE SEQUENCE
            </button>
            
            <button id="btnStopSim" class="btn btn-outline-secondary w-100 py-3 fw-black tracking-widest mt-2 d-none hover:bg-secondary hover:text-white transition-colors" onclick="stopDeepSim()">
                <i class="bi bi-stop-fill me-1"></i> ABORT
            </button>
        </div>
    </div>

    <!-- TERMINAL & HUD -->
    <div class="col-xl-9">
        <div class="glass-card p-0 border border-danger border-opacity-30 overflow-hidden h-100 d-flex flex-column shadow-2xl">
            
            <!-- Odometer HUD -->
            <div class="bg-dark border-bottom border-danger border-opacity-30 p-4 d-flex flex-wrap gap-4 gap-md-5 align-items-center justify-content-center shadow-inner relative z-10">
                <div class="text-center">
                    <div class="text-gray-500 font-mono text-xs uppercase tracking-widest fw-bold mb-1">Spins Processed</div>
                    <div class="odometer-box text-white" id="deepOdometer">0</div>
                </div>
                <div class="text-center">
                    <div class="text-gray-500 font-mono text-xs uppercase tracking-widest fw-bold mb-1">Live RTP Convergence</div>
                    <div class="odometer-box text-danger drop-shadow-[0_0_10px_red]" id="deepRtp">0.00%</div>
                </div>
                <div class="text-center">
                    <div class="text-gray-500 font-mono text-xs uppercase tracking-widest fw-bold mb-1">Net PNL (MMK)</div>
                    <div class="odometer-box text-success" id="deepPnl">0</div>
                </div>
            </div>

            <div class="d-flex flex-column flex-lg-row flex-grow-1">
                <!-- Deep Terminal -->
                <div class="terminal-container p-4 font-mono text-sm hide-scrollbar flex-grow-1 sim-lab-bg" style="height: 60vh; min-height: 500px; overflow-y: auto;">
                    <div id="deepTerminal">
                        > SYSTEM READY.<br>
                        > AWAITING SIMULATION PARAMETERS.<br>
                        > WARNING: High volume simulations may utilize substantial client CPU threads.<br>
                    </div>
                </div>
                
                <!-- Expanded Results Sidebar (Hidden until complete) -->
                <div id="deepResultsContainer" class="bg-black border-start border-danger border-opacity-30" style="flex: 0 0 550px; overflow-y: auto;">
                    <div id="deepResults" class="p-4 d-none h-100 d-flex flex-column">
                        <div class="text-center mb-4 border-bottom border-danger border-opacity-50 pb-3">
                            <h4 class="text-danger font-mono fw-black mb-1"><i class="bi bi-file-earmark-check-fill"></i> DETAILED AUDIT REPORT</h4>
                            <small class="text-muted font-mono" id="deepReportDate"></small>
                        </div>
                        
                        <!-- Core Metrics -->
                        <div class="d-grid gap-2 font-mono mb-4">
                            <div class="p-3 border border-secondary rounded bg-dark d-flex justify-content-between align-items-center">
                                <span class="text-gray-400 text-xs">THEORY RTP</span>
                                <span class="text-gray-200 fs-5 fw-bold" id="resDeepTheory">0%</span>
                            </div>
                            <div class="p-3 border border-danger rounded bg-danger bg-opacity-10 d-flex justify-content-between align-items-center shadow-[0_0_15px_rgba(239,68,68,0.3)]">
                                <span class="text-danger text-xs fw-bold">ACTUAL CONVERGED RTP</span>
                                <span class="text-danger fs-3 fw-black drop-shadow-md" id="resDeepActual">0%</span>
                            </div>
                            
                            <!-- RESTORED: HIT FREQUENCY METRIC -->
                            <div class="p-3 border border-secondary rounded bg-dark d-flex justify-content-between align-items-center">
                                <span class="text-gray-400 text-xs">HIT FREQUENCY</span>
                                <span class="text-warning fs-5 fw-bold" id="resDeepHitFreq">0%</span>
                            </div>
                            
                            <!-- Win vs Loss Ratio Bar -->
                            <div class="p-3 border border-secondary rounded bg-black d-flex flex-column justify-content-center">
                                <div class="d-flex justify-content-between text-[10px] mb-1 uppercase tracking-widest fw-bold">
                                    <span class="text-success" id="resDeepWinPctLabel">WIN: 0%</span>
                                    <span class="text-secondary" id="resDeepLossPctLabel">LOSS: 0%</span>
                                </div>
                                <div class="progress bg-secondary rounded-pill overflow-hidden" style="height: 10px;">
                                    <div id="resDeepWinBar" class="progress-bar bg-success shadow-[0_0_10px_lime]" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- PAYOUT CONTRIBUTION MATRIX -->
                        <h6 class="text-gray-400 font-mono text-[10px] uppercase tracking-widest mb-2 border-bottom border-secondary pb-1 mt-2">
                            <i class="bi bi-cash-stack text-success me-1"></i> Payout Contribution Matrix (Wins Only)
                        </h6>
                        <div class="table-responsive mb-4">
                            <table class="table table-dark table-sm mb-0 font-mono text-[10px] matrix-table text-center align-middle">
                                <thead>
                                    <tr class="text-gray-500 uppercase">
                                        <th class="text-start">Symbol</th>
                                        <th>Mult</th>
                                        <th>Hits</th>
                                        <th>Win Share</th>
                                        <th class="text-end">Total Payout</th>
                                        <th class="text-end text-info">RTP Contrib</th>
                                    </tr>
                                </thead>
                                <tbody id="deepPayoutMatrix">
                                    <!-- Injected by JS -->
                                </tbody>
                            </table>
                        </div>

                        <!-- REEL SPAWN MATRIX -->
                        <h6 class="text-gray-500 font-mono text-[10px] uppercase tracking-widest mb-2 border-bottom border-secondary pb-1">
                            <i class="bi bi-grid-3x3 text-warning me-1"></i> Base Reel Spawn Matrix (All Spins)
                        </h6>
                        <div class="text-[10px] text-gray-300 font-mono flex-grow-1 bg-black bg-opacity-50 p-3 rounded border border-white border-opacity-5">
                            <div class="row text-center g-2" id="deepSymDistro"></div>
                        </div>

                        <button type="button" class="btn btn-outline-danger w-100 py-3 mt-4 fw-black font-mono shadow-[0_0_15px_rgba(239,68,68,0.3)] hover:scale-[1.02] transition-transform" onclick="exportDeepPDF()">
                            <i class="bi bi-download me-2"></i> EXPORT SECURE PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const DB_ISLANDS = <?= json_encode($islands) ?>;
    const DB_SPAWN_RATES = <?= json_encode($spawnRatesByIsland) ?>;
    const DB_PAYOUTS = <?= json_encode($payoutsByIsland) ?>;
    
    let simInterval = null;

    // --- Safety Wrapper to prevent null reference errors ---
    const safeSetText = (id, text) => {
        const el = document.getElementById(id);
        if (el) el.innerText = text;
    };

    function startDeepSim() {
        const islandId = parseInt(document.getElementById('simIsland').value);
        const maxSpins = parseInt(document.getElementById('simSpins').value);
        const bet = parseInt(document.getElementById('simBet').value);

        const island = DB_ISLANDS.find(i => parseInt(i.id) === islandId);
        const rates = DB_SPAWN_RATES[islandId];
        const pouts = DB_PAYOUTS[islandId];
        
        document.getElementById('btnStartSim').classList.add('d-none');
        document.getElementById('btnStopSim').classList.remove('d-none');
        document.getElementById('deepResults').classList.add('d-none');
        
        safeSetText('deepReportDate', new Date().toLocaleString());
        
        const term = document.getElementById('deepTerminal');
        
        // Reset Odometers Safely
        safeSetText('deepOdometer', '0');
        safeSetText('deepRtp', '0.00%');
        safeSetText('deepPnl', '0');
        
        const pnlEl = document.getElementById('deepPnl');
        if (pnlEl) pnlEl.className = 'odometer-box text-muted';
        
        let logBuffer = [
            `> <span style="color:#fff;">[SYSTEM]</span> INITIATING DEEP AUDIT PROTOCOL...`,
            `> <span style="color:#fff;">[TARGET]</span> Ecosystem: ${island.name}`,
            `> <span style="color:#fff;">[CONFIG]</span> Target Base RTP: <span style="color:#0ff">${island.rtp_rate}%</span>`,
            `> <span style="color:#fff;">[PARAMS]</span> Executing ${maxSpins.toLocaleString()} spins at ${bet.toLocaleString()} MMK bet...`,
            `> --------------------------------------------------`
        ];
        term.innerHTML = logBuffer.join('<br/>');

        const targetRtp = parseFloat(island.rtp_rate);
        
        // Deep Configs
        const multipliers = {
            1: parseFloat(pouts.sym_1_mult), 2: parseFloat(pouts.sym_2_mult), 3: parseFloat(pouts.sym_3_mult),
            4: parseFloat(pouts.sym_4_mult), 5: parseFloat(pouts.sym_5_mult), 6: parseFloat(pouts.sym_6_mult), 7: parseFloat(pouts.sym_7_mult)
        };
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

        // Telemetry Trackers
        let spins = 0;
        const BATCH_SIZE = Math.min(25000, Math.ceil(maxSpins / 100)); 
        
        let totalIn = 0;
        let totalOut = 0;
        let totalWinningSpins = 0;
        
        // Base Matrix Trackers (All Spawns)
        let hits = {1:0, 2:0, 3:0, 4:0, 5:0, 6:0, 7:0}; 
        
        // Win Matrix Trackers (Winning Spins Only)
        let winCounts = {1:0, 2:0, 3:0, 4:0, 5:0, 6:0, 7:0};
        let winPayouts = {1:0, 2:0, 3:0, 4:0, 5:0, 6:0, 7:0};

        const symbolIcons = {1:'[7]', 2:'[CHR]', 3:'[BAR]', 4:'[BEL]', 5:'[MEL]', 6:'[CHE]', 7:'[REP]'};
        const names = {1:'GJP/7', 2:'Char', 3:'BAR', 4:'Bell', 5:'Melon', 6:'Cherry', 7:'Replay'};
        const colors = {1:'#ef4444', 2:'#a855f7', 3:'#f97316', 4:'#eab308', 5:'#22c55e', 6:'#ec4899', 7:'#06b6d4'};

        simInterval = setInterval(() => {
            let batchLogs = [];
            
            for(let b=0; b<BATCH_SIZE && spins < maxSpins; b++) {
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
                    
                    if (winAmt > 0) {
                        totalWinningSpins++;
                        winCounts[winSym]++;
                        winPayouts[winSym] += winAmt;
                    }
                    
                    // Log mega hits for terminal
                    if (multipliers[winSym] >= 15) {
                        batchLogs.push(`<div class="terminal-line"><span style="color:#0aa">[#${spins.toString().padStart(8,'0')}]</span> CRITICAL HIT! ${symbolIcons[winSym]}x3 -> <span style="color:#ff0">+${winAmt.toLocaleString()} MMK</span> (x${multipliers[winSym]})</div>`);
                    }
                }
            }

            // Milestone Logging
            if (spins % (BATCH_SIZE * 4) === 0 || spins >= maxSpins) {
                let pct = ((spins / maxSpins) * 100).toFixed(0);
                batchLogs.push(`<div class="terminal-line" style="color:#fff; font-weight:bold;">> MILESTONE: [${pct}%] Processed ${spins.toLocaleString()} spins. Aligning mathematical convergence...</div>`);
            }

            // Update Terminal DOM efficiently
            if (batchLogs.length > 0) {
                logBuffer = logBuffer.concat(batchLogs);
                if (logBuffer.length > 200) logBuffer = logBuffer.slice(logBuffer.length - 200);
                term.innerHTML = logBuffer.join('');
                term.scrollTop = term.scrollHeight;
            }

            // Update Live Odometers Safely
            let currentRtp = ((totalOut / totalIn) * 100).toFixed(2);
            let currentPnl = totalIn - totalOut;
            
            safeSetText('deepOdometer', spins.toLocaleString());
            safeSetText('deepRtp', currentRtp + '%');
            safeSetText('deepPnl', currentPnl > 0 ? '+' + currentPnl.toLocaleString() : currentPnl.toLocaleString());
            
            if (pnlEl) {
                pnlEl.className = `odometer-box ${currentPnl >= 0 ? 'text-success' : 'text-danger'}`;
            }

            // END OF SIMULATION
            if (spins >= maxSpins) {
                stopDeepSim();
                
                logBuffer.push(`<div class="terminal-line mt-4 mb-2 p-2 bg-danger text-black fw-black">> SIMULATION PROTOCOL COMPLETE. GENERATING REPORT.</div>`);
                term.innerHTML = logBuffer.join('');
                term.scrollTop = term.scrollHeight;
                
                // 1. Core Metrics
                let actualRtp = ((totalOut / totalIn) * 100).toFixed(2);
                let hitFrequency = ((totalWinningSpins / maxSpins) * 100).toFixed(2);
                
                safeSetText('resDeepTheory', `${targetRtp.toFixed(2)}%`);
                safeSetText('resDeepActual', `${actualRtp}%`);
                safeSetText('resDeepHitFreq', `${hitFrequency}%`); // <--- Restored Safe Target
                
                // Win/Loss Ratio
                let lossCount = maxSpins - totalWinningSpins;
                let winPct = ((totalWinningSpins / maxSpins) * 100).toFixed(2);
                let lossPct = ((lossCount / maxSpins) * 100).toFixed(2);
                
                safeSetText('resDeepWinPctLabel', `WIN: ${winPct}%`);
                safeSetText('resDeepLossPctLabel', `LOSS: ${lossPct}%`);
                
                const winBarEl = document.getElementById('resDeepWinBar');
                if (winBarEl) winBarEl.style.width = `${winPct}%`;
                
                // Color Code RTP
                const diff = actualRtp - targetRtp;
                const actEl = document.getElementById('resDeepActual');
                if (actEl) {
                    if (diff > 3) actEl.className = "text-danger fs-3 fw-black drop-shadow-[0_0_10px_red] animate-pulse";
                    else if (diff < -3) actEl.className = "text-warning fs-3 fw-black";
                    else actEl.className = "text-success fs-3 fw-black drop-shadow-[0_0_10px_lime]";
                }

                // 2. Build Payout Contribution Matrix (Wins Only)
                let payoutHtml = '';
                for(let i=1; i<=7; i++) {
                    let sHits = winCounts[i];
                    let sShare = totalWinningSpins > 0 ? ((sHits / totalWinningSpins) * 100).toFixed(2) : 0;
                    let sPay = winPayouts[i];
                    let sRtpCont = totalIn > 0 ? ((sPay / totalIn) * 100).toFixed(2) : 0;
                    
                    payoutHtml += `
                        <tr class="border-b border-white border-opacity-5 hover:bg-white hover:bg-opacity-5 transition-colors">
                            <td class="text-start fw-bold" style="color:${colors[i]}">${names[i]}</td>
                            <td class="text-gray-400">x${multipliers[i]}</td>
                            <td class="text-white">${sHits.toLocaleString()}</td>
                            <td class="text-gray-300">${sShare}%</td>
                            <td class="text-end text-success">${sPay.toLocaleString()}</td>
                            <td class="text-end fw-black text-info">${sRtpCont}%</td>
                        </tr>
                    `;
                }
                const matrixEl = document.getElementById('deepPayoutMatrix');
                if(matrixEl) matrixEl.innerHTML = payoutHtml;

                // 3. Distribution Matrix (All Spawns)
                const totalSyms = maxSpins * 3;
                let distHtml = '';
                for(let i=1; i<=7; i++) {
                    let pct = ((hits[i] / totalSyms) * 100).toFixed(2);
                    distHtml += `
                        <div class="col-4 col-md-6 mb-2">
                            <div class="d-flex justify-content-between align-items-center border-bottom border-secondary pb-1 px-1">
                                <span style="color:${colors[i]}; font-weight:bold;">${names[i]}</span>
                                <span class="text-white">${pct}%</span>
                            </div>
                        </div>`;
                }
                const distroEl = document.getElementById('deepSymDistro');
                if(distroEl) distroEl.innerHTML = distHtml;

                const resultsEl = document.getElementById('deepResults');
                if (resultsEl) resultsEl.classList.remove('d-none');
            }
        }, 40); 
    }

    function stopDeepSim() {
        if (simInterval) clearInterval(simInterval);
        document.getElementById('btnStartSim')?.classList.remove('d-none');
        document.getElementById('btnStopSim')?.classList.add('d-none');
    }

    // --- PDF EXPORT FUNCTION ---
    function exportDeepPDF() {
        const element = document.getElementById('deepResults');
        if (!element) return;
        
        element.classList.add('bg-black', 'p-4');
        
        const opt = {
            margin:       0.5,
            filename:     'Suropara_Deep_Audit_Report.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true, backgroundColor: '#000000' },
            jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).save().then(() => {
            element.classList.remove('bg-black', 'p-4');
        });
    }
</script>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>