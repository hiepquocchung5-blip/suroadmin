<?php
// Ensure this is loaded via the router
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');

$pageTitle = "World Engine & Simulations";
requireRole(['GOD', 'FINANCE']);

// --- 1. HANDLE FORM UPDATES & ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo->beginTransaction();
        $adminId = $_SESSION['admin_id'];

        if ($_POST['action'] === 'update_island') {
            requireRole(['GOD']); // Strict security for core config changes
            $id = (int)$_POST['id'];
            
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

            // Update Leviathan AI Win Rates
            $sqlWinRates = "INSERT INTO island_win_rates (island_id, base_hit_rate, max_rtp_cap, burst_volatility) 
                            VALUES (?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE 
                            base_hit_rate=VALUES(base_hit_rate), max_rtp_cap=VALUES(max_rtp_cap), burst_volatility=VALUES(burst_volatility)";
            $pdo->prepare($sqlWinRates)->execute([
                $id, 
                (float)$_POST['base_hit_rate'], 
                (float)$_POST['max_rtp_cap'], 
                (float)$_POST['burst_volatility']
            ]);

            // Update Symbol Payout Multipliers (Ultra Precision DECIMAL 16,8)
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

            $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'islands')")
                ->execute([$adminId, "Re-calibrated Math Matrix for Island #$id"]);
            $success = "Island #$id configuration and multipliers synced successfully.";
        } 
        elseif ($_POST['action'] === 'toggle_status') {
            requireRole(['GOD']);
            $id = (int)$_POST['id'];
            $status = (int)$_POST['status'];
            $pdo->prepare("UPDATE islands SET is_active = ? WHERE id = ?")->execute([$status, $id]);
            $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'islands')")
                ->execute([$adminId, "Toggled Island #$id status to $status"]);
            $success = "Island #$id connectivity status updated.";
        }
        elseif ($_POST['action'] === 'purge_island') {
            requireRole(['GOD', 'FINANCE']);
            $id = (int)$_POST['id'];
            $pdo->prepare("UPDATE machines SET status = 'free', current_user_id = NULL, session_token = NULL WHERE island_id = ?")->execute([$id]);
            $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'machines')")
                ->execute([$adminId, "Purged all player links on Island #$id"]);
            $success = "All active player links on Island #$id have been severed.";
        }
        elseif ($_POST['action'] === 'global_lockdown') {
            requireRole(['GOD']);
            $pdo->query("UPDATE islands SET is_active = 0");
            $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'islands')")
                ->execute([$adminId, "Triggered GLOBAL LOCKDOWN on all islands"]);
            $success = "GLOBAL LOCKDOWN INITIATED. All ecosystems are now OFFLINE.";
        }
        elseif ($_POST['action'] === 'global_restore') {
            requireRole(['GOD']);
            $pdo->query("UPDATE islands SET is_active = 1");
            $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'islands')")
                ->execute([$adminId, "Restored all islands to ONLINE status"]);
            $success = "SYSTEMS RESTORED. All ecosystems are now ONLINE.";
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Action failed: " . $e->getMessage();
    }
}

