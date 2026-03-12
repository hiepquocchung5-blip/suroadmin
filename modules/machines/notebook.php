<?php
/**
 * Isolated Machine Notebook Component
 * Designed to be loaded inside an iframe on the Monitor screen.
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth(); // Ensure security

// Strictly cast the ID to an integer
$machineId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

if (!$machineId) {
    die("
        <div style='display:flex; height:100vh; align-items:center; justify-content:center; background-color:#1e1e1e;'>
            <div style='color:#ef4444; padding:20px; font-family:sans-serif; text-align:center; font-weight:900; letter-spacing:1px; border: 1px solid #ef4444; border-radius: 8px; background: rgba(239, 68, 68, 0.1);'>
                INVALID MACHINE CONNECTION ID
            </div>
        </div>
    ");
}

// 1. Handle New Note Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note'])) {
    // Trim whitespace and clean input
    $note = trim(cleanInput($_POST['note']));
    
    if (!empty($note)) {
        $stmt = $pdo->prepare("INSERT INTO machine_notes (machine_id, admin_id, note) VALUES (?, ?, ?)");
        $stmt->execute([$machineId, $_SESSION['admin_id'], $note]);
    }
    
    // Prevent form resubmission on refresh and persist the theme in URL
    $themeParam = isset($_GET['theme']) ? '&theme=' . urlencode($_GET['theme']) : '';
    header("Location: notebook.php?id=" . $machineId . $themeParam);
    exit;
}

// 2. Fetch History
$stmt = $pdo->prepare("
    SELECT n.*, a.username, a.role 
    FROM machine_notes n
    JOIN admin_users a ON n.admin_id = a.id
    WHERE n.machine_id = ?
    ORDER BY n.created_at DESC
");
$stmt->execute([$machineId]);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine Theme based on URL param or fallback
$theme = $_GET['theme'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Machine Notebook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=JetBrains+Mono&display=swap');
        
        body { 
            background: transparent; 
            font-family: 'Inter', sans-serif; 
            overflow-x: hidden; 
            color: var(--bs-body-color);
        }
        
        /* Glassmorphism Log Cards (Dark Default) */
        .note-card { 
            background: rgba(0, 0, 0, 0.4); 
            border: 1px solid rgba(255, 255, 255, 0.1); 
            border-radius: 12px; 
            margin-bottom: 12px; 
            padding: 12px 16px; 
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            transition: all 0.2s ease-in-out;
        }
        
        /* Light mode overrides */
        [data-bs-theme="light"] .note-card {
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        [data-bs-theme="light"] .text-white { color: #212529 !important; }
        [data-bs-theme="light"] .text-info { color: #C5A059 !important; }
        
        /* Utilities */
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        .tracking-widest { letter-spacing: 0.1em; }
        
        /* Sleek Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(128,128,128,0.3); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(128,128,128,0.6); }
    </style>
</head>
<body class="p-3 d-flex flex-column" style="height: 100vh; margin: 0;">

    <div class="mb-4 flex-shrink-0">
        <form method="POST">
            <div class="input-group shadow-sm">
                <input type="text" name="note" class="form-control bg-dark bg-opacity-25 border-secondary" placeholder="Log maintenance, player complaint, or anomaly..." required autocomplete="off" autofocus>
                <button class="btn btn-info fw-bolder tracking-widest text-dark px-4" type="submit" style="box-shadow: 0 0 15px rgba(13,202,240,0.3);">
                    <i class="bi bi-send-fill"></i> LOG
                </button>
            </div>
            <div class="form-text text-muted mt-1 font-mono" style="font-size: 11px;">
                <i class="bi bi-shield-lock me-1"></i> Logs are permanent and visible to all authorized personnel.
            </div>
        </form>
    </div>

    <div class="flex-grow-1 overflow-y-auto pe-2">
        <?php if (empty($notes)): ?>
            <div class="text-center text-muted py-5 d-flex flex-column align-items-center justify-content-center h-100">
                <i class="bi bi-journal-x fs-1 opacity-25 mb-2"></i>
                <span class="small fw-bold text-uppercase tracking-widest">No Records Found</span>
            </div>
        <?php else: ?>
            <?php foreach ($notes as $n): ?>
                <div class="note-card">
                    <div class="d-flex justify-content-between align-items-center mb-2 border-bottom border-secondary border-opacity-25 pb-2">
                        <div class="fw-bold text-info" style="font-size: 0.85rem;">
                            <i class="bi bi-person-badge me-1"></i> <?= htmlspecialchars($n['username']) ?> 
                            <span class="badge bg-secondary bg-opacity-25 text-light ms-2 border border-secondary" style="font-size: 0.6rem;">
                                <?= htmlspecialchars($n['role']) ?>
                            </span>
                        </div>
                        <div class="text-muted font-mono" style="font-size: 0.75rem;">
                            <i class="bi bi-clock"></i> <?= date('M d, Y - H:i', strtotime($n['created_at'])) ?>
                        </div>
                    </div>
                    <div class="small lh-sm text-white opacity-75 font-mono" style="font-size: 0.85rem;">
                        <?= nl2br(htmlspecialchars($n['note'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        try {
            const parentTheme = window.parent.document.documentElement.getAttribute('data-bs-theme');
            if (parentTheme) {
                document.documentElement.setAttribute('data-bs-theme', parentTheme);
                // Update the URL so form submission keeps the theme
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('theme') !== parentTheme) {
                    urlParams.set('theme', parentTheme);
                    window.history.replaceState({}, '', `${window.location.pathname}?${urlParams}`);
                }
            }
        } catch(e) {
            console.warn("Theme sync failed. Could not access parent frame.");
        }
    </script>
</body>
</html>