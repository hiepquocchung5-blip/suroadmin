<?php
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');
$pageTitle = "Financial Queue";
require_once ADMIN_BASE_PATH . '/layout/main.php';
requireRole(['GOD', 'FINANCE']);

// Handle Actions (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $txId = (int)$_POST['tx_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    $note = cleanInput($_POST['note']);
    $adminId = $_SESSION['admin_id'];

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending' FOR UPDATE");
        $stmt->execute([$txId]);
        $tx = $stmt->fetch();

        if ($tx) {
            $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
            
            if ($newStatus === 'approved' && $tx['type'] === 'deposit') {
                $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$tx['amount'], $tx['user_id']]);
            } elseif ($newStatus === 'rejected' && $tx['type'] === 'withdraw') {
                $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$tx['amount'], $tx['user_id']]);
            }

            $stmtUpd = $pdo->prepare("UPDATE transactions SET status = ?, processed_by_admin_id = ?, admin_note = ?, updated_at = NOW() WHERE id = ?");
            $stmtUpd->execute([$newStatus, $adminId, $note, $txId]);
            $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'transactions')")->execute([$adminId, ucfirst($action) . " Transaction #$txId"]);

            $pdo->commit();
            $success = "Transaction #$txId processed successfully.";
        } else { $error = "Transaction #$txId not found or already processed."; }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error processing transaction: " . $e->getMessage();
    }
}

// Fetch Pending
$typeFilter = $_GET['type'] ?? 'all';
$sql = "SELECT t.*, u.username, u.phone, u.balance as current_balance, u.level, pm.provider_name 
        FROM transactions t JOIN users u ON t.user_id = u.id LEFT JOIN payment_methods pm ON t.payment_method_id = pm.id
        WHERE t.status = 'pending'";
$params = [];
if ($typeFilter !== 'all') { $sql .= " AND t.type = ?"; $params[] = $typeFilter; }
$sql .= " ORDER BY t.created_at ASC";
$pending = $pdo->prepare($sql);
$pending->execute($params);
$pending = $pending->fetchAll();

// API Path logic for proofs
$apiBase = defined('API_BASE_URL') ? rtrim(API_BASE_URL, '/') : '';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-black text-white italic tracking-widest m-0"><i class="bi bi-bank"></i> INBOX QUEUE</h2>
    <div class="btn-group shadow-sm">
        <a href="?route=finance/queue&type=all" class="btn <?= $typeFilter=='all'?'btn-info text-dark fw-bold':'btn-dark border-secondary text-muted' ?>">ALL</a>
        <a href="?route=finance/queue&type=deposit" class="btn <?= $typeFilter=='deposit'?'btn-success fw-bold':'btn-dark border-secondary text-muted' ?>">DEPOSITS</a>
        <a href="?route=finance/queue&type=withdraw" class="btn <?= $typeFilter=='withdraw'?'btn-danger fw-bold':'btn-dark border-secondary text-muted' ?>">PAYOUTS</a>
    </div>
</div>

<?php if(isset($success)): ?><div class="alert bg-success bg-opacity-20 text-success border border-success fw-bold shadow-sm animate-pulse"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert bg-danger bg-opacity-20 text-danger border border-danger fw-bold shadow-sm"><i class="bi bi-x-circle-fill me-2"></i><?= $error ?></div><?php endif; ?>