// --- 2. FETCH ISLANDS, SPAWN RATES, PAYOUTS & LIVE TELEMETRY ---
$islands = $pdo->query("SELECT * FROM islands WHERE id <= 5 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch active players per island
$activePlayersQuery = $pdo->query("SELECT island_id, COUNT(*) as active_count FROM machines WHERE status = 'occupied' GROUP BY island_id")->fetchAll(PDO::FETCH_KEY_PAIR);

// Fetch Spawn Rates
$ratesQuery = $pdo->query("SELECT * FROM reel_spawn_rates");
$allRates = $ratesQuery->fetchAll(PDO::FETCH_ASSOC);
$spawnRatesByIsland = [];

// Fetch Payout Multipliers
$payoutsQuery = $pdo->query("SELECT * FROM island_symbol_payouts");
$allPayouts = $payoutsQuery->fetchAll(PDO::FETCH_ASSOC);
$payoutsByIsland = [];

// Fetch Leviathan Win Rates
$winRatesQuery = $pdo->query("SELECT * FROM island_win_rates");
$allWinRates = $winRatesQuery->fetchAll(PDO::FETCH_ASSOC);
$winRatesByIsland = [];

// Fallbacks & Mapping
foreach($islands as $isl) {
    $spawnRatesByIsland[$isl['id']] = [
        1 => ['sym_1'=>10, 'sym_2'=>40, 'sym_3'=>100, 'sym_4'=>200, 'sym_5'=>200, 'sym_6'=>250, 'sym_7'=>200],
        2 => ['sym_1'=>10, 'sym_2'=>40, 'sym_3'=>100, 'sym_4'=>200, 'sym_5'=>200, 'sym_6'=>250, 'sym_7'=>200],
        3 => ['sym_1'=>10, 'sym_2'=>40, 'sym_3'=>100, 'sym_4'=>200, 'sym_5'=>200, 'sym_6'=>250, 'sym_7'=>200]
    ];
    $payoutsByIsland[$isl['id']] = [
        'sym_1_mult'=>100, 'sym_2_mult'=>20, 'sym_3_mult'=>10, 'sym_4_mult'=>10, 'sym_5_mult'=>15, 'sym_6_mult'=>2, 'sym_7_mult'=>0
    ];
    $winRatesByIsland[$isl['id']] = [
        'base_hit_rate'=>22.00000000, 'max_rtp_cap'=>95.00000000, 'burst_volatility'=>1.50000000
    ];
}

foreach ($allRates as $r) { $spawnRatesByIsland[$r['island_id']][$r['reel_index']] = $r; }
foreach ($allPayouts as $p) { $payoutsByIsland[$p['island_id']] = $p; }
foreach ($allWinRates as $wr) { $winRatesByIsland[$wr['island_id']] = $wr; }

// --- 3. FETCH CONFIGURATION AUDIT LOGS ---
$auditLogs = $pdo->query("
    SELECT al.action, al.created_at, au.username 
    FROM audit_logs al 
    LEFT JOIN admin_users au ON al.admin_id = au.id 
    WHERE al.target_table IN ('islands', 'island_symbol_payouts', 'island_win_rates') 
    ORDER BY al.created_at DESC LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);

require_once ADMIN_BASE_PATH . '/layout/main.php';
?>

<style>
    /* Circuit Chaos Aesthetics */
    .cyber-grid {
        position: relative;
    }
    .cyber-grid::before {
        content: '';
        position: absolute;
        inset: 0;
        background-size: 40px 40px;
        background-image: 
            linear-gradient(to right, rgba(0, 243, 255, 0.05) 1px, transparent 1px),
            linear-gradient(to bottom, rgba(0, 243, 255, 0.05) 1px, transparent 1px);
        animation: gridMove 20s linear infinite;
        z-index: 0;
        pointer-events: none;
    }
    @keyframes gridMove {
        0% { background-position: 0 0; }
        100% { background-position: 40px 40px; }
    }
    
    /* Sleek Tab Styles */
    .nav-tabs .nav-link { color: #94a3b8; border: none; border-bottom: 2px solid transparent; font-weight: bold; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }
    .nav-tabs .nav-link:hover { border-color: rgba(255,255,255,0.1); color: #fff; }
    .nav-tabs .nav-link.active { color: #00f3ff; border-bottom: 2px solid #00f3ff; background: rgba(0, 243, 255, 0.05); }
    
    /* Terminal Output for Sim */
    .terminal-container {
        background-color: #050505;
        background-image: radial-gradient(rgba(0, 243, 255, 0.1) 1px, transparent 1px);
        background-size: 20px 20px;
        border: 1px solid rgba(0, 243, 255, 0.2);
        box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.8);
    }
    .terminal-line { margin: 0; padding: 0; line-height: 1.4; word-wrap: break-word; }
    .odometer-box {
        background: #000; border: 2px solid #333; padding: 5px 10px; border-radius: 8px;
        font-family: 'JetBrains Mono', monospace; font-size: 1.5rem; font-weight: 900; letter-spacing: 2px;
        box-shadow: inset 0 0 10px rgba(0,0,0,0.8);
    }
</style>

<div class="cyber-grid w-100 h-100 absolute top-0 left-0 -z-10"></div>

<div class="d-flex justify-content-between align-items-end mb-4 relative z-10 flex-wrap gap-3">
    <div>
        <h2 class="fw-black text-info italic tracking-widest mb-0 drop-shadow-[0_0_15px_rgba(0,243,255,0.4)]">
            <i class="bi bi-globe-americas"></i> WORLD ENGINE
        </h2>
        <p class="text-muted small mt-1 font-mono">Manage environmental variables, economy gates, multipliers, and ecosystem status.</p>
    </div>
    
    <div class="d-flex gap-2">
        <!-- GLOBAL LOCKDOWN CONTROLS (GOD ONLY) -->
        <?php if($_SESSION['admin_role'] === 'GOD'): ?>
        <form method="POST" class="m-0" onsubmit="return confirm('DANGER: This will instantly take ALL islands offline. Proceed?');">
            <input type="hidden" name="action" value="global_lockdown">
            <button type="submit" class="btn btn-danger fw-bold rounded-pill px-4 shadow-[0_0_15px_rgba(239,68,68,0.5)] animate-pulse">
                <i class="bi bi-shield-lock-fill me-1"></i> GLOBAL LOCKDOWN
            </button>
        </form>
        <form method="POST" class="m-0" onsubmit="return confirm('Restore ALL islands to online status?');">
            <input type="hidden" name="action" value="global_restore">
            <button type="submit" class="btn btn-outline-success fw-bold rounded-pill px-4 shadow-sm hover:shadow-[0_0_15px_rgba(34,197,94,0.4)]">
                <i class="bi bi-shield-check me-1"></i> RESTORE ALL
            </button>
        </form>
        <?php endif; ?>
        
        <a href="?route=content/simulate" class="btn btn-outline-info fw-bold rounded-pill px-4 ms-md-2 shadow-[0_0_15px_rgba(0,243,255,0.3)]">
            <i class="bi bi-radioactive me-2"></i> DEEP SIM LAB
        </a>
    </div>
</div>

<?php if(isset($success)): ?><div class="alert bg-success bg-opacity-20 text-success border border-success fw-bold shadow-sm animate-pulse relative z-10"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert bg-danger bg-opacity-20 text-danger border border-danger fw-bold shadow-sm relative z-10"><i class="bi bi-x-circle-fill me-2"></i><?= $error ?></div><?php endif; ?>

<!-- ISLAND CARDS GRID -->
<div class="row g-4 relative z-10 mb-4">
    <?php foreach($islands as $isl): 
        $pouts = $payoutsByIsland[$isl['id']];
        $wr = $winRatesByIsland[$isl['id']];
        $activeLinks = $activePlayersQuery[$isl['id']] ?? 0;
        $isActive = $isl['is_active'];
        
        $rtpColor = $isl['rtp_rate'] > 85 ? 'text-danger' : ($isl['rtp_rate'] < 60 ? 'text-info' : 'text-success');
        $insightData = $islandInsights[$isl['id']];
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="glass-card h-100 overflow-hidden position-relative group d-flex flex-column transition-colors shadow-[0_0_20px_rgba(0,0,0,0.4)] <?= $isActive ? 'border-info border-opacity-30 hover:border-opacity-100' : 'border-danger border-opacity-50 grayscale-[40%]' ?>">
            
            <div class="bg-black bg-opacity-50 p-4 border-b border-white border-opacity-10 d-flex justify-content-between align-items-start relative">
                <?php if(!$isActive): ?><div class="absolute inset-0 bg-danger opacity-10 animate-pulse pointer-events-none"></div><?php endif; ?>
                <div class="relative z-10">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-dark border border-secondary text-info font-mono">SYS_ID: 00<?= $isl['id'] ?></span>
                        <?php if($isActive): ?>
                            <span class="badge bg-success bg-opacity-20 text-success border border-success"><span class="w-1.5 h-1.5 rounded-full bg-success inline-block animate-pulse me-1"></span> ONLINE</span>
                        <?php else: ?>
                            <span class="badge bg-danger bg-opacity-20 text-danger border border-danger">OFFLINE</span>
                        <?php endif; ?>
                    </div>
                    <h4 class="fw-black text-white italic text-uppercase m-0"><?= htmlspecialchars($isl['name']) ?></h4>
                    <div class="text-muted small mt-1"><i class="bi bi-person-heart"></i> Hostess: <strong class="text-white"><?= strtoupper($isl['hostess_char_id']) ?></strong></div>
                </div>
            </div>

            <div class="card-body p-4 flex-grow-1 d-flex flex-column">
                
                <!-- TELEMETRY & AI METRICS -->
                <div class="d-flex justify-content-between gap-2 mb-3">
                    <div class="flex-1 bg-black bg-opacity-40 border border-white border-opacity-5 rounded-lg p-2 text-center shadow-inner">
                        <small class="text-gray-500 d-block text-[9px] text-uppercase fw-bold mb-1">Active Links</small>
                        <span class="text-cyan-400 fw-bold font-mono fs-6 d-flex align-items-center justify-content-center gap-1">
                            <i class="bi bi-activity <?= $activeLinks > 0 ? 'animate-pulse' : '' ?>"></i> <?= number_format($activeLinks) ?>
                        </span>
                    </div>
                    <div class="flex-1 bg-black bg-opacity-40 border border-white border-opacity-5 rounded-lg p-2 text-center shadow-inner">
                        <small class="text-gray-500 d-block text-[9px] text-uppercase fw-bold mb-1">Req Deposit</small>
                        <span class="text-warning fw-bold font-mono fs-6"><?= number_format($isl['req_deposit']) ?> <span class="text-[9px]">MMK</span></span>
                    </div>
                </div>

                <div class="bg-danger bg-opacity-10 border border-danger border-opacity-30 rounded-lg p-3 mb-4 font-mono">
                    <div class="d-flex justify-content-between align-items-center mb-1 text-[10px]">
                        <span class="text-danger fw-bold uppercase"><i class="bi bi-cpu"></i> LEVIATHAN AI</span>
                        <span class="text-gray-400">Target RTP: <span class="text-white"><?= number_format($isl['rtp_rate'], 1) ?>%</span></span>
                    </div>
                    <div class="d-flex justify-content-between text-[10px] text-gray-300">
                        <span>Base Hit: <span class="text-white fw-bold"><?= number_format($wr['base_hit_rate'], 4) ?>%</span></span>
                        <span>Hard Cap: <span class="text-white fw-bold"><?= number_format($wr['max_rtp_cap'], 2) ?>%</span></span>
                    </div>
                </div>

                <div class="d-grid gap-2 mt-auto">
                    <!-- TWEAK CONFIG (GOD ONLY) -->
                    <?php if($_SESSION['admin_role'] === 'GOD'): ?>
                    <button class="btn btn-outline-info w-100 fw-bold py-2 shadow-sm text-[11px] tracking-widest hover:bg-info hover:text-black transition-colors" onclick='openEditModal(<?= json_encode($isl) ?>, <?= json_encode($pouts) ?>, <?= json_encode($wr) ?>)'>
                        <i class="bi bi-sliders"></i> CONFIG & MULTIPLIERS
                    </button>
                    <?php endif; ?>
                    
                    <!-- SPAWN RATES -->
                    <a href="?route=content/spawn_rates&island=<?= $isl['id'] ?>" class="btn btn-outline-warning w-100 fw-bold py-2 shadow-sm text-[11px] tracking-widest hover:bg-warning hover:text-black transition-colors">
                        <i class="bi bi-gear-wide-connected"></i> ADJUST SPAWN RATES
                    </a>

                    <!-- QUICK SIM -->
                    <button class="btn w-100 fw-black py-2 shadow-[0_0_15px_rgba(59,130,246,0.3)] text-[11px] tracking-widest hover:scale-105 active:scale-95 transition-transform" style="background: linear-gradient(90deg, #3b82f6, #2563eb); color: #fff;" onclick="startSimulation(<?= $isl['id'] ?>)">
                        <i class="bi bi-lightning-charge-fill me-1"></i> QUICK SIM (10K SPINS)
                    </button>
                    
                    <!-- ECOSYSTEM CONTROLS -->
                    <div class="row g-2 mt-1">
                        <?php if($_SESSION['admin_role'] === 'GOD'): ?>
                        <div class="col-6">
                            <form method="POST" class="m-0" onsubmit="return confirm('Toggle Island Online/Offline status?');">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?= $isl['id'] ?>">
                                <input type="hidden" name="status" value="<?= $isActive ? 0 : 1 ?>">
                                <button type="submit" class="btn <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?> w-100 fw-bold py-2 text-[10px] tracking-widest hover:text-white transition-colors">
                                    <i class="bi bi-power me-1"></i> <?= $isActive ? 'OFFLINE' : 'ONLINE' ?>
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                        
                        <div class="<?= $_SESSION['admin_role'] === 'GOD' ? 'col-6' : 'col-12' ?>">
                            <form method="POST" class="m-0" onsubmit="return confirm('WARNING: This will instantly disconnect all <?= $activeLinks ?> players currently on this island. Proceed?');">
                                <input type="hidden" name="action" value="purge_island">
                                <input type="hidden" name="id" value="<?= $isl['id'] ?>">
                                <button type="submit" class="btn btn-dark border border-danger text-danger w-100 fw-bold py-2 text-[10px] tracking-widest hover:bg-danger hover:text-white transition-colors" <?= $activeLinks == 0 ? 'disabled' : '' ?>>
                                    <i class="bi bi-x-circle me-1"></i> PURGE LINKS
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- AUDIT LOG TERMINAL -->
<div class="glass-card p-0 border-secondary overflow-hidden relative z-10 shadow-lg mb-4">
    <div class="bg-black bg-opacity-60 text-white fw-bold p-3 border-b border-white border-opacity-10 tracking-widest italic d-flex align-items-center justify-content-between">
        <span><i class="bi bi-terminal text-info me-2"></i> CONFIGURATION AUDIT TRAIL</span>
        <span class="badge bg-secondary bg-opacity-20 text-gray-400 font-mono">LAST 15 EVENTS</span>
    </div>
    <div class="table-responsive bg-black bg-opacity-40" style="max-height: 250px; overflow-y: auto;">
        <table class="table table-dark table-hover mb-0 align-middle font-mono text-[10px] m-0">
            <thead class="sticky-top bg-black border-b border-secondary">
                <tr class="text-gray-500 uppercase tracking-widest">
                    <th class="ps-4 py-2">Timestamp (UTC)</th>
                    <th>Operative</th>
                    <th>Action Protocol</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($auditLogs as $log): ?>
                <tr class="border-b border-white border-opacity-5 hover:bg-white hover:bg-opacity-5">
                    <td class="text-gray-400 ps-4"><?= $log['created_at'] ?></td>
                    <td class="text-info fw-bold"><i class="bi bi-person-badge me-1"></i> <?= htmlspecialchars($log['username']) ?></td>
                    <td class="text-white"><?= htmlspecialchars($log['action']) ?></td>
                </tr>
                <?php endforeach; if(empty($auditLogs)) echo '<tr><td colspan="3" class="text-center py-4 text-muted">No recent configuration changes logged.</td></tr>'; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- QUICK 10K SPIN SIMULATION MODAL -->
<div class="modal fade" id="simModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-centered">
        <div class="modal-content border-info" style="background-color: #050505; box-shadow: 0 0 50px rgba(0,243,255,0.2);">
            <div class="modal-header border-info border-opacity-50 bg-info bg-opacity-10 py-3">
                <h6 class="modal-title font-mono text-info fw-bold"><i class="bi bi-lightning-charge me-2"></i> QUICK SIM (10K) - <span id="simTitle"></span></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="stopSimulation()"></button>
            </div>
            
            <div class="bg-dark border-bottom border-secondary p-3 d-flex flex-wrap gap-4 align-items-center justify-content-center shadow-inner">
                <div class="text-center">
                    <div class="text-gray-500 font-mono text-[10px] uppercase tracking-widest fw-bold">Spins Processed</div>
                    <div class="odometer-box text-white" id="liveOdometer">0</div>
                </div>
                <div class="text-center">
                    <div class="text-gray-500 font-mono text-[10px] uppercase tracking-widest fw-bold">Live RTP</div>
                    <div class="odometer-box text-info" id="liveRtp">0.00%</div>
                </div>
            </div>

            <div class="modal-body p-0 d-flex flex-column flex-lg-row">
                <!-- Terminal Output -->
                <div class="terminal-container p-4 font-mono text-xs hide-scrollbar flex-grow-1" style="height: 50vh; min-height: 400px; overflow-y: auto; color: #0f0;">
                    <div id="simTerminal"></div>
                </div>
                
                <!-- Results Dashboard -->
                <div id="simResultsContainer" class="bg-dark border-start border-secondary" style="flex: 0 0 350px; overflow-y: auto;">
                    <div id="simResults" class="p-4 d-none bg-dark h-100">
                        <div class="text-center mb-3 border-bottom border-secondary pb-2">
                            <h5 class="text-info font-mono fw-black mb-0">QUICK AUDIT</h5>
                        </div>
                        
                        <div class="d-grid gap-2 font-mono mb-4">
                            <div class="p-2 border border-secondary rounded bg-black bg-opacity-50 d-flex justify-content-between align-items-center">
                                <span class="text-muted" style="font-size: 10px;">THEORETICAL RTP</span>
                                <span class="text-gray-300 fs-5 fw-bold" id="resTheory">0%</span>
                            </div>
                            <div class="p-2 border border-info rounded bg-info bg-opacity-20 d-flex justify-content-between align-items-center shadow-[0_0_15px_rgba(0,243,255,0.3)]">
                                <span class="text-info" style="font-size: 10px;">ACTUAL RTP</span>
                                <span class="text-info fs-3 fw-black drop-shadow-md" id="resActual">0%</span>
                            </div>
                        </div>
                        
                        <h6 class="text-gray-400 font-mono text-[10px] uppercase tracking-widest mb-2 border-bottom border-secondary pb-1">Symbol Drops</h6>
                        <div class="text-[10px] text-gray-300 font-mono">
                            <div class="row text-center g-2" id="symDistro"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer border-info border-opacity-30 bg-black">
                <a href="?route=content/simulate" class="btn btn-outline-danger fw-bold font-mono">
                    <i class="bi bi-radioactive me-2"></i> OPEN DEEP SIMULATOR (1M SPINS)
                </a>
            </div>
        </div>
    </div>
</div>

<!-- EDIT CONFIG & MULTIPLIERS MODAL -->
<?php if($_SESSION['admin_role'] === 'GOD'): ?>
<div class="modal fade" id="editIslandModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <form method="POST" class="modal-content glass-card border-info shadow-[0_0_50px_rgba(13,202,240,0.2)] bg-dark">
            <div class="modal-header border-secondary bg-black bg-opacity-50">
                <h5 class="modal-title fw-black text-info italic tracking-widest"><i class="bi bi-sliders2"></i> SYSTEM OVERRIDE MATRIX</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-0">
                <input type="hidden" name="action" value="update_island">
                <input type="hidden" name="id" id="editId">
                
                <!-- Tab Navigation -->
                <ul class="nav nav-tabs px-4 pt-3 border-secondary bg-black bg-opacity-30" id="configTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="env-tab" data-bs-toggle="tab" data-bs-target="#env" type="button" role="tab">
                            <i class="bi bi-globe me-1"></i> Environment
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-danger" id="ai-tab" data-bs-toggle="tab" data-bs-target="#ai" type="button" role="tab">
                            <i class="bi bi-cpu me-1"></i> Leviathan AI
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-success" id="mult-tab" data-bs-toggle="tab" data-bs-target="#mult" type="button" role="tab">
                            <i class="bi bi-cash-stack me-1"></i> Payout Multipliers
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content p-4" id="configTabsContent">
                    
                    <!-- TAB 1: ENVIRONMENT -->
                    <div class="tab-pane fade show active" id="env" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small text-white fw-bold">Display Name</label>
                                <input type="text" name="name" id="editName" class="form-control bg-black text-white border-secondary shadow-inner" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-white fw-bold">Hostess Character (Key)</label>
                                <input type="text" name="hostess_char_id" id="editHostess" class="form-control bg-black text-white border-secondary shadow-inner" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-white fw-bold">Description</label>
                                <textarea name="desc" id="editDesc" class="form-control bg-black text-white border-secondary shadow-inner" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-white fw-bold">Atmosphere FX</label>
                                <select name="atmosphere_type" id="editAtm" class="form-select bg-black text-white border-secondary shadow-inner">
                                    <option value="none">None</option> <option value="neon_rain">Neon Rain</option>
                                    <option value="sunset">Sunset/Fireflies</option> <option value="ash">Volcanic Ash</option>
                                    <option value="snow">Snow</option> <option value="clouds">Moving Clouds</option>
                                    <option value="spores">Bio Spores</option> <option value="static">Cyber Static</option>
                                    <option value="steam">Steam & Sparks</option> <option value="stars">Cosmic Stars</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-warning fw-bold">Required Lifetime Deposit (MMK)</label>
                                <input type="number" name="req_deposit" id="editReqDep" class="form-control bg-black text-warning border-warning font-mono fw-bold shadow-inner" required>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: LEVIATHAN AI -->
                    <div class="tab-pane fade" id="ai" role="tabpanel">
                        <div class="alert bg-danger bg-opacity-10 border border-danger text-gray-300 small">
                            <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i> 
                            These settings strictly dictate the core mathematics of the slot engine. Modify with extreme caution. All rates support 8-decimal precision.
                        </div>
                        <div class="row g-4 font-mono">
                            <div class="col-md-12">
                                <label class="form-label d-flex justify-content-between">
                                    <span class="fw-bold text-info small">Target Base RTP (%)</span>
                                    <span class="text-info fw-black font-mono fs-5" id="rtpInputDisplay">70%</span>
                                </label>
                                <input type="range" name="rtp_rate" id="editRtp" class="form-range" min="10" max="95" step="0.5" oninput="document.getElementById('rtpInputDisplay').innerText = this.value + '%'">
                            </div>
                            <div class="col-md-4">
                                <label class="small text-danger fw-bold text-[10px] tracking-widest">Base Hit Rate (%)</label>
                                <input type="number" step="0.00000001" name="base_hit_rate" id="editBaseHit" class="form-control bg-black text-danger border-danger shadow-[inset_0_0_10px_rgba(239,68,68,0.2)]" required>
                            </div>
                            <div class="col-md-4">
                                <label class="small text-danger fw-bold text-[10px] tracking-widest">Hard Max RTP Cap (%)</label>
                                <input type="number" step="0.00000001" name="max_rtp_cap" id="editMaxRtp" class="form-control bg-black text-danger border-danger shadow-[inset_0_0_10px_rgba(239,68,68,0.2)]" required>
                            </div>
                            <div class="col-md-4">
                                <label class="small text-danger fw-bold text-[10px] tracking-widest">Burst Volatility (x)</label>
                                <input type="number" step="0.00000001" name="burst_volatility" id="editBurstVol" class="form-control bg-black text-danger border-danger shadow-[inset_0_0_10px_rgba(239,68,68,0.2)]" required>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: PAYOUT MULTIPLIERS -->
                    <div class="tab-pane fade" id="mult" role="tabpanel">
                        <div class="alert bg-success bg-opacity-10 border border-success text-gray-300 small">
                            <i class="bi bi-cash-stack text-success me-2"></i> 
                            Defines the exact (xBet) multiplier awarded when a specific symbol connects on a payline.
                        </div>
                        <div class="row g-3 font-mono">
                            <div class="col-sm-6 col-md-4">
                                <label class="small text-danger fw-bold text-[10px] tracking-widest">SYM 1 (7 / GJP)</label>
                                <input type="number" step="0.00000001" name="sym_1_mult" id="editMult1" class="form-control bg-black text-white border-secondary shadow-inner" required>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <label class="small text-purple-400 fw-bold text-[10px] tracking-widest">SYM 2 (Character)</label>
                                <input type="number" step="0.00000001" name="sym_2_mult" id="editMult2" class="form-control bg-black text-white border-secondary shadow-inner" required>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <label class="small text-orange-400 fw-bold text-[10px] tracking-widest">SYM 3 (BAR)</label>
                                <input type="number" step="0.00000001" name="sym_3_mult" id="editMult3" class="form-control bg-black text-white border-secondary shadow-inner" required>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <label class="small text-yellow-400 fw-bold text-[10px] tracking-widest">SYM 4 (Bell)</label>
                                <input type="number" step="0.00000001" name="sym_4_mult" id="editMult4" class="form-control bg-black text-white border-secondary shadow-inner" required>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <label class="small text-green-400 fw-bold text-[10px] tracking-widest">SYM 5 (Melon)</label>
                                <input type="number" step="0.00000001" name="sym_5_mult" id="editMult5" class="form-control bg-black text-white border-secondary shadow-inner" required>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <label class="small text-pink-400 fw-bold text-[10px] tracking-widest">SYM 6 (Cherry)</label>
                                <input type="number" step="0.00000001" name="sym_6_mult" id="editMult6" class="form-control bg-black text-white border-secondary shadow-inner" required>
                            </div>
                            <div class="col-12">
                                <label class="small text-cyan-400 fw-bold text-[10px] tracking-widest">SYM 7 (Replay / Free Spin)</label>
                                <input type="number" step="0.00000001" name="sym_7_mult" id="editMult7" class="form-control bg-black text-white border-secondary shadow-inner" required>
                                <div class="form-text text-muted text-[10px]">Replay typically pays 0x but triggers a free spin logic in engine.</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <div class="modal-footer border-secondary bg-black p-4 shadow-[0_-10px_20px_rgba(0,0,0,0.5)] z-20 relative">
                <button type="button" class="btn btn-dark fw-bold px-4 rounded-pill" data-bs-dismiss="modal">CANCEL</button>
                <button type="submit" class="btn btn-info fw-black px-5 rounded-pill shadow-[0_0_15px_rgba(13,202,240,0.4)] hover:scale-105 active:scale-95 transition-transform tracking-widest">
                    <i class="bi bi-hdd-network-fill me-1"></i> OVERWRITE SYSTEM MATRIX
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(island, payouts, winRates) {
        document.getElementById('editId').value = island.id;
        document.getElementById('editName').value = island.name;
        document.getElementById('editDesc').value = island.desc;
        document.getElementById('editHostess').value = island.hostess_char_id;
        document.getElementById('editAtm').value = island.atmosphere_type;
        document.getElementById('editReqDep').value = island.req_deposit;
        document.getElementById('editRtp').value = island.rtp_rate;
        document.getElementById('rtpInputDisplay').innerText = island.rtp_rate + '%';

        // Leviathan Win Rates
        document.getElementById('editBaseHit').value = winRates.base_hit_rate;
        document.getElementById('editMaxRtp').value = winRates.max_rtp_cap;
        document.getElementById('editBurstVol').value = winRates.burst_volatility;

        // Payouts
        document.getElementById('editMult1').value = payouts.sym_1_mult;
        document.getElementById('editMult2').value = payouts.sym_2_mult;
        document.getElementById('editMult3').value = payouts.sym_3_mult;
        document.getElementById('editMult4').value = payouts.sym_4_mult;
        document.getElementById('editMult5').value = payouts.sym_5_mult;
        document.getElementById('editMult6').value = payouts.sym_6_mult;
        document.getElementById('editMult7').value = payouts.sym_7_mult;

        // Reset to first tab on open
        const firstTab = new bootstrap.Tab(document.querySelector('#env-tab'));
        firstTab.show();

        new bootstrap.Modal(document.getElementById('editIslandModal')).show();
    }

    // --- QUICK 10K SIMULATION ---
    const DB_ISLANDS = <?= json_encode($islands) ?>;
    const DB_SPAWN_RATES = <?= json_encode($spawnRatesByIsland) ?>;
    const DB_PAYOUTS = <?= json_encode($payoutsByIsland) ?>;
    const DB_WIN_RATES = <?= json_encode($winRatesByIsland) ?>;
    
    let simInterval = null;

    function startSimulation(islandId) {
        const island = DB_ISLANDS.find(i => parseInt(i.id) === islandId);
        const rates = DB_SPAWN_RATES[islandId];
        const pouts = DB_PAYOUTS[islandId];
        const wRates = DB_WIN_RATES[islandId];
        
        document.getElementById('simTitle').innerText = island.name;
        const term = document.getElementById('simTerminal');
        document.getElementById('simResults').classList.add('d-none');
        
        document.getElementById('liveOdometer').innerText = '0';
        document.getElementById('liveRtp').innerText = '0.00%';
        
        let logBuffer = [
            `> Initializing Quick Sim for [${island.name}]...`,
            `> Target Base RTP: <span style="color:#0ff">${island.rtp_rate}%</span>`,
            `> Executing 10,000 spins at 1,000 MMK bet...`,
            `--------------------------------------------------`
        ];
        term.innerHTML = logBuffer.join('<br/>');
        
        new bootstrap.Modal(document.getElementById('simModal')).show();

        const bet = 1000;
        const targetRtp = parseFloat(island.rtp_rate);
        const baseHitRate = parseFloat(wRates.base_hit_rate);
        
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

        let spins = 0;
        const MAX_SPINS = 10000; // Quick Sim limit
        const BATCH_SIZE = 1000; 
        
        let totalIn = 0, totalOut = 0, totalWinningSpins = 0;
        let hits = {1:0, 2:0, 3:0, 4:0, 5:0, 6:0, 7:0}; 
        const symbolIcons = {1:'[7]', 2:'[CHR]', 3:'[BAR]', 4:'[BEL]', 5:'[MEL]', 6:'[CHE]', 7:'[REP]'};

        simInterval = setInterval(() => {
            let batchLogs = [];
            for(let b=0; b<BATCH_SIZE && spins < MAX_SPINS; b++) {
                spins++;
                totalIn += bet;

                let r1 = pickSymbol(rates[1]); let r2 = pickSymbol(rates[2]); let r3 = pickSymbol(rates[3]);
                hits[r1]++; hits[r2]++; hits[r3]++;

                // Ultra High Precision JS Simulator Hit Engine (V5.5)
                let isHit = (Math.random() * 10000000000) <= (baseHitRate * 100000000);
                
                if (isHit) {
                    let winSym = pickWinSymbol();
                    let winAmt = bet * multipliers[winSym];
                    totalOut += winAmt;
                    if (winAmt > 0) totalWinningSpins++;
                    
                    if (multipliers[winSym] >= 10) {
                        batchLogs.push(`<div class="terminal-line"><span style="color:#0aa">[#${spins.toString().padStart(5,'0')}]</span> HIT! ${symbolIcons[winSym]}x3 -> <span style="color:#ff0">+${winAmt.toLocaleString()} MMK</span></div>`);
                    }
                }
            }

            if (batchLogs.length > 0) {
                logBuffer = logBuffer.concat(batchLogs);
                if (logBuffer.length > 100) logBuffer = logBuffer.slice(logBuffer.length - 100);
                term.innerHTML = logBuffer.join('');
                term.scrollTop = term.scrollHeight;
            }

            let currentRtp = ((totalOut / totalIn) * 100).toFixed(2);
            document.getElementById('liveOdometer').innerText = spins.toLocaleString();
            document.getElementById('liveRtp').innerText = currentRtp + '%';

            if (spins >= MAX_SPINS) {
                clearInterval(simInterval);
                logBuffer.push(`<div class="terminal-line mt-3" style="color:#0f0; font-weight:900;">> QUICK SIM COMPLETE.</div>`);
                term.innerHTML = logBuffer.join('');
                term.scrollTop = term.scrollHeight;
                
                let actualRtp = ((totalOut / totalIn) * 100).toFixed(2);
                document.getElementById('resTheory').innerText = `${targetRtp.toFixed(2)}%`;
                document.getElementById('resActual').innerText = `${actualRtp}%`;
                
                const diff = actualRtp - targetRtp;
                const actEl = document.getElementById('resActual');
                if (diff > 3) actEl.className = "text-danger fs-3 fw-black drop-shadow-[0_0_10px_red] animate-pulse";
                else if (diff < -3) actEl.className = "text-warning fs-3 fw-black";
                else actEl.className = "text-info fs-3 fw-black drop-shadow-[0_0_10px_cyan]";

                const totalSyms = MAX_SPINS * 3;
                const names = {1:'GJP/7', 2:'Char', 3:'BAR', 4:'Bell', 5:'Melon', 6:'Cherry', 7:'Replay'};
                const colors = {1:'#ef4444', 2:'#a855f7', 3:'#f97316', 4:'#eab308', 5:'#22c55e', 6:'#ec4899', 7:'#06b6d4'};
                let distHtml = '';
                for(let i=1; i<=7; i++) {
                    let pct = ((hits[i] / totalSyms) * 100).toFixed(2);
                    distHtml += `<div class="col-12 mb-2"><div class="d-flex justify-content-between border-bottom border-secondary pb-1"><span style="color:${colors[i]}; font-weight:bold;">${names[i]}</span><span class="text-white">${pct}%</span></div></div>`;
                }
                document.getElementById('symDistro').innerHTML = distHtml;
                document.getElementById('simResults').classList.remove('d-none');
            }
        }, 30); 
    }

    function stopSimulation() {
        if (simInterval) clearInterval(simInterval);
    }
</script>
<?php endif; ?>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>