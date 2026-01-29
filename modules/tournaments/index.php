<?php
$pageTitle = "Tournament Manager";
require_once '../../layout/main.php';
requireRole(['GOD', 'FINANCE']);

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        $sql = "INSERT INTO tournaments (title, `desc`, entry_fee, prize_pool, spin_limit, start_time, end_time, min_level, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'upcoming')";
        $pdo->prepare($sql)->execute([
            cleanInput($_POST['title']),
            cleanInput($_POST['desc']),
            (float)$_POST['entry_fee'],
            (float)$_POST['prize_pool'],
            (int)$_POST['spin_limit'],
            $_POST['start_time'],
            $_POST['end_time'],
            (int)$_POST['min_level']
        ]);
        $success = "Tournament created.";
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM tournaments WHERE id = ?")->execute([$id]);
        $success = "Tournament deleted.";
    } elseif ($action === 'update_status') {
        $id = (int)$_POST['id'];
        $status = $_POST['status'];
        $pdo->prepare("UPDATE tournaments SET status = ? WHERE id = ?")->execute([$status, $id]);
        $success = "Status updated to $status.";
    }
}

// Fetch Tournaments
$tourneys = $pdo->query("
    SELECT t.*, 
    (SELECT COUNT(*) FROM tournament_entries WHERE tournament_id = t.id) as players
    FROM tournaments t 
    ORDER BY t.start_time DESC
")->fetchAll();
?>

<?php if(isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<div class="row">
    <!-- CREATE FORM -->
    <div class="col-md-4">
        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning bg-opacity-10 text-warning fw-bold">HOST NEW EVENT</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="small text-muted">Title</label>
                        <input type="text" name="title" class="form-control bg-dark text-white border-secondary" required>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Description</label>
                        <textarea name="desc" class="form-control bg-dark text-white border-secondary" rows="2"></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small text-muted">Entry Fee</label>
                            <input type="number" name="entry_fee" class="form-control bg-dark text-white border-secondary" value="0">
                        </div>
                        <div class="col-6">
                            <label class="small text-muted">Prize Pool</label>
                            <input type="number" name="prize_pool" class="form-control bg-dark text-white border-secondary" value="100000">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small text-muted">Spin Limit</label>
                            <input type="number" name="spin_limit" class="form-control bg-dark text-white border-secondary" value="50">
                        </div>
                        <div class="col-6">
                            <label class="small text-muted">Min Level</label>
                            <input type="number" name="min_level" class="form-control bg-dark text-white border-secondary" value="1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Start Time</label>
                        <input type="datetime-local" name="start_time" class="form-control bg-dark text-white border-secondary" required>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">End Time</label>
                        <input type="datetime-local" name="end_time" class="form-control bg-dark text-white border-secondary" required>
                    </div>
                    <button class="btn btn-warning w-100 fw-bold text-dark">CREATE TOURNAMENT</button>
                </form>
            </div>
        </div>
    </div>

    <!-- LIST -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header border-secondary text-white">TOURNAMENT LIST</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0 align-middle">
                        <thead>
                            <tr class="text-secondary text-uppercase text-xs">
                                <th>Event</th>
                                <th>Stats</th>
                                <th>Dates</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($tourneys as $t): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-white"><?= htmlspecialchars($t['title']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($t['desc']) ?></div>
                                </td>
                                <td>
                                    <div class="badge bg-dark border border-secondary text-info">
                                        Pool: <?= number_format($t['prize_pool']) ?>
                                    </div>
                                    <div class="small mt-1 text-muted">
                                        <i class="bi bi-people"></i> <?= $t['players'] ?> Players
                                    </div>
                                </td>
                                <td class="small text-muted">
                                    Start: <?= date('M d H:i', strtotime($t['start_time'])) ?><br>
                                    End: <?= date('M d H:i', strtotime($t['end_time'])) ?>
                                </td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                        <select name="status" class="form-select form-select-sm bg-black text-white border-secondary" onchange="this.form.submit()">
                                            <option <?= $t['status']=='upcoming'?'selected':'' ?>>upcoming</option>
                                            <option <?= $t['status']=='active'?'selected':'' ?>>active</option>
                                            <option <?= $t['status']=='ended'?'selected':'' ?>>ended</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="text-end">
                                    <form method="POST" onsubmit="return confirm('Delete?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
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
</div>
<?php require_once '../../layout/footer.php'; ?>