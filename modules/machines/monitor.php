<?php
// Ensure this is loaded via the router
if (!defined('__DIR__')) exit;

$pageTitle = "Fleet Command Center";
requireRole(['GOD', 'FINANCE']);

// --- ADVANCED MACHINE ACTIONS ---
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
            $pdo->prepare("UPDATE machines SET laps_since_bonus=0, session_win_streak=0, session_spins=0 WHERE id=?")->execute([$mId]);
            $success = "Machine #$mId AI telemetry cache wiped.";
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
    if($m['total_payout'] > 500000) $hot++;
}
?>

<style>
    /* High Density Grid specific to this page */
    .machine-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(65px, 1fr)); gap: 8px; }
    .m-cell { height: 65px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); cursor: pointer; transition: all 0.2s; position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: center; align-items: center; }
    .m-cell:hover { transform: scale(1.1); z-index: 10; box-shadow: 0 10px 20px rgba(0,0,0,0.5); border-color: #fff; }
    .status-free { background: rgba(255,255,255,0.05); }
    .status-occupied { background: rgba(34, 197, 94, 0.2); border-color: #22c55e; }
    .status-maintenance { background: rgba(239, 68, 68, 0.2); border-color: #ef4444; opacity: 0.6; }
    .hot-indicator { position: absolute; top: 0; left: 0; right: 0; height: 2px; background: #f97316; box-shadow: 0 0 10px #f97316; }
</style>

<!-- HEADER NAV -->
<div class="glass-card p-3 mb-4 d-flex justify-content-between align-items-center">
    <div class="d-flex gap-2">
        <?php foreach($islands as $isl): ?>
            <a href="?route=fleet/monitor&island=<?= $isl['id'] ?>" class="btn btn-sm <?= $isl['id'] == $currentIsland ? 'btn-info fw-bold shadow-lg' : 'btn-outline-secondary' ?>">
                <?= htmlspecialchars($isl['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="d-flex gap-4 font-mono text-sm">
        <div class="text-success"><i class="bi bi-person-fill"></i> ACTIVE: <?= $active ?></div>
        <div class="text-warning"><i class="bi bi-fire"></i> HOT: <?= $hot ?></div>
        <div class="text-danger"><i class="bi bi-tools"></i> DOWN: <?= $maint ?></div>
    </div>
</div>

<?php if(isset($success)): ?><div class="alert bg-success bg-opacity-25 text-success border-success fw-bold small"><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert bg-danger bg-opacity-25 text-danger border-danger fw-bold small"><?= $error ?></div><?php endif; ?>

<!-- HIGH DENSITY FLEET GRID -->
<div class="glass-card p-4">
    <div class="machine-grid">
        <?php foreach($machines as $m): 
            $isHot = $m['total_payout'] > 500000;
        ?>
        <div class="m-cell status-<?= $m['status'] ?>" onclick='inspectMachine(<?= json_encode($m) ?>)'>
            <?php if($isHot): ?><div class="hot-indicator animate-pulse"></div><?php endif; ?>
            <span class="text-white font-mono fw-bold" style="font-size: 14px;"><?= $m['machine_number'] ?></span>
            
            <?php if($m['status'] === 'occupied'): ?>
                <span class="text-success fw-bold truncate w-100 text-center px-1" style="font-size: 9px;"><?= htmlspecialchars($m['username']) ?></span>
            <?php elseif($m['status'] === 'maintenance'): ?>
                <i class="bi bi-wrench text-danger" style="font-size: 12px;"></i>
            <?php else: ?>
                <span class="text-muted" style="font-size: 9px;">FREE</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ADVANCED INSPECTOR OFF-CANVAS / MODAL -->
<div class="modal fade" id="inspectorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-info shadow-lg">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-info fw-black italic tracking-widest"><i class="bi bi-cpu"></i> UNIT #<span id="inspId"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                
                <!-- Telemetry -->
                <div class="bg-black bg-opacity-50 p-4">
                    <div class="d-flex justify-content-between mb-2 pb-2 border-bottom border-secondary border-opacity-50">
                        <span class="text-muted small uppercase">Current Link</span>
                        <span id="inspUser" class="fw-bold text-white"></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Total Payout</span>
                        <span id="inspPayout" class="font-mono text-warning fw-bold"></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Tenjo Depth (Laps)</span>
                        <span id="inspTenjo" class="font-mono text-white"></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Session Token</span>
                        <span id="inspToken" class="font-mono text-secondary small" style="font-size: 0.6rem;"></span>
                    </div>
                </div>

                <!-- Command Interface -->
                <div class="p-4 d-grid gap-2">
                    <form method="POST">
                        <input type="hidden" name="machine_id" id="cmdId1">
                        <input type="hidden" name="action" value="force_kick">
                        <button class="btn btn-outline-warning w-100 fw-bold text-start"><i class="bi bi-plug me-2"></i> FORCE DISCONNECT PLAYER</button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="machine_id" id="cmdId2">
                        <input type="hidden" name="action" id="lockdownAction">
                        <button class="btn btn-outline-danger w-100 fw-bold text-start" id="btnLockdown"><i class="bi bi-lock me-2"></i> HARD LOCKDOWN</button>
                    </form>
                    <form method="POST" onsubmit="return confirm('Wipe internal AI streak and lap trackers?');">
                        <input type="hidden" name="machine_id" id="cmdId3">
                        <input type="hidden" name="action" value="reset_cache">
                        <button class="btn btn-outline-info w-100 fw-bold text-start"><i class="bi bi-arrow-clockwise me-2"></i> CLEAR TELEMETRY CACHE</button>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function inspectMachine(data) {
    document.getElementById('inspId').innerText = data.machine_number;
    document.getElementById('cmdId1').value = data.id;
    document.getElementById('cmdId2').value = data.id;
    document.getElementById('cmdId3').value = data.id;

    if (data.status === 'occupied') {
        document.getElementById('inspUser').innerText = data.username + " (Lv " + data.level + ")";
        document.getElementById('inspUser').className = "fw-bold text-success";
    } else {
        document.getElementById('inspUser').innerText = "OFFLINE";
        document.getElementById('inspUser').className = "fw-bold text-muted";
    }

    document.getElementById('inspPayout').innerText = new Intl.NumberFormat().format(data.total_payout) + " MMK";
    document.getElementById('inspTenjo').innerText = data.laps_since_bonus + " / 777";
    document.getElementById('inspToken').innerText = data.session_token ? data.session_token.substring(0,20) + "..." : "NO_TOKEN";

    const btnLock = document.getElementById('btnLockdown');
    if (data.status === 'maintenance') {
        btnLock.innerHTML = '<i class="bi bi-unlock me-2"></i> RESTORE MACHINE';
        btnLock.className = "btn btn-outline-success w-100 fw-bold text-start";
        document.getElementById('lockdownAction').value = 'unlock';
    } else {
        btnLock.innerHTML = '<i class="bi bi-lock me-2"></i> HARD LOCKDOWN (MAINTENANCE)';
        btnLock.className = "btn btn-outline-danger w-100 fw-bold text-start";
        document.getElementById('lockdownAction').value = 'lockdown';
    }

    new bootstrap.Modal(document.getElementById('inspectorModal')).show();
}
</script>