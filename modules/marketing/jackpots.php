<?php
$pageTitle = "Jackpot Control";
require_once '../../layout/main.php';
requireRole(['GOD', 'FINANCE']);

// Handle Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole(['GOD']);
    $action = $_POST['action'];
    $id = (int)$_POST['id'];

    if ($action === 'update_seed') {
        $amount = (float)$_POST['amount'];
        $pdo->prepare("UPDATE global_jackpots SET current_amount = ? WHERE id = ?")->execute([$amount, $id]);
        $success = "Jackpot amount updated manually.";
    } 
    elseif ($action === 'update_rate') {
        $rate = (float)$_POST['rate'];
        $pdo->prepare("UPDATE global_jackpots SET contribution_rate = ? WHERE id = ?")->execute([$rate, $id]);
        $success = "Contribution rate updated.";
    }
}

$jackpots = $pdo->query("SELECT * FROM global_jackpots")->fetchAll();
?>

<?php if(isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<div class="row">
    <?php foreach($jackpots as $jp): ?>
    <div class="col-md-6">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark fw-bold d-flex justify-content-between">
                <span><?= htmlspecialchars($jp['name']) ?></span>
                <i class="bi bi-trophy-fill"></i>
            </div>
            <div class="card-body text-center py-5">
                <h6 class="text-muted">CURRENT POOL</h6>
                <h1 class="display-4 fw-black text-warning"><?= number_format($jp['current_amount']) ?></h1>
                <small class="text-muted">MMK</small>
            </div>
            <div class="card-footer border-warning bg-dark">
                <form method="POST" class="row g-2 align-items-center">
                    <input type="hidden" name="id" value="<?= $jp['id'] ?>">
                    
                    <div class="col-md-6">
                        <label class="small text-muted">Manual Override Amount</label>
                        <div class="input-group input-group-sm">
                            <input type="number" name="amount" class="form-control bg-black text-white border-secondary" value="<?= $jp['current_amount'] ?>">
                            <button name="action" value="update_seed" class="btn btn-outline-warning">SET</button>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="small text-muted">Contribution Rate (0.01 = 1%)</label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.0001" name="rate" class="form-control bg-black text-white border-secondary" value="<?= $jp['contribution_rate'] ?>">
                            <button name="action" value="update_rate" class="btn btn-outline-info">SET</button>
                        </div>
                    </div>
                </form>
                
                <div class="mt-3 small text-white border-top border-secondary pt-2">
                    <strong>Last Winner:</strong> <?= $jp['last_won_by'] ?? 'None yet' ?> 
                    (<?= $jp['last_won_at'] ? date('M d, H:i', strtotime($jp['last_won_at'])) : '-' ?>)
                    <span class="text-success float-end"><?= $jp['last_won_amount'] ? number_format($jp['last_won_amount']) . ' MMK' : '' ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once '../../layout/footer.php'; ?>