<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Only GOD can touch money manually
requireRole(['GOD']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)$_POST['user_id'];
    $type = $_POST['type']; // 'credit' or 'debit'
    $amount = (float)$_POST['amount'];
    $adminId = $_SESSION['admin_id'];

    if ($amount <= 0) die("Invalid amount");

    try {
        $pdo->beginTransaction();

        // 1. Update Balance
        if ($type === 'credit') {
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $userId]);
            $note = "Admin Credit (Bonus/Refund)";
            $txType = "bonus";
        } else {
            $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$amount, $userId]);
            $note = "Admin Debit (Correction)";
            $txType = "withdraw"; // Or a specific 'correction' type if added to ENUM
        }

        // 2. Create Transaction Record
        $sql = "INSERT INTO transactions (user_id, type, amount, status, processed_by_admin_id, admin_note) VALUES (?, ?, ?, 'approved', ?, ?)";
        $pdo->prepare($sql)->execute([$userId, $txType, $amount, $adminId, $note]);

        // 3. Audit Log
        $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'users')")
            ->execute([$adminId, "Manual $type of $amount to User #$userId"]);

        $pdo->commit();

        header("Location: details.php?id=$userId&success=BalanceUpdated");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}
?>