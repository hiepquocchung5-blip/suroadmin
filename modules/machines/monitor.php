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
            // Forces the machine into the highest tier bonus mode immediately
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
    } catch (Exception $e) {
        $error = "Command failed: " . $e->getMessage();
    }
}

// Fetch Selection
$currentIsland = isset($_GET['island']) ? (int)$_GET['island'] : 1;
$islands = $pdo->query("SELECT id, name FROM islands WHERE id <= 5 ORDER BY id ASC")->fetchAll();

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
    /* High Density Grid specific to this page */
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
                <!-- FIXED: Added Null Coalescing Operator to prevent Line 143 crash on ghosts -->
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

<!-- ADVANCED INSPECTOR MODAL -->
<div class="modal fade" id="inspectorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-info shadow-lg" style="background: var(--bg-card);">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title text-info fw-black italic tracking-widest"><i class="bi bi-cpu"></i> UNIT #<span id="inspId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="inspectorModalClose"></button>
            </div>
            <div class="modal-body p-0">
                
                <!-- Telemetry -->
                <div class="p-4 border-bottom border-secondary" style="background: rgba(0,0,0,0.1);">
                    <div class="d-flex justify-content-between mb-2 pb-2 border-bottom border-secondary border-opacity-50">
                        <span class="text-muted small text-uppercase fw-bold">Current Link</span>
                        <span id="inspUser" class="fw-bold text-success"></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small fw-bold">Total Payout</span>
                        <span id="inspPayout" class="font-mono text-warning fw-bold"></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small fw-bold">Tenjo Depth (Laps)</span>
                        <span id="inspTenjo" class="font-mono fw-bold" style="color: var(--text-main);"></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small fw-bold">Active Mode</span>
                        <span id="inspMode" class="font-mono text-purple-500 fw-bold small text-uppercase"></span>
                    </div>
                </div>

                <!-- Command Interface -->
                <div class="p-4 d-grid gap-3">
                    <h6 class="text-muted text-xs font-bold uppercase tracking-widest mb-0">Standard Operations</h6>
                    
                    <form method="POST" class="m-0">
                        <input type="hidden" name="machine_id" id="cmdId1">
                        <input type="hidden" name="action" value="force_kick">
                        <button class="btn btn-outline-warning w-100 fw-bold text-start shadow-sm"><i class="bi bi-plug me-2"></i> FORCE DISCONNECT PLAYER</button>
                    </form>
                    
                    <form method="POST" class="m-0">
                        <input type="hidden" name="machine_id" id="cmdId2">
                        <input type="hidden" name="action" id="lockdownAction">
                        <button class="btn btn-outline-danger w-100 fw-bold text-start shadow-sm" id="btnLockdown"><i class="bi bi-lock me-2"></i> HARD LOCKDOWN</button>
                    </form>
                    
                    <!-- NEW: OPEN NOTEBOOK POPUP BUTTON -->
                    <button type="button" class="btn btn-outline-info w-100 fw-bold text-start shadow-sm mt-2" onclick="openNotebook()">
                        <i class="bi bi-journal-text me-2"></i> ACCESS MAINTENANCE NOTEBOOK
                    </button>
                    <input type="hidden" id="notebookTargetDbId">

                    <?php if($_SESSION['admin_role'] === 'GOD'): ?>
                    <hr class="border-secondary opacity-25 my-2">
                    <h6 class="text-danger text-xs font-bold uppercase tracking-widest mb-0"><i class="bi bi-stars text-warning"></i> God Mode Overrides</h6>
                    
                    <form method="POST" class="m-0" onsubmit="return confirm('Immediately trigger HEAVEN Mode for this machine?');">
                        <input type="hidden" name="machine_id" id="cmdId3">
                        <input type="hidden" name="action" value="force_bonus">
                        <button class="btn btn-outline-primary w-100 fw-bold text-start shadow-sm"><i class="bi bi-lightning-charge me-2"></i> FORCE HEAVEN BONUS (32 Spins)</button>
                    </form>

                    <form method="POST" class="m-0 d-flex gap-2">
                        <input type="hidden" name="machine_id" id="cmdId4">
                        <input type="hidden" name="action" value="set_tenjo">
                        <input type="number" name="laps_target" id="inspTenjoInput" class="form-control form-control-sm bg-transparent border-secondary text-center font-mono" style="width: 100px; color: var(--text-main);" required>
                        <button class="btn btn-outline-success btn-sm flex-fill fw-bold shadow-sm"><i class="bi bi-arrow-up-circle me-1"></i> SHIFT TENJO</button>
                    </form>

                    <form method="POST" class="m-0" onsubmit="return confirm('Wipe internal AI streak and lap trackers?');">
                        <input type="hidden" name="machine_id" id="cmdId5">
                        <input type="hidden" name="action" value="reset_cache">
                        <button class="btn btn-outline-secondary btn-sm w-100 fw-bold text-start shadow-sm"><i class="bi bi-arrow-clockwise me-2"></i> CLEAR TELEMETRY CACHE</button>
                    </form>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- NEW: DEDICATED NOTEBOOK POPUP (Loads separate page via iframe) -->
