<?php
$pageTitle = "Financial Queue";
require_once '../../layout/main.php';

// Handle Actions (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole(['GOD', 'FINANCE']);
    
    $txId = (int)$_POST['tx_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    $note = cleanInput($_POST['note']);
    $adminId = $_SESSION['admin_id'];

    try {
        $pdo->beginTransaction();

        // Get TX details with row lock
        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending' FOR UPDATE");
        $stmt->execute([$txId]);
        $tx = $stmt->fetch();

        if ($tx) {
            $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
            
            // Balance Logic
            if ($newStatus === 'approved' && $tx['type'] === 'deposit') {
                $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$tx['amount'], $tx['user_id']]);
            } elseif ($newStatus === 'rejected' && $tx['type'] === 'withdraw') {
                $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$tx['amount'], $tx['user_id']]);
            }

            // Update Transaction
            $stmtUpd = $pdo->prepare("UPDATE transactions SET status = ?, processed_by_admin_id = ?, admin_note = ?, updated_at = NOW() WHERE id = ?");
            $stmtUpd->execute([$newStatus, $adminId, $note, $txId]);

            // Audit
            $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'transactions')")
                ->execute([$adminId, ucfirst($action) . " Transaction #$txId"]);

            $pdo->commit();
            $success = "Transaction #$txId processed successfully.";
        } else {
            $error = "Transaction #$txId not found or already processed.";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error processing transaction: " . $e->getMessage();
    }
}

// --- FILTERS & SEARCH ---
$typeFilter = $_GET['type'] ?? 'all';
$searchQuery = $_GET['q'] ?? '';

$sql = "SELECT t.*, u.username, u.phone, u.balance as current_balance, u.level, pm.provider_name 
        FROM transactions t 
        JOIN users u ON t.user_id = u.id 
        LEFT JOIN payment_methods pm ON t.payment_method_id = pm.id
        WHERE t.status = 'pending'";

$params = [];

if ($typeFilter !== 'all') {
    $sql .= " AND t.type = ?";
    $params[] = $typeFilter;
}

if ($searchQuery) {
    $sql .= " AND (u.username LIKE ? OR u.phone LIKE ? OR t.id = ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = $searchQuery;
}

$sql .= " ORDER BY t.created_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pending = $stmt->fetchAll();

// Stats
$totalPendingDeposit = 0;
$totalPendingWithdraw = 0;
foreach($pending as $p) {
    if($p['type'] == 'deposit') $totalPendingDeposit += $p['amount'];
    if($p['type'] == 'withdraw') $totalPendingWithdraw += $p['amount'];
}
?>

<!-- ALERTS -->
<?php if(isset($success)): ?><div class="alert alert-success alert-dismissible fade show"><?= $success ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert alert-danger alert-dismissible fade show"><?= $error ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- SUMMARY BAR -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card bg-dark border-success text-white h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-success mb-0">PENDING DEPOSITS</h6>
                    <small class="text-muted">Inbound Cash</small>
                </div>
                <h3 class="fw-bold mb-0 text-success">+<?= number_format($totalPendingDeposit) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-dark border-danger text-white h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-danger mb-0">PENDING WITHDRAWALS</h6>
                    <small class="text-muted">Outbound Cash</small>
                </div>
                <h3 class="fw-bold mb-0 text-danger">-<?= number_format($totalPendingWithdraw) ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- CONTROLS -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto">
                <select name="type" class="form-select bg-dark text-white border-secondary form-select-sm" onchange="this.form.submit()">
                    <option value="all" <?= $typeFilter == 'all' ? 'selected' : '' ?>>All Types</option>
                    <option value="deposit" <?= $typeFilter == 'deposit' ? 'selected' : '' ?>>Deposits</option>
                    <option value="withdraw" <?= $typeFilter == 'withdraw' ? 'selected' : '' ?>>Withdrawals</option>
                </select>
            </div>
            <div class="col-auto flex-grow-1">
                <input type="text" name="q" class="form-control bg-dark text-white border-secondary form-select-sm" placeholder="Search User ID, Phone, or TX ID..." value="<?= htmlspecialchars($searchQuery) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-info fw-bold">SEARCH</button>
                <a href="queue.php" class="btn btn-sm btn-outline-secondary">RESET</a>
            </div>
        </form>
    </div>
</div>

