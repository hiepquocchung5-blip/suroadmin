<?php
// Ensure this is loaded via the router
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');

$pageTitle = "Fleet Command Center";
requireRole(['GOD', 'FINANCE']);

// Include Header via Base Path
require_once ADMIN_BASE_PATH . '/layout/main.php';

// --- ADVANCED MACHINE ACTIONS & RTP CONTROL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $mId = (int)$_POST['machine_id'];

    try {
        if ($action === 'force_kick') {
            $pdo->prepare("UPDATE machines SET status='free', current_user_id=NULL, session_token=NULL WHERE id=?")->execute([$mId]);
            $success = "User connection terminated on Machine #$mId.";
            $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'machines')")->execute([$_SESSION['admin_id'], "Force Kicked M#$mId"]);
        } 
        elseif ($action === 'lockdown') {
            $pdo->prepare("UPDATE machines SET status='maintenance', current_user_id=NULL, session_token=NULL WHERE id=?")->execute([$mId]);
            $success = "Machine #$mId locked down for maintenance.";
        }
        elseif ($action === 'unlock') {
            $pdo->prepare("UPDATE machines SET status='free' WHERE id=?")->execute([$mId]);
            $success = "Machine #$mId restored to active duty.";
        }
        elseif ($action === 'reset_cache') {
            $pdo->prepare("UPDATE machines SET laps_since_bonus=0, session_win_streak=0, session_spins=0, bonus_mode=NULL, bonus_spins_left=0 WHERE id=?")->execute([$mId]);
            $success = "Machine #$mId AI telemetry cache and bonus states wiped.";
        }
        elseif ($action === 'force_bonus') {
            requireRole(['GOD']);
            $pdo->prepare("UPDATE machines SET bonus_mode='HEAVEN', bonus_spins_left=32 WHERE id=?")->execute([$mId]);
            $success = "Machine #$mId forced into HEAVEN Bonus Mode (32 Spins).";
            $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'machines')")->execute([$_SESSION['admin_id'], "Forced HEAVEN Mode on M#$mId"]);
        }
        elseif ($action === 'set_tenjo') {
            requireRole(['GOD']);
            $laps = (int)$_POST['laps_target'];
            $pdo->prepare("UPDATE machines SET laps_since_bonus=? WHERE id=?")->execute([$laps, $mId]);
            $success = "Machine #$mId Tenjo manually shifted to $laps laps.";
        }
        elseif ($action === 'update_visuals') {
            $skin = cleanInput($_POST['paint_skin']);
            $sticker = cleanInput($_POST['sticker_char_id']);
            $pdo->prepare("UPDATE machines SET paint_skin=?, sticker_char_id=? WHERE id=?")->execute([$skin, $sticker, $mId]);
            $success = "Machine #$mId visuals updated.";
        }
        elseif ($action === 'update_seed') {
            requireRole(['GOD']);
            $seed = (float)$_POST['jackpot_seed'];
            $pdo->prepare("UPDATE machines SET jackpot_seed=? WHERE id=?")->execute([$seed, $mId]);
            $success = "Machine #$mId local jackpot seed injected with $seed MMK.";
        }
    } catch (Exception $e) {
        $error = "Command failed: " . $e->getMessage();
    }
}

// Fetch Selection
$currentIsland = isset($_GET['island']) ? (int)$_GET['island'] : 1;
$islands = $pdo->query("SELECT id, name FROM islands WHERE id <= 5 ORDER BY id ASC")->fetchAll();
$characters = $pdo->query("SELECT char_key, name FROM characters ORDER BY name ASC")->fetchAll();