<div class="modal fade" id="notebookModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content glass-card border-info" style="background: var(--bg-card);">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title text-info fw-black italic tracking-widest">
                    <i class="bi bi-journal-bookmark-fill me-2"></i> MACHINE LOG: M-<span id="nbDisplayNum"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="notebookModalClose"></button>
            </div>
            <div class="modal-body p-0" style="height: 60vh;">
                <!-- Loads the isolated notebook page dynamically -->
                <iframe id="notebookFrame" src="" style="width: 100%; height: 100%; border: none; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
// Apply dark mode filter to close buttons on page load
document.addEventListener('DOMContentLoaded', function() {
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const closeButtons = document.getElementById('inspectorModalClose') && document.getElementById('notebookModalClose');
    if(closeButtons && isDark) {
        document.getElementById('inspectorModalClose').style.filter = 'invert(1) grayscale(100%) brightness(200%)';
        document.getElementById('notebookModalClose').style.filter = 'invert(1) grayscale(100%) brightness(200%)';
    }
});

function inspectMachine(data) {
    document.getElementById('inspId').innerText = data.machine_number;
    document.getElementById('cmdId1').value = data.id;
    document.getElementById('cmdId2').value = data.id;
    
    // Store DB ID for Notebook pop-up
    document.getElementById('notebookTargetDbId').value = data.id;
    
    if(document.getElementById('cmdId3')) {
        document.getElementById('cmdId3').value = data.id;
        document.getElementById('cmdId4').value = data.id;
        document.getElementById('cmdId5').value = data.id;
        document.getElementById('inspTenjoInput').value = data.laps_since_bonus || 0;
    }

    if (data.status === 'occupied') {
        const uName = data.username || 'UNKNOWN';
        const uLevel = data.level || 0;
        document.getElementById('inspUser').innerText = uName + " (Lv " + uLevel + ")";
        document.getElementById('inspUser').className = "fw-bold text-success";
    } else {
        document.getElementById('inspUser').innerText = "OFFLINE";
        document.getElementById('inspUser').className = "fw-bold text-muted";
    }

    document.getElementById('inspPayout').innerText = new Intl.NumberFormat().format(data.total_payout) + " MMK";
    document.getElementById('inspTenjo').innerText = (data.laps_since_bonus || 0) + " / 777";
    
    const modeDisplay = data.bonus_mode ? `${data.bonus_mode} (${data.bonus_spins_left} left)` : 'NORMAL';
    document.getElementById('inspMode').innerText = modeDisplay;

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

    new bootstrap.Modal(document.getElementById('inspectorModal')).show();
}

function openNotebook() {
    const dbId = document.getElementById('notebookTargetDbId').value;
    const mNum = document.getElementById('inspId').innerText;
    
    // Set Header
    document.getElementById('nbDisplayNum').innerText = mNum;
    
    // Route to the separate notebook page
    // Since we are inside index.php?route=..., relative path hits the root. 
    document.getElementById('notebookFrame').src = `modules/machines/notebook.php?id=${dbId}`;
    
    // Hide Inspector Modal and Show Notebook Modal
    const inspectorEl = document.getElementById('inspectorModal');
    const modalInst = bootstrap.Modal.getInstance(inspectorEl);
    if(modalInst) modalInst.hide();
    
    setTimeout(() => {
        new bootstrap.Modal(document.getElementById('notebookModal')).show();
    }, 400); // Wait for transition
}
</script>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>