<?php
$pageTitle = "CMS - Game News";
require_once '../../layout/main.php';
requireRole(['GOD', 'FINANCE']);

// 1. Ensure Table Exists (Auto-Migration)
$pdo->exec("CREATE TABLE IF NOT EXISTS `game_news` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(100) NOT NULL,
    `content` text,
    `type` enum('info','warning','bonus') DEFAULT 'info',
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
)");

// 2. Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        $title = cleanInput($_POST['title']);
        $content = cleanInput($_POST['content']);
        $type = $_POST['type'];
        
        $pdo->prepare("INSERT INTO game_news (title, content, type) VALUES (?, ?, ?)")
            ->execute([$title, $content, $type]);
        $success = "News item posted.";
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $val = (int)$_POST['val'];
        $pdo->prepare("UPDATE game_news SET is_active = ? WHERE id = ?")->execute([$val, $id]);
        $success = "Status updated.";
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM game_news WHERE id = ?")->execute([$id]);
        $success = "News item deleted.";
    }
}

// Fetch News
$news = $pdo->query("SELECT * FROM game_news ORDER BY created_at DESC")->fetchAll();
?>

<?php if(isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header border-secondary text-info fw-bold">POST UPDATE</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="text-muted small">Headline</label>
                        <input type="text" name="title" class="form-control bg-dark text-white border-secondary" placeholder="e.g. Server Maintenance" required>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Category</label>
                        <select name="type" class="form-select bg-dark text-white border-secondary">
                            <option value="info">General Info</option>
                            <option value="bonus">Bonus / Event</option>
                            <option value="warning">Warning / Maintenance</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Message Body</label>
                        <textarea name="content" class="form-control bg-dark text-white border-secondary" rows="4"></textarea>
                    </div>
                    <button type="submit" class="btn btn-info w-100 fw-bold">PUBLISH</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header border-secondary">NEWS FEED</div>
            <div class="card-body p-0">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead>
                        <tr class="text-secondary text-uppercase text-xs">
                            <th>Type</th>
                            <th>Content</th>
                            <th>Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($news as $n): ?>
                        <tr class="<?= $n['is_active'] ? '' : 'opacity-50' ?>">
                            <td>
                                <?php 
                                $badges = ['info'=>'bg-primary', 'bonus'=>'bg-success', 'warning'=>'bg-danger'];
                                ?>
                                <span class="badge <?= $badges[$n['type']] ?>"><?= strtoupper($n['type']) ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-white"><?= htmlspecialchars($n['title']) ?></div>
                                <div class="small text-muted text-truncate" style="max-width: 300px;"><?= htmlspecialchars($n['content']) ?></div>
                            </td>
                            <td class="text-muted small"><?= date('M d', strtotime($n['created_at'])) ?></td>
                            <td class="text-end">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $n['id'] ?>">
                                    <input type="hidden" name="val" value="<?= $n['is_active'] ? 0 : 1 ?>">
                                    <button class="btn btn-sm btn-outline-secondary border-0"><i class="bi bi-eye<?= $n['is_active'] ? '' : '-slash' ?>"></i></button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $n['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger border-0"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../layout/footer.php'; ?>