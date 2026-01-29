<?php
$pageTitle = "Marketing Campaigns";
require_once '../../layout/main.php';
requireRole(['GOD', 'FINANCE']);

// 1. Auto-Migration
$pdo->exec("CREATE TABLE IF NOT EXISTS `marketing_events` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(100) NOT NULL,
    `type` enum('xp_boost','rtp_boost','deposit_bonus') NOT NULL,
    `multiplier` decimal(5,2) DEFAULT 1.00,
    `target_island_id` int(11) DEFAULT NULL,
    `start_time` datetime NOT NULL,
    `end_time` datetime NOT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
)");

// 2. Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'create') {
        $sql = "INSERT INTO marketing_events (title, type, multiplier, target_island_id, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([
            cleanInput($_POST['title']),
            $_POST['type'],
            $_POST['multiplier'],
            $_POST['target_island_id'] ?: NULL,
            $_POST['start_time'],
            $_POST['end_time']
        ]);
        $success = "Campaign created.";
    } elseif ($_POST['action'] === 'delete') {
        $pdo->prepare("DELETE FROM marketing_events WHERE id = ?")->execute([(int)$_POST['id']]);
        $success = "Campaign deleted.";
    }
}

// Fetch
$events = $pdo->query("SELECT m.*, i.name as island_name FROM marketing_events m LEFT JOIN islands i ON m.target_island_id = i.id ORDER BY m.is_active DESC, m.end_time DESC")->fetchAll();
$islands = $pdo->query("SELECT id, name FROM islands")->fetchAll();
?>

<?php if(isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning bg-opacity-10 text-warning fw-bold">CREATE EVENT</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="small text-muted">Campaign Title</label>
                        <input type="text" name="title" class="form-control bg-dark text-white border-secondary" placeholder="e.g. Weekend XP Blast" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small text-muted">Type</label>
                            <select name="type" class="form-select bg-dark text-white border-secondary">
                                <option value="xp_boost">XP Multiplier</option>
                                <option value="rtp_boost">RTP Boost (Global)</option>
                                <option value="deposit_bonus">Deposit Bonus</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="small text-muted">Multiplier (x)</label>
                            <input type="number" step="0.1" name="multiplier" class="form-control bg-dark text-white border-secondary" value="1.5">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Target Island (Optional)</label>
                        <select name="target_island_id" class="form-select bg-dark text-white border-secondary">
                            <option value="">-- All Islands --</option>
                            <?php foreach($islands as $isl): ?><option value="<?= $isl['id'] ?>"><?= $isl['name'] ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small text-muted">Start</label>
                            <input type="datetime-local" name="start_time" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-6">
                            <label class="small text-muted">End</label>
                            <input type="datetime-local" name="end_time" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                    </div>
                    <button class="btn btn-warning w-100 fw-bold text-dark">LAUNCH CAMPAIGN</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header border-secondary text-white">ACTIVE & UPCOMING CAMPAIGNS</div>
            <div class="card-body p-0">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead>
                        <tr class="text-secondary text-uppercase text-xs">
                            <th>Event</th>
                            <th>Bonus</th>
                            <th>Target</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($events as $e): 
                            $now = time();
                            $start = strtotime($e['start_time']);
                            $end = strtotime($e['end_time']);
                            $isActive = $now >= $start && $now <= $end;
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-white"><?= htmlspecialchars($e['title']) ?></div>
                                <div class="small text-muted"><?= strtoupper(str_replace('_', ' ', $e['type'])) ?></div>
                            </td>
                            <td class="text-info fw-bold">x<?= $e['multiplier'] ?></td>
                            <td><?= $e['island_name'] ?? 'Global' ?></td>
                            <td class="small text-muted">
                                <div><?= date('M d H:i', $start) ?></div>
                                <div><?= date('M d H:i', $end) ?></div>
                            </td>
                            <td>
                                <?php if($isActive): ?>
                                    <span class="badge bg-success animate-pulse">LIVE</span>
                                <?php elseif($now < $start): ?>
                                    <span class="badge bg-warning text-dark">UPCOMING</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">ENDED</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <form method="POST" onsubmit="return confirm('Delete event?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
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