<div class="row g-3">
    <?php if(empty($pending)): ?>
        <div class="col-12">
            <div class="glass-card text-center py-5 text-muted border-dashed border-secondary">
                <i class="bi bi-cup-hot display-1 opacity-25 mb-3 d-block"></i>
                <h4 class="fw-bold text-white">Queue is Empty</h4>
                <p>All financial requests have been processed.</p>
            </div>
        </div>
    <?php else: foreach($pending as $row): 
        $isDep = $row['type'] === 'deposit';
        $color = $isDep ? 'success' : 'danger';
        $proofUrl = $row['proof_image'] ? $apiBase . '/' . ltrim($row['proof_image'], '/') : null;
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="glass-card h-100 p-0 overflow-hidden border-<?= $color ?> border-opacity-50 shadow-[0_5px_20px_rgba(0,0,0,0.5)] transition-transform hover:-translate-y-1">
            
            <div class="bg-<?= $color ?> bg-opacity-10 p-3 border-b border-<?= $color ?> border-opacity-30 d-flex justify-content-between align-items-center">
                <span class="badge bg-<?= $color ?> text-<?= $isDep ? 'dark' : 'white' ?> fw-black tracking-widest px-3 py-1 rounded-pill">
                    <i class="bi bi-arrow-<?= $isDep ? 'down' : 'up' ?>-circle me-1"></i> <?= strtoupper($row['type']) ?>
                </span>
                <span class="text-muted font-mono text-[10px]">TX #<?= $row['id'] ?></span>
            </div>

            <div class="p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="fw-black text-white m-0"><?= htmlspecialchars($row['username']) ?></h5>
                        <div class="text-muted font-mono text-xs mt-1"><i class="bi bi-phone"></i> <?= htmlspecialchars($row['phone']) ?></div>
                    </div>
                    <?php if($proofUrl): ?>
                        <div class="w-12 h-12 rounded border border-secondary overflow-hidden cursor-pointer hover:border-info transition-colors" onclick="viewProof('<?= $proofUrl ?>')">
                            <img src="<?= $proofUrl ?>" class="w-100 h-100 object-fit-cover" alt="Proof">
                        </div>
                    <?php else: ?>
                        <div class="w-12 h-12 rounded border border-dashed border-secondary d-flex justify-content-center align-items-center text-muted">
                            <i class="bi bi-image-alt"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="bg-black bg-opacity-40 p-3 rounded-xl border border-white border-opacity-5 mb-4 text-center shadow-inner">
                    <span class="text-muted text-[9px] fw-bold text-uppercase tracking-widest d-block mb-1">REQUEST AMOUNT</span>
                    <div class="text-<?= $color ?> font-mono fw-black fs-2 lh-1 drop-shadow-md">
                        <?= $isDep ? '+' : '-' ?><?= number_format($row['amount']) ?>
                    </div>
                </div>

                <div class="d-flex justify-content-between text-[10px] text-gray-400 font-mono mb-4 px-1 border-b border-white border-opacity-5 pb-3">
                    <span><i class="bi bi-bank"></i> <?= htmlspecialchars($row['provider_name'] ?? 'System') ?></span>
                    <span class="text-warning">REF: <?= $row['transaction_last_digits'] ? "*".$row['transaction_last_digits'] : '---' ?></span>
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <button class="btn btn-dark w-100 fw-bold border-danger text-danger hover:bg-danger hover:text-white transition-colors" onclick="openProcessModal(<?= $row['id'] ?>, 'reject', '<?= $row['type'] ?>', <?= $row['amount'] ?>)">
                            <i class="bi bi-x-lg"></i> REJECT
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-success w-100 fw-black shadow-[0_0_15px_rgba(34,197,94,0.4)] hover:scale-105 active:scale-95 transition-all" onclick="openProcessModal(<?= $row['id'] ?>, 'approve', '<?= $row['type'] ?>', <?= $row['amount'] ?>)">
                            <i class="bi bi-check2-circle"></i> APPROVE
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<!-- Process Modal -->
<div class="modal fade" id="processModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content glass-card border-secondary shadow-2xl">
            <div class="modal-body p-5 text-center relative overflow-hidden">
                <input type="hidden" name="tx_id" id="modalTxId">
                <input type="hidden" name="action" id="modalAction">
                
                <h1 id="modalIcon" class="display-1 mb-3"></h1>
                <h3 id="modalTitle" class="fw-black italic tracking-widest uppercase mb-2"></h3>
                <p id="modalDesc" class="text-gray-400 small mb-4"></p>
                
                <div class="text-start mb-4">
                    <label class="form-label text-muted small fw-bold tracking-widest text-uppercase">Agent Note (Mandatory for Rejects)</label>
                    <textarea name="note" class="form-control bg-black text-white border-secondary rounded-xl p-3 font-mono text-sm" rows="2" placeholder="Processing details..."></textarea>
                </div>
                
                <div class="row g-2">
                    <div class="col-6">
                        <button type="button" class="btn btn-dark w-100 fw-bold py-3 rounded-pill" data-bs-dismiss="modal">CANCEL</button>
                    </div>
                    <div class="col-6">
                        <button type="submit" class="btn w-100 fw-black py-3 rounded-pill shadow-lg" id="modalSubmitBtn">CONFIRM</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Proof Modal -->
<div class="modal fade" id="proofModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0 shadow-none">
            <div class="modal-body p-0 text-center relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 z-10 bg-black p-2 rounded-circle" data-bs-dismiss="modal"></button>
                <img id="proofImgDisplay" src="" class="img-fluid rounded-2xl border border-secondary shadow-[0_0_50px_rgba(255,255,255,0.2)]" style="max-height: 85vh;">
            </div>
        </div>
    </div>
</div>

<script>
function openProcessModal(id, action, type, amount) {
    document.getElementById('modalTxId').value = id;
    document.getElementById('modalAction').value = action;
    const btn = document.getElementById('modalSubmitBtn');
    const title = document.getElementById('modalTitle');
    const desc = document.getElementById('modalDesc');
    const icon = document.getElementById('modalIcon');
    const amountStr = new Intl.NumberFormat().format(amount) + ' MMK';

    if (action === 'approve') {
        btn.className = 'btn btn-success w-100 fw-black py-3 rounded-pill shadow-[0_0_15px_rgba(34,197,94,0.5)]';
        btn.innerText = 'EXECUTE APPROVAL';
        title.innerText = `Approve ${type}?`;
        title.className = 'text-success fw-black italic tracking-widest uppercase mb-2';
        desc.innerHTML = `Authorize transferring <b class="text-white font-mono">${amountStr}</b> for TX #${id}.`;
        icon.innerHTML = '<i class="bi bi-shield-check text-success drop-shadow-[0_0_20px_lime]"></i>';
    } else {
        btn.className = 'btn btn-danger w-100 fw-black py-3 rounded-pill shadow-[0_0_15px_rgba(239,68,68,0.5)]';
        btn.innerText = 'EXECUTE REJECTION';
        title.innerText = `Reject ${type}?`;
        title.className = 'text-danger fw-black italic tracking-widest uppercase mb-2';
        if(type === 'withdraw') desc.innerHTML = `Funds (<b class="text-white font-mono">${amountStr}</b>) will be returned to the player's wallet.`;
        else desc.innerHTML = `Deposit request #${id} will be denied.`;
        icon.innerHTML = '<i class="bi bi-shield-x text-danger drop-shadow-[0_0_20px_red]"></i>';
    }
    new bootstrap.Modal(document.getElementById('processModal')).show();
}

function viewProof(src) {
    document.getElementById('proofImgDisplay').src = src;
    new bootstrap.Modal(document.getElementById('proofModal')).show();
}
</script>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>