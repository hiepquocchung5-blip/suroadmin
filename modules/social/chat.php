<?php
$pageTitle = "Live Chat Monitor";
require_once '../../layout/main.php';
requireRole(['GOD', 'FINANCE']);

// --- ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Delete Message
    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $pdo->prepare("DELETE FROM chat_messages WHERE id = ?")->execute([$id]);
        $msg = "Message #$id removed.";
    }
    
    // 2. Purge User History
    if (isset($_POST['purge_user_id'])) {
        $uid = (int)$_POST['purge_user_id'];
        $pdo->prepare("DELETE FROM chat_messages WHERE user_id = ?")->execute([$uid]);
        $msg = "All messages from User #$uid purged.";
        // Audit
        $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'chat')")
            ->execute([$_SESSION['admin_id'], "Purged Chat User #$uid"]);
    }

    // 3. Mute/Unmute User (New)
    if (isset($_POST['toggle_mute_id'])) {
        $uid = (int)$_POST['toggle_mute_id'];
        $current = (int)$_POST['current_mute_status'];
        $newStatus = $current ? 0 : 1;
        
        $pdo->prepare("UPDATE users SET is_muted = ? WHERE id = ?")->execute([$newStatus, $uid]);
        $msg = $newStatus ? "User #$uid Muted." : "User #$uid Unmuted.";
    }

    // 4. Pin/Unpin Message (New)
    if (isset($_POST['toggle_pin_id'])) {
        $mid = (int)$_POST['toggle_pin_id'];
        $current = (int)$_POST['current_pin_status'];
        $newStatus = $current ? 0 : 1;
        
        $pdo->prepare("UPDATE chat_messages SET is_pinned = ? WHERE id = ?")->execute([$newStatus, $mid]);
        $msg = $newStatus ? "Message pinned." : "Message unpinned.";
    }
    
    // 5. Broadcast
    if (isset($_POST['system_msg'])) {
        $text = htmlspecialchars($_POST['system_msg']);
        $pdo->prepare("INSERT INTO chat_messages (type, message, is_pinned) VALUES ('system', ?, 0)")->execute([$text]);
        $msg = "System announcement posted.";
    }
}

// --- FETCH LOGS ---
// Added is_muted and is_pinned to query
$chats = $pdo->query("
    SELECT m.*, u.username, u.phone, u.is_muted
    FROM chat_messages m 
    LEFT JOIN users u ON m.user_id = u.id 
    ORDER BY m.is_pinned DESC, m.created_at DESC 
    LIMIT 100
")->fetchAll();
?>

<?php if(isset($msg)): ?><div class="alert alert-success small shadow-sm border-0 border-start border-success border-4"><?= $msg ?></div><?php endif; ?>

<div class="row">
    <!-- TOOLS -->
    <div class="col-md-4">
        <div class="card mb-3 border-info shadow-sm">
            <div class="card-header bg-info bg-opacity-10 text-info fw-bold"><i class="bi bi-megaphone-fill me-2"></i> BROADCAST</div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-2">
                        <textarea name="system_msg" class="form-control bg-black text-white border-secondary" rows="3" placeholder="System Announcement..." required></textarea>
                    </div>
                    <button class="btn btn-info w-100 fw-bold text-dark">SEND ANNOUNCEMENT</button>
                </form>
            </div>
        </div>
        
        <div class="card bg-dark border-secondary">
             <div class="card-body">
                 <small class="text-muted d-block mb-2">Auto-refresh recommended to catch spam live.</small>
                 <button class="btn btn-outline-light w-100 btn-sm" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i> REFRESH FEED</button>
             </div>
        </div>
    </div>

    <!-- FEED -->
    <div class="col-md-8">
        <div class="card shadow-sm border-secondary">
            <div class="card-header bg-transparent border-secondary d-flex justify-content-between align-items-center">
                <span class="text-white fw-bold"><i class="bi bi-chat-left-text-fill me-2"></i> LIVE FEED (Last 100)</span>
                <span class="badge bg-danger animate-pulse">LIVE</span>
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle small">
                    <thead>
                        <tr class="text-secondary text-uppercase text-xs">
                            <th width="10%">Time</th>
                            <th width="25%">User</th>
                            <th>Message</th>
                            <th width="20%" class="text-end">Controls</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($chats as $c): 
                            $isSystem = $c['type'] === 'system';
                            $isWin = $c['type'] === 'win' || $c['type'] === 'jackpot';
                            $isPinned = $c['is_pinned'];
                        ?>
                        <tr class="<?= $isPinned ? 'bg-warning bg-opacity-10' : '' ?>">
                            <td class="text-muted font-monospace">
                                <?php if($isPinned): ?><i class="bi bi-pin-angle-fill text-warning me-1"></i><?php endif; ?>
                                <?= date('H:i:s', strtotime($c['created_at'])) ?>
                            </td>
                            <td>
                                <?php if($isSystem): ?>
                                    <span class="badge bg-info text-dark">SYSTEM</span>
                                <?php elseif($isWin): ?>
                                    <span class="badge bg-warning text-dark">GAME</span>
                                <?php else: ?>
                                    <div class="fw-bold text-white d-flex align-items-center gap-1">
                                        <?= htmlspecialchars($c['username']) ?>
                                        <?php if($c['is_muted']): ?>
                                            <i class="bi bi-mic-mute-fill text-danger" title="Muted"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.7em;"><?= $c['phone'] ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="<?= $isSystem ? 'text-info fst-italic' : ($isWin ? 'text-warning fw-bold' : 'text-white') ?>">
                                    <?= htmlspecialchars($c['message']) ?>
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <!-- Pin Toggle -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="toggle_pin_id" value="<?= $c['id'] ?>">
                                        <input type="hidden" name="current_pin_status" value="<?= $c['is_pinned'] ?>">
                                        <button class="btn btn-link text-secondary p-1" title="<?= $isPinned ? 'Unpin' : 'Pin' ?>">
                                            <i class="bi bi-pin-angle<?= $isPinned ? '-fill text-warning' : '' ?>"></i>
                                        </button>
                                    </form>

                                    <!-- Delete -->
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this message?');">
                                        <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                                        <button class="btn btn-link text-danger p-1" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>

                                    <!-- User Actions (Mute/Purge) -->
                                    <?php if(!$isSystem && !$isWin): ?>
                                        <button type="button" class="btn btn-link text-white p-1" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-dark shadow border-secondary">
                                            <li>
                                                <form method="POST">
                                                    <input type="hidden" name="toggle_mute_id" value="<?= $c['user_id'] ?>">
                                                    <input type="hidden" name="current_mute_status" value="<?= $c['is_muted'] ?>">
                                                    <button class="dropdown-item text-warning">
                                                        <i class="bi bi-mic-mute me-2"></i> <?= $c['is_muted'] ? 'Unmute User' : 'Mute User' ?>
                                                    </button>
                                                </form>
                                            </li>
                                            <li><hr class="dropdown-divider border-secondary"></li>
                                            <li>
                                                <form method="POST" onsubmit="return confirm('Purge ALL messages from this user?');">
                                                    <input type="hidden" name="purge_user_id" value="<?= $c['user_id'] ?>">
                                                    <button class="dropdown-item text-danger"><i class="bi bi-radioactive me-2"></i> Purge All msgs</button>
                                                </form>
                                            </li>
                                        </ul>
                                    <?php endif; ?>
                                </div>
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