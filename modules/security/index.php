<?php
$pageTitle = "Security & Threat Monitor";
require_once '../../layout/main.php';
requireRole(['GOD']);

// Handle Actions (Resolve Alert)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'resolve') {
    $id = (int)$_POST['id'];
    // In a real app, you might update a status column. 
    // Here we delete the alert to "Clear" it.
    $pdo->prepare("DELETE FROM security_alerts WHERE id = ?")->execute([$id]);
    $success = "Alert #$id resolved.";
}

// Fetch Alerts
$alerts = $pdo->query("
    SELECT s.*, u.username, u.phone 
    FROM security_alerts s 
    LEFT JOIN users u ON s.user_id = u.id 
    ORDER BY s.risk_level DESC, s.created_at DESC 
    LIMIT 50
")->fetchAll();
?>

<?php if(isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<div class="row g-4">
    <!-- Live Threat Feed -->
    <div class="col-12">
        <div class="card border-danger">
            <div class="card-header bg-danger bg-opacity-10 text-danger fw-bold d-flex justify-content-between">
                <span><i class="bi bi-shield-exclamation"></i> LIVE THREAT FEED</span>
                <span class="badge bg-danger"><?= count($alerts) ?> Active</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0 align-middle">
                        <thead>
                            <tr class="text-secondary text-uppercase text-xs">
                                <th>Severity</th>
                                <th>Time</th>
                                <th>User</th>
                                <th>Event Type</th>
                                <th>Details</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($alerts)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-5">System Secure. No active threats.</td></tr>
                            <?php else: foreach($alerts as $a): 
                                $riskColor = match($a['risk_level']) {
                                    'critical' => 'bg-danger',
                                    'high' => 'bg-warning text-dark',
                                    'medium' => 'bg-info text-dark',
                                    default => 'bg-secondary'
                                };
                            ?>
                            <tr>
                                <td><span class="badge <?= $riskColor ?>"><?= strtoupper($a['risk_level']) ?></span></td>
                                <td class="text-muted small"><?= date('H:i:s', strtotime($a['created_at'])) ?></td>
                                <td>
                                    <div class="fw-bold text-white"><?= htmlspecialchars($a['username']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($a['phone']) ?></div>
                                </td>
                                <td class="fw-bold text-info"><?= htmlspecialchars($a['event_type']) ?></td>
                                <td class="small text-white"><?= htmlspecialchars($a['details']) ?></td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a href="../users/details.php?user_id=<?= $a['user_id'] ?>" class="btn btn-sm btn-outline-light" title="Investigate"><i class="bi bi-search"></i></a>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="resolve">
                                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                            <button class="btn btn-sm btn-outline-success" title="Mark Resolved"><i class="bi bi-check-lg"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Security Rules (Read Only View) -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header border-secondary text-info fw-bold">ACTIVE DEFENSE RULES</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item bg-transparent text-white border-secondary d-flex justify-content-between">
                    <span>Rate Limiter</span>
                    <span class="text-success fw-bold">1 Spin / 2s</span>
                </li>
                <li class="list-group-item bg-transparent text-white border-secondary d-flex justify-content-between">
                    <span>High Win Trigger</span>
                    <span class="text-warning fw-bold">> 500,000 MMK</span>
                </li>
                <li class="list-group-item bg-transparent text-white border-secondary d-flex justify-content-between">
                    <span>Valid Bet Range</span>
                    <span class="font-monospace text-muted">200 - 900,000</span>
                </li>
                <li class="list-group-item bg-transparent text-white border-secondary d-flex justify-content-between">
                    <span>Session Timeout</span>
                    <span class="text-muted">30 Minutes</span>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php require_once '../../layout/footer.php'; ?>