// Fetch Telemetry Data for selected floor
$stmt = $pdo->prepare("
    SELECT m.*, u.username, u.balance, u.level 
    FROM machines m 
    LEFT JOIN users u ON m.current_user_id = u.id 
    WHERE m.island_id = ? 
    ORDER BY m.machine_number ASC
");
$stmt->execute([$currentIsland]);
$machines = $stmt->fetchAll();

// Aggregates
$active = 0; $maint = 0; $hot = 0;
foreach($machines as $m) {
    if($m['status'] === 'occupied') $active++;
    if($m['status'] === 'maintenance') $maint++;
    if($m['total_payout'] > 500000 || $m['bonus_mode'] !== null) $hot++;
}
?>

<style>
    .machine-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(65px, 1fr)); gap: 8px; }
    .m-cell { height: 65px; border-radius: 6px; border: 1px solid var(--border-color); cursor: pointer; transition: all 0.2s; position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: center; align-items: center; background: var(--bg-card); }
    .m-cell:hover { transform: scale(1.1); z-index: 10; box-shadow: var(--card-shadow); border-color: var(--accent); }
    .status-free { opacity: 0.8; }
    .status-occupied { background: rgba(34, 197, 94, 0.15); border-color: #22c55e; }
    .status-maintenance { background: rgba(239, 68, 68, 0.15); border-color: #ef4444; opacity: 0.6; }
    
    .hot-indicator { position: absolute; top: 0; left: 0; right: 0; height: 3px; background: #f97316; box-shadow: 0 0 10px #f97316; }
    .heaven-indicator { position: absolute; inset: 0; border: 2px solid #a855f7; box-shadow: inset 0 0 15px rgba(168,85,247,0.4); pointer-events: none; }
</style>

<!-- HEADER NAV -->
<div class="glass-card p-3 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div class="d-flex gap-2 flex-wrap">
        <?php foreach($islands as $isl): ?>
            <a href="?route=fleet/monitor&island=<?= $isl['id'] ?>" class="btn btn-sm <?= $isl['id'] == $currentIsland ? 'btn-info fw-bold shadow-sm' : 'btn-outline-secondary' ?>">
                <?= htmlspecialchars($isl['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="d-flex gap-4 font-mono text-sm fw-bold">
        <div class="text-success"><i class="bi bi-person-fill"></i> ACTIVE: <?= $active ?></div>
        <div class="text-warning"><i class="bi bi-fire"></i> HOT: <?= $hot ?></div>
        <div class="text-danger"><i class="bi bi-tools"></i> DOWN: <?= $maint ?></div>
    </div>
</div>

<?php if(isset($success)): ?><div class="alert bg-success bg-opacity-10 text-success border border-success fw-bold small shadow-sm animate-pulse"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert bg-danger bg-opacity-10 text-danger border border-danger fw-bold small shadow-sm"><i class="bi bi-x-circle-fill me-2"></i><?= $error ?></div><?php endif; ?>

<!-- HIGH DENSITY FLEET GRID -->
<div class="glass-card p-4">
    <div class="machine-grid">
        <?php foreach($machines as $m): 
            $isHot = $m['total_payout'] > 500000;
            $isHeaven = $m['bonus_mode'] === 'HEAVEN';
        ?>
        <div class="m-cell status-<?= $m['status'] ?>" onclick='inspectMachine(<?= json_encode($m) ?>)'>
            <?php if($isHot && !$isHeaven): ?><div class="hot-indicator animate-pulse"></div><?php endif; ?>
            <?php if($isHeaven): ?><div class="heaven-indicator animate-pulse"></div><?php endif; ?>
            
            <span class="font-mono fw-bold" style="font-size: 14px; color: var(--text-main);"><?= $m['machine_number'] ?></span>
            
            <?php if($m['status'] === 'occupied'): ?>
                <span class="text-success fw-bold text-truncate w-100 text-center px-1" style="font-size: 9px;"><?= htmlspecialchars($m['username'] ?? 'UNKNOWN') ?></span>
            <?php elseif($m['status'] === 'maintenance'): ?>
                <i class="bi bi-wrench text-danger" style="font-size: 12px;"></i>
            <?php else: ?>
                <span class="text-muted" style="font-size: 9px;">FREE</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        
        <?php if(empty($machines)): ?>
            <div class="col-12 text-center text-muted py-5 w-100 font-mono">No machines found for this sector.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ADVANCED MULTI-TAB INSPECTOR MODAL -->
<div class="modal fade" id="inspectorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content glass-card border-info shadow-lg" style="background: var(--bg-card);">
            <div class="modal-header border-bottom border-secondary bg-black bg-opacity-25">
                <h5 class="modal-title text-info fw-black italic tracking-widest"><i class="bi bi-cpu"></i> UNIT #<span id="inspId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="inspectorCloseBtn"></button>
            </div>
            <div class="modal-body p-0">
                
                <!-- Quick Overview Header -->
                <div class="p-3 border-bottom border-secondary d-flex justify-content-between align-items-center" style="background: rgba(0,0,0,0.1);">
                    <div>
                        <span class="text-muted small text-uppercase fw-bold">Link:</span>
                        <span id="inspUser" class="fw-bold ms-2"></span>
                    </div>
                    <div>
                        <span class="badge bg-purple-900 bg-opacity-50 text-purple-400 border border-purple-500 font-mono px-3" id="inspMode"></span>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs nav-fill border-secondary bg-black bg-opacity-25" id="inspectorTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active text-info fw-bold rounded-0 border-0 border-bottom border-3 border-info" id="telemetry-tab" data-bs-toggle="tab" data-bs-target="#telemetry" type="button" role="tab">TELEMETRY</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-muted fw-bold rounded-0 border-0" id="controls-tab" data-bs-toggle="tab" data-bs-target="#controls" type="button" role="tab">OVERRIDES</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-muted fw-bold rounded-0 border-0" id="visuals-tab" data-bs-toggle="tab" data-bs-target="#visuals" type="button" role="tab">CONFIG</button>
                    </li>
                </ul>

                <!-- Tabs Content -->
                <div class="tab-content" id="inspectorTabsContent">
                    
                    <!-- 1. TELEMETRY TAB -->
                    <div class="tab-pane fade show active p-4" id="telemetry" role="tabpanel">
                        <div class="row g-3 font-mono">
                            <div class="col-6">
                                <div class="bg-black bg-opacity-40 p-3 rounded border border-secondary">
                                    <small class="text-muted text-uppercase d-block mb-1">Total Payout</small>
                                    <span id="inspPayout" class="text-success fw-bold fs-5"></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bg-black bg-opacity-40 p-3 rounded border border-secondary">
                                    <small class="text-muted text-uppercase d-block mb-1">Tenjo Depth</small>
                                    <span id="inspTenjo" class="text-warning fw-bold fs-5"></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bg-black bg-opacity-40 p-3 rounded border border-secondary">
                                    <small class="text-muted text-uppercase d-block mb-1">Session Laps</small>
                                    <span id="inspSessionLaps" class="text-white fw-bold fs-5"></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bg-black bg-opacity-40 p-3 rounded border border-secondary">
                                    <small class="text-muted text-uppercase d-block mb-1">Local JP Seed</small>
                                    <span id="inspJpSeedDisplay" class="text-info fw-bold fs-5"></span>
                                </div>
                            </div>
                            <div class="col-12 mt-3">
                                <button type="button" class="btn btn-outline-info w-100 fw-bold shadow-sm" onclick="openNotebook()">
                                    <i class="bi bi-journal-text me-2"></i> ACCESS MAINTENANCE NOTEBOOK
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 2. CONTROLS TAB -->
                    <div class="tab-pane fade p-4" id="controls" role="tabpanel">
                        <div class="d-grid gap-3">
                            <form method="POST" class="m-0 row g-2">
                                <input type="hidden" name="machine_id" id="cmdId1">
                                <div class="col-6">
                                    <button name="action" value="force_kick" class="btn btn-outline-warning w-100 fw-bold shadow-sm"><i class="bi bi-plug me-2"></i> KICK USER</button>
                                </div>
                                <div class="col-6">
                                    <input type="hidden" name="action" id="lockdownAction">
                                    <button id="btnLockdown" class="btn w-100 fw-bold shadow-sm"></button>
                                </div>
                            </form>

                            <?php if($_SESSION['admin_role'] === 'GOD'): ?>
                            <hr class="border-secondary opacity-25 my-1">
                            <h6 class="text-danger text-xs font-bold uppercase tracking-widest mb-0"><i class="bi bi-stars text-warning"></i> God Mode Interventions</h6>
                            
                            <form method="POST" class="m-0" onsubmit="return confirm('Immediately trigger HEAVEN Mode for this machine?');">
                                <input type="hidden" name="machine_id" id="cmdId3">
                                <input type="hidden" name="action" value="force_bonus">
                                <button class="btn btn-outline-primary w-100 fw-bold text-start shadow-sm"><i class="bi bi-lightning-charge me-2"></i> FORCE HEAVEN BONUS (32 Spins)</button>
                            </form>

                            <form method="POST" class="m-0 d-flex gap-2">
                                <input type="hidden" name="machine_id" id="cmdId4">
                                <input type="hidden" name="action" value="set_tenjo">
                                <input type="number" name="laps_target" id="inspTenjoInput" class="form-control bg-dark border-secondary text-center font-mono text-white" style="width: 120px;" required>
                                <button class="btn btn-outline-success flex-fill fw-bold shadow-sm"><i class="bi bi-arrow-up-circle me-1"></i> OVERRIDE TENJO LAPS</button>
                            </form>

                            <form method="POST" class="m-0" onsubmit="return confirm('Wipe internal AI streak and lap trackers?');">
                                <input type="hidden" name="machine_id" id="cmdId5">
                                <input type="hidden" name="action" value="reset_cache">
                                <button class="btn btn-outline-danger w-100 fw-bold text-start shadow-sm"><i class="bi bi-arrow-clockwise me-2"></i> CLEAR TELEMETRY CACHE (Hard Reset)</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 3. VISUALS & CONFIG TAB -->
                    <div class="tab-pane fade p-4" id="visuals" role="tabpanel">
                        <div class="row g-4">
                            <!-- Visual Settings -->
                            <div class="col-md-6 border-end border-secondary border-opacity-50">
                                <h6 class="text-info fw-bold small text-uppercase mb-3"><i class="bi bi-palette"></i> Cabinet Appearance</h6>
                                <form method="POST">
                                    <input type="hidden" name="machine_id" id="cmdId6">
                                    <input type="hidden" name="action" value="update_visuals">
                                    
                                    <div class="mb-3">
                                        <label class="small text-muted fw-bold">Chassis Paint</label>
                                        <select name="paint_skin" id="inspSkin" class="form-select form-select-sm bg-dark text-white border-secondary">
                                            <option value="default">Default Plastic</option>
                                            <option value="gold">24k Gold Frame</option>
                                            <option value="cyber">Carbon Fiber / Cyber</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="small text-muted fw-bold">Character Decal (Belly Glass)</label>
                                        <select name="sticker_char_id" id="inspSticker" class="form-select form-select-sm bg-dark text-white border-secondary">
                                            <option value="">-- None --</option>
                                            <?php foreach($characters as $c): ?>
                                                <option value="<?= $c['char_key'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button class="btn btn-info btn-sm w-100 fw-bold text-dark">APPLY VISUALS</button>
                                </form>
                            </div>

                            <!-- Financial Overrides -->
                            <div class="col-md-6">
                                <h6 class="text-warning fw-bold small text-uppercase mb-3"><i class="bi bi-coin"></i> Local Machine Jackpot</h6>
                                <?php if($_SESSION['admin_role'] === 'GOD'): ?>
                                <form method="POST">
                                    <input type="hidden" name="machine_id" id="cmdId7">
                                    <input type="hidden" name="action" value="update_seed">
                                    
                                    <div class="mb-3">
                                        <label class="small text-muted fw-bold">Inject Seed (MMK)</label>
                                        <input type="number" name="jackpot_seed" id="inspJpSeedInput" class="form-control bg-dark text-warning border-warning font-mono fw-bold fs-5" step="1000">
                                        <div class="form-text text-muted" style="font-size: 10px;">Feeds the local machine progressive pot.</div>
                                    </div>
                                    <button class="btn btn-warning btn-sm w-100 fw-bold text-dark">INJECT FUNDS</button>
                                </form>
                                <?php else: ?>
                                <div class="alert bg-danger bg-opacity-20 border border-danger text-danger text-[10px] p-2 text-center">
                                    GOD Access required to manipulate localized seeds.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- NEW: DEDICATED NOTEBOOK POPUP (Loads separate page via iframe) -->
<div class="modal fade" id="notebookModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content glass-card border-info" style="background: var(--bg-card);">
            <div class="modal-header border-bottom border-secondary bg-black bg-opacity-25">
                <h5 class="modal-title text-info fw-black italic tracking-widest">
                    <i class="bi bi-journal-bookmark-fill me-2"></i> MACHINE LOG: M-<span id="nbDisplayNum"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="notebookCloseBtn"></button>
            </div>
            <div class="modal-body p-0" style="height: 60vh;">
                <iframe id="notebookFrame" src="" style="width: 100%; height: 100%; border: none; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;"></iframe>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="notebookTargetDbId">

<script>
// Apply dark theme filter to close buttons
function applyCloseButtonFilter() {
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const filter = isDark ? 'invert(1) grayscale(100%) brightness(200%)' : 'none';
    const closeButtons = document.querySelectorAll('#inspectorCloseBtn, #notebookCloseBtn');
    closeButtons.forEach(btn => btn.style.filter = filter);
}

// Run on page load and when modals show
document.addEventListener('DOMContentLoaded', applyCloseButtonFilter);
document.getElementById('inspectorModal')?.addEventListener('show.bs.modal', applyCloseButtonFilter);
document.getElementById('notebookModal')?.addEventListener('show.bs.modal', applyCloseButtonFilter);

// Tab styling logic
document.querySelectorAll('#inspectorTabs .nav-link').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('#inspectorTabs .nav-link').forEach(t => {
            t.classList.remove('text-info', 'border-bottom', 'border-3', 'border-info');
            t.classList.add('text-muted');
        });
        this.classList.remove('text-muted');
        this.classList.add('text-info', 'border-bottom', 'border-3', 'border-info');
    });
});

function inspectMachine(data) {
    document.getElementById('inspId').innerText = data.machine_number;
    
    // Assign IDs to all forms
    ['cmdId1', 'cmdId2', 'cmdId3', 'cmdId4', 'cmdId5', 'cmdId6', 'cmdId7'].forEach(id => {
        if(document.getElementById(id)) document.getElementById(id).value = data.id;
    });
    
    document.getElementById('notebookTargetDbId').value = data.id;

    // Users
    if (data.status === 'occupied') {
        const uName = data.username || 'UNKNOWN';
        const uLevel = data.level || 0;
        document.getElementById('inspUser').innerText = uName + " (Lv " + uLevel + ")";
        document.getElementById('inspUser').className = "fw-bold text-success";
    } else {
        document.getElementById('inspUser').innerText = "OFFLINE";
        document.getElementById('inspUser').className = "fw-bold text-muted";
    }

    // Telemetry Tab
    document.getElementById('inspPayout').innerText = new Intl.NumberFormat().format(data.total_payout) + " MMK";
    document.getElementById('inspTenjo').innerText = (data.laps_since_bonus || 0) + " / 777";
    document.getElementById('inspSessionLaps').innerText = data.session_spins || 0;
    document.getElementById('inspJpSeedDisplay').innerText = new Intl.NumberFormat().format(data.jackpot_seed || 0) + " MMK";
    
    const modeDisplay = data.bonus_mode ? `${data.bonus_mode} (${data.bonus_spins_left} left)` : 'NORMAL';
    document.getElementById('inspMode').innerText = modeDisplay;

    // Controls Tab
    if(document.getElementById('inspTenjoInput')) document.getElementById('inspTenjoInput').value = data.laps_since_bonus || 0;

    const btnLock = document.getElementById('btnLockdown');
    if (data.status === 'maintenance') {
        btnLock.innerHTML = '<i class="bi bi-unlock me-2"></i> RESTORE MACHINE';
        btnLock.className = "btn btn-outline-success w-100 fw-bold text-start shadow-sm";
        document.getElementById('lockdownAction').value = 'unlock';
    } else {
        btnLock.innerHTML = '<i class="bi bi-lock me-2"></i> HARD LOCKDOWN (MAINTENANCE)';
        btnLock.className = "btn btn-outline-danger w-100 fw-bold text-start shadow-sm";
        document.getElementById('lockdownAction').value = 'lockdown';
    }

    // Visuals Tab
    if(document.getElementById('inspSkin')) document.getElementById('inspSkin').value = data.paint_skin || 'default';
    if(document.getElementById('inspSticker')) document.getElementById('inspSticker').value = data.sticker_char_id || '';
    if(document.getElementById('inspJpSeedInput')) document.getElementById('inspJpSeedInput').value = data.jackpot_seed || 0;

    // Reset tabs to first
    const firstTab = new bootstrap.Tab(document.querySelector('#telemetry-tab'));
    firstTab.show();
    document.querySelector('#telemetry-tab').click(); // trigger styling

    new bootstrap.Modal(document.getElementById('inspectorModal')).show();
}

function openNotebook() {
    const dbId = document.getElementById('notebookTargetDbId').value;
    const mNum = document.getElementById('inspId').innerText;
    document.getElementById('nbDisplayNum').innerText = mNum;
    
    document.getElementById('notebookFrame').src = `modules/machines/notebook.php?id=${dbId}`;
    
    const inspectorEl = document.getElementById('inspectorModal');
    const modalInst = bootstrap.Modal.getInstance(inspectorEl);
    if(modalInst) modalInst.hide();
    
    setTimeout(() => { new bootstrap.Modal(document.getElementById('notebookModal')).show(); }, 400); 
}
</script>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>