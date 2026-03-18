<?php
// Ensure this is loaded via the router
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');

$pageTitle = "Security & Threat Monitor";
requireRole(['GOD']);
require_once ADMIN_BASE_PATH . '/layout/main.php';

// Handle Actions (Resolve Alert)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'resolve') {
    $id = (int)$_POST['id'];
    $pdo->prepare("DELETE FROM security_alerts WHERE id = ?")->execute([$id]);
    $success = "Alert #$id resolved and cleared from the radar.";
    $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'security_alerts')")->execute([$_SESSION['admin_id'], "Resolved Security Alert #$id"]);
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

<!-- Radar Ping Animation -->
<style>
    .radar-container {
        position: relative;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        overflow: hidden;
    }
    .radar-sweep {
        position: absolute;
        top: 0; left: 30px; width: 30px; height: 30px;
        background: linear-gradient(45deg, rgba(239, 68, 68, 1) 0%, transparent 50%);
        transform-origin: 0 100%;
        animation: sweep 2s linear infinite;
    }
    @keyframes sweep { to { transform: rotate(360deg); } }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="radar-container shadow-[0_0_15px_rgba(239,68,68,0.3)]">
            <div class="radar-sweep"></div>
        </div>
        <div>
            <h2 class="fw-black text-danger italic tracking-widest mb-0">RISK RADAR</h2>
            <p class="text-muted small mt-1">Real-time anomaly and threat detection matrix.</p>
        </div>
    </div>
    <button class="btn btn-outline-danger fw-bold rounded-pill px-4 shadow-sm" onclick="location.reload()">
        <i class="bi bi-arrow-clockwise me-1"></i> FORCE SCAN
    </button>
</div>

<?php if(isset($success)): ?><div class="alert bg-success bg-opacity-20 text-success border border-success fw-bold shadow-sm animate-pulse"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>

<div class="row g-4">
    <!-- Live Threat Feed -->
    <div class="col-xl-8">
        <div class="glass-card p-0 border-danger border-opacity-50 overflow-hidden shadow-[0_0_30px_rgba(239,68,68,0.15)] h-100">
            <div class="bg-danger bg-opacity-20 text-danger fw-black p-3 border-b border-danger border-opacity-30 tracking-widest italic d-flex justify-content-between align-items-center">
                <span><i class="bi bi-shield-exclamation me-2"></i> ACTIVE THREAT FEED</span>
                <span class="badge bg-danger text-white font-mono"><?= count($alerts) ?> DETECTED</span>
            </div>
            
            <div class="table-responsive bg-black bg-opacity-60 hide-scrollbar" style="max-height: 60vh;">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead class="sticky-top bg-black border-secondary">
                        <tr class="text-gray-500 text-uppercase tracking-widest text-[10px]">
                            <th class="ps-4 py-3">Severity</th>
                            <th>Time</th>
                            <th>Target Entity</th>
                            <th>Vector</th>
                            <th>Details</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody class="font-mono text-sm">
                        <?php if(empty($alerts)): ?>
                            <tr><td colspan="6" class="text-center text-success py-5"><i class="bi bi-shield-check display-4 d-block mb-3 opacity-50"></i>System Secure. No anomalies detected.</td></tr>
                        <?php else: foreach($alerts as $a): 
                            $riskColor = match($a['risk_level']) {
                                'critical' => 'text-white bg-danger shadow-[0_0_10px_red] animate-pulse',
                                'high' => 'text-dark bg-warning',
                                'medium' => 'text-dark bg-info',
                                default => 'text-white bg-secondary'
                            };
                        ?>
                        <tr class="border-b border-white border-opacity-5 hover:bg-white/5 transition-colors">
                            <td class="ps-4"><span class="badge <?= $riskColor ?> px-2 py-1 rounded-pill"><?= strtoupper($a['risk_level']) ?></span></td>
                            <td class="text-gray-500 text-xs"><?= date('H:i:s', strtotime($a['created_at'])) ?></td>
                            <td>
                                <div class="fw-bold text-white font-sans"><?= htmlspecialchars($a['username']) ?></div>
                                <div class="text-[10px] text-gray-500"><?= htmlspecialchars($a['phone']) ?></div>
                            </td>
                            <td class="fw-bold text-danger"><?= htmlspecialchars($a['event_type']) ?></td>
                            <td class="text-gray-400 text-xs" style="max-width: 200px; white-space: normal;"><?= htmlspecialchars($a['details']) ?></td>
                            <td class="text-end pe-4">
                                <div class="btn-group shadow-sm">
                                    <a href="?route=players/details&id=<?= $a['user_id'] ?>" class="btn btn-sm btn-dark border-secondary text-info hover:bg-info hover:text-black" title="Investigate"><i class="bi bi-search"></i></a>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="resolve">
                                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                        <button class="btn btn-sm btn-dark border-secondary text-success hover:bg-success hover:text-black" title="Mark Resolved"><i class="bi bi-check-lg"></i></button>
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

    <!-- Security Rules (Read Only View) -->
    <div class="col-xl-4">
        <div class="glass-card p-0 border-secondary overflow-hidden h-100">
            <div class="bg-black bg-opacity-50 text-white fw-bold p-3 border-b border-white border-opacity-10 tracking-widest italic flex items-center">
                <i class="bi bi-cpu text-info me-2"></i> ACTIVE DEFENSE PROTOCOLS
            </div>
            <div class="p-4 bg-black bg-opacity-30 space-y-3">
                <div class="bg-black bg-opacity-50 p-3 rounded-xl border border-white border-opacity-5 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white fw-bold small">Velocity Limiter</div>
                        <div class="text-[10px] text-gray-500 font-mono">Blocks rapid API requests</div>
                    </div>
                    <span class="badge bg-success bg-opacity-20 text-success border border-success border-opacity-50 px-2 py-1"><span class="w-1.5 h-1.5 rounded-full bg-success inline-block me-1 animate-pulse"></span> 1 Spin / 0.3s</span>
                </div>

                <div class="bg-black bg-opacity-50 p-3 rounded-xl border border-white border-opacity-5 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white fw-bold small">High Win Trigger</div>
                        <div class="text-[10px] text-gray-500 font-mono">Flags suspicious payouts</div>
                    </div>
                    <span class="badge bg-warning bg-opacity-20 text-warning border border-warning border-opacity-50 px-2 py-1">> 1,000,000 MMK</span>
                </div>

                <div class="bg-black bg-opacity-50 p-3 rounded-xl border border-white border-opacity-5 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white fw-bold small">Bet Range Enforcement</div>
                        <div class="text-[10px] text-gray-500 font-mono">Prevents packet manipulation</div>
                    </div>
                    <span class="badge bg-info bg-opacity-20 text-info border border-info border-opacity-50 px-2 py-1 font-mono">STRICT V3 TIER</span>
                </div>

                <div class="bg-black bg-opacity-50 p-3 rounded-xl border border-white border-opacity-5 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white fw-bold small">Anti-Ghosting Protocol</div>
                        <div class="text-[10px] text-gray-500 font-mono">Clears dead machine links</div>
                    </div>
                    <span class="badge bg-danger bg-opacity-20 text-danger border border-danger border-opacity-50 px-2 py-1">5 MIN TIMEOUT</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>