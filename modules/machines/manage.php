<?php
$pageTitle = "Machine Inventory";
require_once '../../layout/main.php';
requireRole(['GOD', 'FINANCE']);

// --- HANDLE ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        // Single Create
        $islandId = (int)$_POST['island_id'];
        $number = (int)$_POST['machine_number'];
        $skin = cleanInput($_POST['paint_skin']);
        $sticker = cleanInput($_POST['sticker_char_id']) ?: null;
        
        try {
            $sql = "INSERT INTO machines (island_id, machine_number, status, paint_skin, sticker_char_id) VALUES (?, ?, 'free', ?, ?)";
            $pdo->prepare($sql)->execute([$islandId, $number, $skin, $sticker]);
            $success = "Machine #$number created successfully.";
        } catch (PDOException $e) {
            $error = "Error: Machine Number likely exists for this Island.";
        }
    } 
    elseif ($action === 'bulk_create') {
        // Bulk Create
        requireRole(['GOD']);
        $islandId = (int)$_POST['island_id'];
        $start = (int)$_POST['start_number'];
        $count = (int)$_POST['count'];
        $skin = cleanInput($_POST['paint_skin']);
        $sticker = cleanInput($_POST['sticker_char_id']) ?: null;
        $created = 0;

        $sql = "INSERT IGNORE INTO machines (island_id, machine_number, status, paint_skin, sticker_char_id) VALUES (?, ?, 'free', ?, ?)";
        $stmt = $pdo->prepare($sql);

        for ($i = 0; $i < $count; $i++) {
            $num = $start + $i;
            $stmt->execute([$islandId, $num, $skin, $sticker]);
            if ($stmt->rowCount() > 0) $created++;
        }
        $success = "Bulk Operation: $created machines created (skipped duplicates).";
    }
    elseif ($action === 'update') {
        $id = (int)$_POST['id'];
        $status = cleanInput($_POST['status']);
        $skin = cleanInput($_POST['paint_skin']);
        $sticker = cleanInput($_POST['sticker_char_id']) ?: null;
        
        // If setting to maintenance, kick user
        $currentUserSql = ($status === 'maintenance') ? ", current_user_id = NULL" : "";
        
        $sql = "UPDATE machines SET status = ?, paint_skin = ?, sticker_char_id = ? $currentUserSql WHERE id = ?";
        $pdo->prepare($sql)->execute([$status, $skin, $sticker, $id]);
        $success = "Machine updated.";
    }
    elseif ($action === 'reset_stats') {
        requireRole(['GOD']);
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE machines SET total_laps = 0, total_payout = 0 WHERE id = ?")->execute([$id]);
        $success = "Stats reset for Machine ID #$id.";
        
        // Audit
        $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'machines')")
            ->execute([$_SESSION['admin_id'], "Reset Stats Machine #$id"]);
    }
    elseif ($action === 'delete') {
        requireRole(['GOD']);
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM machines WHERE id = ?")->execute([$id]);
        $success = "Machine deleted.";
    }
}

// --- FILTERS & SEARCH ---
$filterIsland = isset($_GET['island']) ? (int)$_GET['island'] : 0;
$search = $_GET['q'] ?? '';

$where = "1";
$params = [];

if ($filterIsland) {
    $where .= " AND m.island_id = ?";
    $params[] = $filterIsland;
}
if ($search) {
    $where .= " AND (m.machine_number LIKE ? OR m.id = ?)";
    $params[] = "%$search%";
    $params[] = $search;
}

// Fetch Machines
$sql = "SELECT m.*, i.name as island_name, i.rtp_rate, c.name as sticker_name 
        FROM machines m 
        JOIN islands i ON m.island_id = i.id 
        LEFT JOIN characters c ON m.sticker_char_id = c.char_key
        WHERE $where 
        ORDER BY m.island_id ASC, m.machine_number ASC 
        LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$machines = $stmt->fetchAll();

// Fetch Options for Dropdowns
$islands = $pdo->query("SELECT id, name FROM islands ORDER BY id ASC")->fetchAll();
$chars = $pdo->query("SELECT char_key, name FROM characters ORDER BY name ASC")->fetchAll();
?>

