<?php
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');
$pageTitle = "Withdrawal Limits";
requireRole(['GOD', 'FINANCE']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'create') {
        $deposit = (float)$_POST['deposit_amount'];
        $withdraw = (float)$_POST['max_withdraw'];
        
        $stmtCheck = $pdo->prepare("SELECT id FROM withdrawal_limits WHERE deposit_amount = ?");
        $stmtCheck->execute([$deposit]);
        
        if ($stmtCheck->rowCount() > 0) {
            $error = "A limit for this deposit amount already exists.";
        } else {
            $pdo->prepare("INSERT INTO withdrawal_limits (deposit_amount, max_withdraw) VALUES (?, ?)")->execute([$deposit, $withdraw]);
            $success = "New tier established.";
        }
    } 
    elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM withdrawal_limits WHERE id = ?")->execute([$id]);
        $success = "Tier removed.";
    }
}

$tiers = $pdo->query("SELECT * FROM withdrawal_limits ORDER BY deposit_amount ASC")->fetchAll();
require_once ADMIN_BASE_PATH . '/layout/main.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="text-white fw-black mb-0 italic tracking-widest"><i class="bi bi-shield-check text-warning"></i> PAYOUT TIERS</h3>
</div>

<?php if(isset($success)): ?><div class="alert bg-success bg-opacity-20 text-success border border-success fw-bold shadow-sm"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert bg-danger bg-opacity-20 text-danger border border-danger fw-bold shadow-sm"><i class="bi bi-x-circle-fill me-2"></i><?= $error ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="glass-card p-0 border-warning border-opacity-50 h-100">
            <div class="bg-warning bg-opacity-20 text-warning fw-black p-3 border-b border-warning border-opacity-30 tracking-widest italic">
                <i class="bi bi-plus-lg me-1"></i> ADD NEW RULE
            </div>
            <div class="card-body p-4 bg-black bg-opacity-60">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-3">
                        <label class="text-gray-400 small fw-bold text-uppercase tracking-widest mb-1">Lifetime Deposit</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-dark border-secondary text-success font-mono">></span>
                            <input type="number" name="deposit_amount" class="form-control bg-dark text-white border-secondary font-mono fw-bold" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="text-gray-400 small fw-bold text-uppercase tracking-widest mb-1">Max Withdraw Allow</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-dark border-secondary text-warning font-mono">Max</span>
                            <input type="number" name="max_withdraw" class="form-control bg-dark text-white border-secondary font-mono fw-bold" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-warning w-100 fw-black py-3 shadow-[0_0_15px_rgba(234,179,8,0.4)] text-dark hover:scale-105 transition-transform">CREATE TIER</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="glass-card p-0 border-secondary overflow-hidden h-100">
            <div class="card-header bg-black bg-opacity-50 border-b border-white border-opacity-10 text-white fw-bold tracking-widest italic p-3">
                ACTIVE PROGRESSION RULES
            </div>
            <div class="table-responsive bg-black bg-opacity-40">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead>
                        <tr class="text-gray-500 text-uppercase text-[10px] tracking-widest border-b border-white border-opacity-10">
                            <th class="ps-4 py-3 text-end w-1/3">Lifetime Deposit ></th>
                            <th class="text-center"><i class="bi bi-arrow-right"></i></th>
                            <th class="text-start w-1/3">Max Withdrawal</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm font-mono">
                        <?php foreach($tiers as $t): ?>
                        <tr class="border-b border-white border-opacity-5 hover:bg-white/5 transition-colors">
                            <td class="ps-4 text-end fw-bold text-success fs-6">
                                <?= number_format($t['deposit_amount']) ?> <span class="text-[10px] text-gray-500">MMK</span>
                            </td>
                            <td class="text-center text-gray-600"><i class="bi bi-arrow-right"></i></td>
                            <td class="text-start fw-black text-warning fs-6">
                                <?= number_format($t['max_withdraw']) ?> <span class="text-[10px] text-gray-500">MMK</span>
                            </td>
                            <td class="text-end pe-4">
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this security rule?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger border-0 hover:bg-danger hover:text-white rounded-circle"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; if(empty($tiers)) echo '<tr><td colspan="4" class="text-center py-5 text-red-500 font-sans fw-bold">CRITICAL: No limits defined. Users can withdraw infinitely.</td></tr>'; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>