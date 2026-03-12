<?php
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');
$pageTitle = "Security Audit Logs";
requireRole(['GOD']);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$logs = $pdo->query("
    SELECT l.*, a.username 
    FROM audit_logs l 
    LEFT JOIN admin_users a ON l.admin_id = a.id 
    ORDER BY l.created_at DESC 
    LIMIT $limit OFFSET $offset
")->fetchAll();

$total = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$totalPages = ceil($total / $limit);

require_once ADMIN_BASE_PATH . '/layout/main.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="text-white fw-black mb-0 italic tracking-widest"><i class="bi bi-terminal text-success"></i> AUDIT TRAIL</h3>
    <span class="badge bg-black border border-success text-success px-4 py-2 font-mono fs-6 shadow-[0_0_15px_rgba(34,197,94,0.3)] animate-pulse">
        <?= number_format($total) ?> EVENTS LOGGED
    </span>
</div>

<div class="glass-card p-0 border-success border-opacity-30 overflow-hidden shadow-lg">
    <div class="table-responsive bg-black bg-opacity-60 hide-scrollbar" style="max-height: 75vh;">
        <table class="table table-dark table-hover mb-0 align-middle font-mono text-xs">
            <thead class="sticky-top bg-black shadow-sm">
                <tr class="text-gray-500 uppercase tracking-widest text-[10px]">
                    <th class="ps-4 py-3">TIMESTAMP (UTC)</th>
                    <th>OPERATIVE</th>
                    <th>ACTION EXECUTED</th>
                    <th>TARGET SECTOR</th>
                    <th class="text-end pe-4">IP ORIGIN</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($logs as $log): ?>
                <tr class="hover:bg-success hover:bg-opacity-10 transition-colors border-b border-white border-opacity-5">
                    <td class="text-green-400 ps-4"><?= $log['created_at'] ?></td>
                    <td class="fw-bold text-white"><i class="bi bi-person-fill text-gray-600 me-1"></i> <?= htmlspecialchars($log['username'] ?? 'SYSTEM') ?></td>
                    <td class="text-gray-300"><?= htmlspecialchars($log['action']) ?></td>
                    <td><span class="badge bg-dark border border-secondary text-warning"><?= htmlspecialchars($log['target_table']) ?></span></td>
                    <td class="text-gray-500 text-end pe-4"><?= htmlspecialchars($log['ip_address'] ?? 'INTERNAL') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if($totalPages > 1): ?>
    <div class="bg-black p-3 border-t border-white border-opacity-10 d-flex justify-content-center">
        <ul class="pagination pagination-sm m-0 gap-2">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link bg-dark text-white border-secondary rounded-pill px-3" href="?route=staff/logs&page=<?= $page-1 ?>">PREV</a>
            </li>
            <li class="page-item disabled"><span class="page-link bg-transparent text-muted border-0 fw-bold">Page <?= $page ?></span></li>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link bg-dark text-white border-secondary rounded-pill px-3" href="?route=staff/logs&page=<?= $page+1 ?>">NEXT</a>
            </li>
        </ul>
    </div>
    <?php endif; ?>
</div>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>