<?php if(isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<!-- TOOLBAR -->
<div class="card mb-4">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <form method="GET" class="d-flex gap-2 flex-grow-1">
                <select name="island" class="form-select bg-dark text-white border-secondary form-select-sm w-auto" onchange="this.form.submit()">
                    <option value="0">All Islands</option>
                    <?php foreach($islands as $isl): ?>
                        <option value="<?= $isl['id'] ?>" <?= $filterIsland == $isl['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($isl['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="q" class="form-control bg-dark text-white border-secondary form-select-sm" placeholder="Search Machine #..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-sm btn-info fw-bold">SEARCH</button>
            </form>
            
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-warning fw-bold" data-bs-toggle="modal" data-bs-target="#bulkModal">
                    <i class="bi bi-stack"></i> BULK ADD
                </button>
                <button type="button" class="btn btn-sm btn-warning fw-bold" data-bs-toggle="modal" data-bs-target="#createModal">
                    <i class="bi bi-plus-lg"></i> ADD SINGLE
                </button>
            </div>
        </div>
    </div>
</div>

<!-- LIST -->
<div class="card">
    <div class="card-header border-secondary text-white">MACHINE LIST (<?= count($machines) ?>)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr class="text-secondary text-uppercase text-xs">
                        <th>ID</th>
                        <th>Location</th>
                        <th># No.</th>
                        <th>Status</th>
                        <th>Config (RTP)</th>
                        <th>Visuals</th>
                        <th>Stats</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($machines as $m): ?>
                    <tr>
                        <td><span class="text-muted">#</span><?= $m['id'] ?></td>
                        <td><?= htmlspecialchars($m['island_name']) ?></td>
                        <td><span class="fw-bold fs-5 text-white"><?= $m['machine_number'] ?></span></td>
                        <td>
                            <?php if($m['status'] === 'free'): ?>
                                <span class="badge bg-success">Open</span>
                            <?php elseif($m['status'] === 'occupied'): ?>
                                <span class="badge bg-danger">In Use</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Maint.</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="text-info"><?= $m['rtp_rate'] ?>%</span>
                        </td>
                        <td>
                            <div class="small text-muted">Skin: <?= htmlspecialchars($m['paint_skin']) ?></div>
                            <?php if($m['sticker_name']): ?>
                                <div class="badge bg-dark border border-secondary text-white">
                                    <i class="bi bi-sticky"></i> <?= htmlspecialchars($m['sticker_name']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <div>Laps: <span class="text-white"><?= number_format($m['total_laps']) ?></span></div>
                            <div>Out: <span class="text-warning"><?= number_format($m['total_payout']) ?></span></div>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-light" onclick='openEditModal(<?= json_encode($m) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if($_SESSION['admin_role'] === 'GOD'): ?>
                                <div class="btn-group">
                                    <form method="POST" onsubmit="return confirm('Reset stats for Machine #<?= $m['machine_number'] ?>?');">
                                        <input type="hidden" name="action" value="reset_stats">
                                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                        <button class="btn btn-sm btn-outline-warning border-0" title="Reset Stats"><i class="bi bi-arrow-counterclockwise"></i></button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Delete this machine?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger border-0" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- CREATE MODAL -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content bg-dark border-secondary text-white">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Add New Machine</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="create">
                <!-- Inputs duplicated for brevity, same as Edit Modal but blank -->
                <div class="mb-3">
                    <label class="text-muted small">Island</label>
                    <select name="island_id" class="form-select bg-black text-white border-secondary">
                        <?php foreach($islands as $isl): ?>
                            <option value="<?= $isl['id'] ?>"><?= htmlspecialchars($isl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="text-muted small">Machine Number</label>
                    <input type="number" name="machine_number" class="form-control bg-black text-white border-secondary" required>
                </div>
                <div class="mb-3">
                    <label class="text-muted small">Skin</label>
                    <select name="paint_skin" class="form-select bg-black text-white border-secondary">
                        <option value="default">Default</option><option value="gold">Gold</option><option value="cyber">Cyber</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="submit" class="btn btn-warning fw-bold w-100">CREATE</button>
            </div>
        </form>
    </div>
</div>

<!-- BULK CREATE MODAL -->
<div class="modal fade" id="bulkModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content bg-dark border-secondary text-white">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-warning">Bulk Generator</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="bulk_create">
                <div class="alert alert-info py-2 small">Existing machine numbers will be skipped.</div>
                
                <div class="mb-3">
                    <label class="text-muted small">Target Island</label>
                    <select name="island_id" class="form-select bg-black text-white border-secondary">
                        <?php foreach($islands as $isl): ?>
                            <option value="<?= $isl['id'] ?>"><?= htmlspecialchars($isl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="text-muted small">Start Number</label>
                        <input type="number" name="start_number" class="form-control bg-black text-white border-secondary" placeholder="e.g. 101" required>
                    </div>
                    <div class="col-6">
                        <label class="text-muted small">Quantity</label>
                        <input type="number" name="count" class="form-control bg-black text-white border-secondary" placeholder="e.g. 50" max="100" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="text-muted small">Paint Skin</label>
                    <select name="paint_skin" class="form-select bg-black text-white border-secondary">
                        <option value="default">Default</option><option value="gold">Gold</option><option value="cyber">Cyber</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="text-muted small">Sticker (Optional)</label>
                    <select name="sticker_char_id" class="form-select bg-black text-white border-secondary">
                        <option value="">-- None --</option>
                        <?php foreach($chars as $c): ?>
                            <option value="<?= $c['char_key'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="submit" class="btn btn-warning fw-bold w-100">GENERATE MACHINES</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content bg-dark border-secondary text-white">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Edit Machine #<span id="editNumDisplay"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editId">
                <div class="mb-3">
                    <label class="text-muted small">Status</label>
                    <select name="status" id="editStatus" class="form-select bg-black text-white border-secondary">
                        <option value="free">Active (Free)</option>
                        <option value="occupied">Occupied</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="text-muted small">Skin</label>
                    <select name="paint_skin" id="editSkin" class="form-select bg-black text-white border-secondary">
                        <option value="default">Default</option><option value="gold">Gold</option><option value="cyber">Cyber</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="text-muted small">Sticker</label>
                    <select name="sticker_char_id" id="editSticker" class="form-select bg-black text-white border-secondary">
                        <option value="">-- None --</option>
                        <?php foreach($chars as $c): ?>
                            <option value="<?= $c['char_key'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="submit" class="btn btn-info fw-bold w-100">SAVE CHANGES</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(machine) {
    document.getElementById('editId').value = machine.id;
    document.getElementById('editNumDisplay').innerText = machine.machine_number;
    document.getElementById('editStatus').value = machine.status;
    document.getElementById('editSkin').value = machine.paint_skin;
    document.getElementById('editSticker').value = machine.sticker_char_id || "";
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once '../../layout/footer.php'; ?>