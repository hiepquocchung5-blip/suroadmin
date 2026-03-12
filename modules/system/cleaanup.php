<?php
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');
$pageTitle = "System Maintenance";
requireRole(['GOD']); 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    try {
        if ($action === 'clear_game_logs') {
            $pdo->query("DELETE FROM game_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $msg = "Old game logs cleared.";
        } elseif ($action === 'clear_audit') {
            $pdo->query("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $msg = "Old audit logs cleared.";
        } elseif ($action === 'prune_tokens') {
            $pdo->query("DELETE FROM user_tokens WHERE expires_at < NOW()");
            $msg = "Expired sessions removed.";
        }
        $success = $msg;
    } catch (Exception $e) {
        $error = "Operation failed: " . $e->getMessage();
    }
}

// Get Counts
$countLogs = $pdo->query("SELECT COUNT(*) FROM game_logs")->fetchColumn();
$countAudit = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$countTokens = $pdo->query("SELECT COUNT(*) FROM user_tokens")->fetchColumn();

require_once ADMIN_BASE_PATH . '/layout/main.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="text-white fw-black mb-0 italic tracking-widest"><i class="bi bi-trash3 text-danger"></i> SYSTEM PRUNER</h3>
</div>

<?php if(isset($success)): ?><div class="alert bg-success bg-opacity-20 text-success border border-success fw-bold shadow-sm animate-pulse"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert bg-danger bg-opacity-20 text-danger border border-danger fw-bold shadow-sm"><i class="bi bi-x-circle-fill me-2"></i><?= $error ?></div><?php endif; ?>

<div class="row g-4 mb-4">
    <!-- Game Logs -->
    <div class="col-md-4">
        <div class="glass-card border-secondary h-100 p-5 text-center transition-transform hover:-translate-y-1">
            <h6 class="text-gray-400 fw-bold uppercase tracking-widest mb-3">GAME SPIN LOGS</h6>
            <h1 class="text-white font-mono fw-black mb-1"><?= number_format($countLogs) ?></h1>
            <small class="text-gray-500 font-mono d-block mb-4">Rows in Database</small>
            
            <form method="POST" onsubmit="return confirm('Delete logs older than 30 days? This cannot be undone.');">
                <input type="hidden" name="action" value="clear_game_logs">
                <button class="btn btn-outline-danger btn-sm w-100 fw-bold rounded-pill shadow-sm hover:shadow-[0_0_15px_red]">PRUNE > 30 DAYS</button>
            </form>
        </div>
    </div>

    <!-- Audit Logs -->
    <div class="col-md-4">
        <div class="glass-card border-secondary h-100 p-5 text-center transition-transform hover:-translate-y-1">
            <h6 class="text-gray-400 fw-bold uppercase tracking-widest mb-3">ADMIN AUDIT TRAIL</h6>
            <h1 class="text-white font-mono fw-black mb-1"><?= number_format($countAudit) ?></h1>
            <small class="text-gray-500 font-mono d-block mb-4">Security Records</small>
            
            <form method="POST" onsubmit="return confirm('Delete audits older than 90 days?');">
                <input type="hidden" name="action" value="clear_audit">
                <button class="btn btn-outline-warning btn-sm w-100 fw-bold rounded-pill shadow-sm hover:shadow-[0_0_15px_yellow]">PRUNE > 90 DAYS</button>
            </form>
        </div>
    </div>

    <!-- Sessions -->
    <div class="col-md-4">
        <div class="glass-card border-info border-opacity-50 h-100 p-5 text-center transition-transform hover:-translate-y-1">
            <h6 class="text-info fw-bold uppercase tracking-widest mb-3">ACTIVE SESSIONS</h6>
            <h1 class="text-cyan-400 font-mono fw-black mb-1 drop-shadow-[0_0_10px_cyan]"><?= number_format($countTokens) ?></h1>
            <small class="text-cyan-600 font-mono d-block mb-4">Auth Tokens</small>
            
            <form method="POST">
                <input type="hidden" name="action" value="prune_tokens">
                <button class="btn btn-info btn-sm w-100 fw-black rounded-pill shadow-[0_0_10px_rgba(13,202,240,0.5)]">CLEAR EXPIRED</button>
            </form>
        </div>
    </div>
</div>

<div class="glass-card bg-info bg-opacity-10 border-info border-opacity-30 p-4 d-flex gap-3 align-items-center shadow-sm">
    <i class="bi bi-info-circle-fill text-info fs-2"></i>
    <p class="text-info mb-0 small fw-bold">
        Run these maintenance tasks once a month to keep the API response times fast and reduce storage costs. Active connections will not be interrupted by token pruning.
    </p>
</div>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>