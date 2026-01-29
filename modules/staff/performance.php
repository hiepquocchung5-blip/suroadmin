<?php
$pageTitle = "Staff Performance";
require_once '../../layout/main.php';
requireRole(['GOD', 'FINANCE']);

// Fetch Performance Stats
// Counts how many transactions were processed by each admin
$sql = "
    SELECT 
        a.id, 
        a.username, 
        a.role,
        a.last_login,
        COUNT(t.id) as total_processed,
        SUM(CASE WHEN t.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN t.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        MAX(t.updated_at) as last_action
    FROM admin_users a
    LEFT JOIN transactions t ON a.id = t.processed_by_admin_id
    WHERE a.role != 'GOD' -- Usually track employees, not Root
    GROUP BY a.id
    ORDER BY total_processed DESC
";

$stats = $pdo->query($sql)->fetchAll();
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                <span class="fw-bold text-white">STAFF ACTIVITY LEADERBOARD</span>
                <span class="badge bg-info text-dark">KPI TRACKER</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0 align-middle">
                        <thead>
                            <tr class="text-secondary text-uppercase text-xs">
                                <th>Staff Member</th>
                                <th class="text-center">Total Processed</th>
                                <th class="text-center text-success">Approved</th>
                                <th class="text-center text-danger">Rejected</th>
                                <th class="text-end">Last Action</th>
                                <th class="text-end">Efficiency</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($stats)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No staff activity recorded yet.</td></tr>
                            <?php else: foreach($stats as $s): 
                                $total = $s['total_processed'];
                                $efficiency = $total > 0 ? round(($s['approved_count'] / $total) * 100) : 0;
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 35px; height: 35px;">
                                            <?= strtoupper(substr($s['username'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-white"><?= htmlspecialchars($s['username']) ?></div>
                                            <span class="badge bg-dark border border-secondary text-muted" style="font-size: 0.65rem;"><?= $s['role'] ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="fs-5 fw-bold text-white"><?= number_format($total) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="text-success fw-bold">+<?= number_format($s['approved_count']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="text-danger fw-bold">-<?= number_format($s['rejected_count']) ?></span>
                                </td>
                                <td class="text-end text-muted small">
                                    <?= $s['last_action'] ? date('M d H:i', strtotime($s['last_action'])) : 'N/A' ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <span class="small"><?= $efficiency ?>% Appr.</span>
                                        <div class="progress" style="width: 50px; height: 4px;">
                                            <div class="progress-bar bg-info" style="width: <?= $efficiency ?>%"></div>
                                        </div>
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
</div>

<?php require_once '../../layout/footer.php'; ?>