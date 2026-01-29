<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Only Finance/God can export data
requireRole(['GOD', 'FINANCE']);

// Check if export requested
if (isset($_POST['export'])) {
    $type = $_POST['type'] ?? 'all';
    $start = $_POST['start_date'];
    $end = $_POST['end_date'];
    
    // Build Filename
    $filename = "suropara_tx_" . date('Ymd') . ".csv";
    
    // Headers for download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Column Headers
    fputcsv($output, ['ID', 'User Phone', 'Type', 'Amount', 'Provider', 'Status', 'Date', 'Admin Note']);
    
    // Build Query
    $sql = "SELECT t.id, u.phone, t.type, t.amount, pm.provider_name, t.status, t.created_at, t.admin_note 
            FROM transactions t
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN payment_methods pm ON t.payment_method_id = pm.id
            WHERE t.created_at BETWEEN ? AND ?";
    
    $params = [$start . ' 00:00:00', $end . ' 23:59:59'];
    
    if ($type !== 'all') {
        $sql .= " AND t.type = ?";
        $params[] = $type;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Stream rows to CSV
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    
    // Log the export action
    $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'transactions')")
        ->execute([$_SESSION['admin_id'], "Exported CSV: $type ($start to $end)"]);
    
    exit; // Stop execution to serve file
}

$pageTitle = "Export Financial Data";
require_once '../../layout/main.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-success">
            <div class="card-header bg-success bg-opacity-10 text-success fw-bold">
                <i class="bi bi-file-earmark-spreadsheet"></i> EXPORT TRANSACTIONS
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="small text-muted">Transaction Type</label>
                        <select name="type" class="form-select bg-dark text-white border-secondary">
                            <option value="all">All Transactions</option>
                            <option value="deposit">Deposits Only</option>
                            <option value="withdraw">Withdrawals Only</option>
                            <option value="bonus">Bonuses</option>
                        </select>
                    </div>
                    
                    <div class="row g-2 mb-4">
                        <div class="col-6">
                            <label class="small text-muted">Start Date</label>
                            <input type="date" name="start_date" class="form-control bg-dark text-white border-secondary" value="<?= date('Y-m-01') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="small text-muted">End Date</label>
                            <input type="date" name="end_date" class="form-control bg-dark text-white border-secondary" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="export" class="btn btn-success w-100 fw-bold">
                        <i class="bi bi-download me-2"></i> DOWNLOAD CSV
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../layout/footer.php'; ?>