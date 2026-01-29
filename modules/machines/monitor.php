<?php
$pageTitle = "Machine Hall Monitor";
require_once '../../layout/main.php';

// Handle Kick/Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole(['GOD']); // Only GOD can reset machines
    
    $machineId = (int)$_POST['machine_id'];
    
    // Force vacate
    $pdo->prepare("UPDATE machines SET status = 'free', current_user_id = NULL WHERE id = ?")->execute([$machineId]);
    $success = "Machine #$machineId reset successfully.";
}

// Fetch Islands for Dropdown
$islands = $pdo->query("SELECT * FROM islands WHERE is_active = 1")->fetchAll();
$selectedIsland = isset($_GET['island']) ? (int)$_GET['island'] : ($islands[0]['id'] ?? 1);

// Fetch Machines
$machines = $pdo->prepare("
    SELECT m.*, u.username 
    FROM machines m 
    LEFT JOIN users u ON m.current_user_id = u.id 
    WHERE m.island_id = ? 
    ORDER BY m.machine_number ASC
");
$machines->execute([$selectedIsland]);
$grid = $machines->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <form method="GET" class="d-flex gap-2">
        <select name="island" class="form-select bg-dark text-white border-secondary" onchange="this.form.submit()">
            <?php foreach($islands as $island): ?>
                <option value="<?= $island['id'] ?>" <?= $island['id'] == $selectedIsland ? 'selected' : '' ?>>
                    <?= htmlspecialchars($island['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    
    <div>
        <span class="badge bg-success me-2">Free</span>
        <span class="badge bg-danger">Occupied</span>
    </div>
</div>

<?php if(isset($success)): ?><div class="alert alert-success py-2"><?= $success ?></div><?php endif; ?>

<div class="card border-0 bg-transparent">
    <div class="card-body p-0">
        <div class="row g-2">
            <?php foreach($grid as $m): ?>
                <div class="col-6 col-sm-4 col-md-3 col-lg-2 col-xl-1">
                    <div class="card text-center border-<?= $m['status'] === 'free' ? 'success' : 'danger' ?> bg-dark position-relative" style="min-height: 100px;">
                        <div class="card-body p-2 d-flex flex-col justify-content-center align-items-center" style="height: 100%;">
                            <h5 class="card-title text-white mb-1">#<?= $m['machine_number'] ?></h5>
                            
                            <?php if($m['status'] === 'occupied'): ?>
                                <small class="text-danger d-block text-truncate" style="max-width: 100%; font-size: 10px;">
                                    <?= htmlspecialchars($m['username'] ?? 'Unknown') ?>
                                </small>
                                <form method="POST" class="mt-1 position-absolute top-0 end-0">
                                    <input type="hidden" name="machine_id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="btn btn-sm text-white p-0 px-1" title="Kick User" onclick="return confirm('Force kick user from machine?')">
                                        &times;
                                    </button>
                                </form>
                            <?php else: ?>
                                <small class="text-success" style="font-size: 10px;">OPEN</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once '../../layout/footer.php'; ?>