<!-- QUEUE TABLE -->
<div class="card">
    <div class="card-header bg-transparent border-secondary d-flex justify-content-between">
        <h5 class="mb-0 text-white">TRANSACTION QUEUE (<?= count($pending) ?>)</h5>
        <button class="btn btn-sm btn-outline-light" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr class="text-secondary text-uppercase text-xs" style="font-size: 0.8rem;">
                        <th>TX ID</th>
                        <th>User Profile</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Provider Details</th>
                        <th>Proof</th>
                        <th>Time</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($pending)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No pending transactions.</td></tr>
                    <?php else: foreach($pending as $row): ?>
                        <tr>
                            <td><span class="text-muted">#</span><?= $row['id'] ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div>
                                        <div class="fw-bold text-white"><?= htmlspecialchars($row['username']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($row['phone']) ?></div>
                                        <div class="badge bg-secondary text-light" style="font-size: 0.65rem;">Lvl <?= $row['level'] ?> • Bal: <?= number_format($row['current_balance']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if($row['type'] == 'deposit'): ?>
                                    <span class="badge bg-success bg-opacity-25 text-success border border-success">DEPOSIT</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-25 text-danger border border-danger">WITHDRAW</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold font-monospace fs-6 <?= $row['type'] == 'deposit' ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($row['amount']) ?>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($row['provider_name'] ?? 'System') ?></div>
                                <?php if($row['transaction_last_digits']): ?>
                                    <div class="small text-info font-monospace">Ref: ...<?= htmlspecialchars($row['transaction_last_digits']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($row['proof_image']): ?>
                                    <button class="btn btn-sm btn-outline-info" onclick="viewProof('<?= htmlspecialchars($row['proof_image']) ?>')">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted text-xs">No Proof</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small">
                                <div><?= date('H:i', strtotime($row['created_at'])) ?></div>
                                <div style="font-size: 0.7rem;"><?= date('M d', strtotime($row['created_at'])) ?></div>
                            </td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-success" type="button" onclick="openProcessModal(<?= $row['id'] ?>, 'approve', '<?= $row['type'] ?>', <?= $row['amount'] ?>)">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" type="button" onclick="openProcessModal(<?= $row['id'] ?>, 'reject', '<?= $row['type'] ?>', <?= $row['amount'] ?>)">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Process Modal -->
<div class="modal fade" id="processModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content bg-dark border-secondary text-white">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Confirm Action</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="tx_id" id="modalTxId">
                <input type="hidden" name="action" id="modalAction">
                
                <div class="text-center mb-4">
                    <h1 id="modalIcon" class="display-4 mb-2"></h1>
                    <h4 id="modalTitle"></h4>
                    <p id="modalDesc" class="text-muted"></p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-secondary small">ADMIN NOTE</label>
                    <textarea name="note" class="form-control bg-black text-white border-secondary" rows="2" placeholder="Reason for approval/rejection..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn" id="modalSubmitBtn">Confirm</button>
            </div>
        </form>
    </div>
</div>

<!-- Proof Modal -->
<div class="modal fade" id="proofModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary py-2">
                <h6 class="modal-title text-white">Payment Proof</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-black d-flex justify-content-center">
                <img id="proofImgDisplay" src="" class="img-fluid" style="max-height: 80vh;">
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT LOGIC -->
<script>
function openProcessModal(id, action, type, amount) {
    // 1. Set Hidden Input Values
    document.getElementById('modalTxId').value = id;
    document.getElementById('modalAction').value = action;
    
    // 2. UI Elements
    const btn = document.getElementById('modalSubmitBtn');
    const title = document.getElementById('modalTitle');
    const desc = document.getElementById('modalDesc');
    const icon = document.getElementById('modalIcon');
    
    const amountStr = new Intl.NumberFormat().format(amount) + ' MMK';

    // 3. Configure Modal based on Action
    if (action === 'approve') {
        btn.className = 'btn btn-success w-100 fw-bold';
        btn.innerText = 'CONFIRM APPROVAL';
        title.innerText = `Approve ${type.toUpperCase()}?`;
        title.className = 'text-success fw-bold';
        desc.innerHTML = `Transaction #${id} for <b class="text-white">${amountStr}</b> will be processed.`;
        icon.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
    } else {
        btn.className = 'btn btn-danger w-100 fw-bold';
        btn.innerText = 'CONFIRM REJECTION';
        title.innerText = `Reject ${type.toUpperCase()}?`;
        title.className = 'text-danger fw-bold';
        if(type === 'withdraw') {
            desc.innerHTML = `Funds (<b class="text-white">${amountStr}</b>) will be refunded to user balance.`;
        } else {
            desc.innerHTML = `Transaction #${id} will be marked as rejected.`;
        }
        icon.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i>';
    }
    
    // 4. Show Modal using Bootstrap
    const modalEl = document.getElementById('processModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
}

function viewProof(src) {
    document.getElementById('proofImgDisplay').src = src;
    new bootstrap.Modal(document.getElementById('proofModal')).show();
}
</script>

<!-- INCLUDE FOOTER (Loads Bootstrap JS) -->
<?php require_once '../../layout/footer.php'; ?>