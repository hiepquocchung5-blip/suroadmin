<?php
$pageTitle = "System Maintenance";
require_once '../../layout/main.php';
requireRole(['GOD']); // Critical access only

// Get Counts
$countLogs = $pdo->query("SELECT COUNT(*) FROM game_logs")->fetchColumn();
$countAudit = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$countTokens = $pdo->query("SELECT COUNT(*) FROM user_tokens")->fetchColumn();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    try {
        if ($action === 'clear_game_logs') {
            // Keep last 30 days
            $pdo->query("DELETE FROM game_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $msg = "Old game logs cleared.";
        } elseif ($action === 'clear_audit') {
            // Keep last 90 days
            $pdo->query("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $msg = "Old audit logs cleared.";
        } elseif ($action === 'prune_tokens') {
            // Clear expired tokens
            $pdo->query("DELETE FROM user_tokens WHERE expires_at < NOW()");
            $msg = "Expired sessions removed.";
        }
        
        $success = $msg;
        // Refresh counts
        $countLogs = $pdo->query("SELECT COUNT(*) FROM game_logs")->fetchColumn();
        $countAudit = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
        $countTokens = $pdo->query("SELECT COUNT(*) FROM user_tokens")->fetchColumn();
        
    } catch (Exception $e) {
        $error = "Operation failed: " . $e->getMessage();
    }
}
?>

<?php if(isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<div class="row g-4">
    <!-- Game Logs -->
    <div class="col-md-4">
        <div class="card border-secondary h-100">
            <div class="card-body text-center">
                <h6 class="text-muted">GAME SPIN LOGS</h6>
                <h2 class="text-white my-3"><?= number_format($countLogs) ?></h2>
                <small class="d-block text-secondary mb-3">Rows in Database</small>
                
                <form method="POST" onsubmit="return confirm('Delete logs older than 30 days? This cannot be undone.');">
                    <input type="hidden" name="action" value="clear_game_logs">
                    <button class="btn btn-outline-danger btn-sm w-100">Prune > 30 Days</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Audit Logs -->
    <div class="col-md-4">
        <div class="card border-secondary h-100">
            <div class="card-body text-center">
                <h6 class="text-muted">ADMIN AUDIT TRAIL</h6>
                <h2 class="text-white my-3"><?= number_format($countAudit) ?></h2>
                <small class="d-block text-secondary mb-3">Security Records</small>
                
                <form method="POST" onsubmit="return confirm('Delete audits older than 90 days?');">
                    <input type="hidden" name="action" value="clear_audit">
                    <button class="btn btn-outline-warning btn-sm w-100">Prune > 90 Days</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Sessions -->
    <div class="col-md-4">
        <div class="card border-secondary h-100">
            <div class="card-body text-center">
                <h6 class="text-muted">ACTIVE SESSIONS</h6>
                <h2 class="text-info my-3"><?= number_format($countTokens) ?></h2>
                <small class="d-block text-secondary mb-3">Token Entries</small>
                
                <form method="POST">
                    <input type="hidden" name="action" value="prune_tokens">
                    <button class="btn btn-outline-info btn-sm w-100">Clear Expired</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info mt-4">
    <i class="bi bi-info-circle-fill me-2"></i> <strong>Tip:</strong> Run these maintenance tasks once a month to keep the API response times fast.
</div>

<?php require_once '../../layout/footer.php'; ?>