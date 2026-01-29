<?php
$pageTitle = "Withdrawal Limit Configuration";
require_once '../../layout/main.php';
requireRole(['GOD', 'FINANCE']);

// --- ACTION HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'create') {
        $deposit = (float)$_POST['deposit_amount'];
        $withdraw = (float)$_POST['max_withdraw'];
        
        // Prevent duplicates for same deposit amount
        $stmtCheck = $pdo->prepare("SELECT id FROM withdrawal_limits WHERE deposit_amount = ?");
        $stmtCheck->execute([$deposit]);
        
        if ($stmtCheck->rowCount() > 0) {
            $error = "A limit for this deposit amount already exists.";
        } else {
            $sql = "INSERT INTO withdrawal_limits (deposit_amount, max_withdraw) VALUES (?, ?)";
            $pdo->prepare($sql)->execute([$deposit, $withdraw]);
            $success = "New tier added.";
            
            // Audit
            $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'withdrawal_limits')")
                ->execute([$_SESSION['admin_id'], "Added Limit Tier: Dep $deposit -> Max $withdraw"]);
        }
    } 
    elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM withdrawal_limits WHERE id = ?")->execute([$id]);
        $success = "Tier deleted.";
    }
    elseif ($action === 'update') {
        $id = (int)$_POST['id'];
        $deposit = (float)$_POST['deposit_amount'];
        $withdraw = (float)$_POST['max_withdraw'];
        
        $pdo->prepare("UPDATE withdrawal_limits SET deposit_amount = ?, max_withdraw = ? WHERE id = ?")
            ->execute([$deposit, $withdraw, $id]);
        $success = "Tier updated.";
    }
}

// --- FETCH DATA ---
$tiers = $pdo->query("SELECT * FROM withdrawal_limits ORDER BY deposit_amount ASC")->fetchAll();
?>

<?php if(isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<div class="row">
    <!-- ADD FORM -->
    <div class="col-md-4">
        <div class="card mb-4 border-info">
            <div class="card-header bg-dark border-info text-info fw-bold">ADD NEW TIER</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-3">
                        <label class="text-muted small">Total Lifetime Deposit (MMK)</label>
                        <input type="number" name="deposit_amount" class="form-control bg-black text-white border-secondary" placeholder="e.g. 50000" required>
                        <div class="form-text text-muted">Threshold to unlock this tier.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="text-muted small">Max Withdrawal Limit (MMK)</label>
                        <input type="number" name="max_withdraw" class="form-control bg-black text-white border-secondary" placeholder="e.g. 150000" required>
                    </div>
                    
                    <button type="submit" class="btn btn-info w-100 fw-bold text-dark">ADD RULE</button>
                </form>
            </div>
            <div class="card-footer bg-dark border-secondary text-muted small">
                <i class="bi bi-info-circle"></i> Example: If a user has deposited <strong>10,000</strong> total, they can withdraw up to <strong>30,000</strong>.
            </div>
        </div>
    </div>

    <!-- LIST -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header border-secondary text-white">ACTIVE LIMIT TIERS</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0 align-middle">
                        <thead>
                            <tr class="text-secondary text-uppercase text-xs">
                                <th class="text-end">Lifetime Deposit ></th>
                                <th class="text-center"><i class="bi bi-arrow-right"></i></th>
                                <th class="text-start">Max Withdraw</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($tiers)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">No limits defined. Users might have unrestricted withdrawals!</td></tr>
                            <?php else: foreach($tiers as $t): ?>
                            <tr>
                                <td class="text-end fw-bold font-monospace text-success">
                                    <?= number_format($t['deposit_amount']) ?>
                                </td>
                                <td class="text-center text-muted">allows</td>
                                <td class="text-start fw-bold font-monospace text-warning">
                                    <?= number_format($t['max_withdraw']) ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-light me-1" onclick='openEditModal(<?= json_encode($t) ?>)' title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this tier?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
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

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content bg-dark border-secondary text-white">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit Limit Tier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editId">
                
                <div class="mb-3">
                    <label class="text-muted small">Lifetime Deposit</label>
                    <input type="number" name="deposit_amount" id="editDeposit" class="form-control bg-black text-white border-secondary" required>
                </div>
                
                <div class="mb-3">
                    <label class="text-muted small">Max Withdraw</label>
                    <input type="number" name="max_withdraw" id="editWithdraw" class="form-control bg-black text-white border-secondary" required>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-info fw-bold">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(tier) {
    document.getElementById('editId').value = tier.id;
    document.getElementById('editDeposit').value = tier.deposit_amount;
    document.getElementById('editWithdraw').value = tier.max_withdraw;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once '../../layout/footer.php'; ?>