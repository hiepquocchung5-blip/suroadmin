<?php
$pageTitle = "Security Audit Logs";
require_once '../../layout/main.php';
requireRole(['GOD']);

// Simple Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Fetch Logs
$logs = $pdo->query("
    SELECT l.*, a.username 
    FROM audit_logs l 
    LEFT JOIN admin_users a ON l.admin_id = a.id 
    ORDER BY l.created_at DESC 
    LIMIT $limit OFFSET $offset
")->fetchAll();

$total = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$totalPages = ceil($total / $limit);
?>

<div class="card">
    <div class="card-header border-secondary d-flex justify-content-between">
        <span class="text-muted">SYSTEM ACTIVITY TRACKER</span>
        <span class="badge bg-dark border border-secondary"><?= number_format($total) ?> Events</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-sm mb-0 font-monospace" style="font-size: 0.85rem;">
                <thead>
                    <tr class="text-secondary">
                        <th>TIME</th>
                        <th>ADMIN</th>
                        <th>ACTION</th>
                        <th>TARGET</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($logs as $log): ?>
                    <tr>
                        <td class="text-info"><?= $log['created_at'] ?></td>
                        <td class="fw-bold text-white"><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
                        <td><?= htmlspecialchars($log['action']) ?></td>
                        <td class="text-warning"><?= htmlspecialchars($log['target_table']) ?></td>
                        <td class="text-muted"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer border-secondary">
        <nav>
            <ul class="pagination pagination-sm justify-content-center m-0">
                <?php for($i=1; $i<=$totalPages; $i++): ?>
                    <li class="page-item <?= $i==$page ? 'active' : '' ?>">
                        <a class="page-link bg-dark border-secondary text-white" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
</div>

<?php require_once '../../layout/footer.php'; ?>