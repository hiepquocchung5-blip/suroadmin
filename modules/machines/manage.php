<?php
// Ensure this is loaded via the router
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');

$pageTitle = "Machine Inventory";
requireRole(['GOD', 'FINANCE']);

// Include Header via Base Path
require_once ADMIN_BASE_PATH . '/layout/main.php';

// 1. Auto-Migration for Jackpot Column
try {
    $pdo->exec("ALTER TABLE machines ADD COLUMN jackpot_seed decimal(12,2) DEFAULT 0.00");
} catch(Exception $e) {} // Ignore if exists

// --- HANDLE ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        $islandId = (int)$_POST['island_id'];
        $number = (int)$_POST['machine_number'];
        $skin = cleanInput($_POST['paint_skin']);
        $sticker = cleanInput($_POST['sticker_char_id']) ?: null;
        
        try {
            $sql = "INSERT INTO machines (island_id, machine_number, status, paint_skin, sticker_char_id) VALUES (?, ?, 'free', ?, ?)";
            $pdo->prepare($sql)->execute([$islandId, $number, $skin, $sticker]);
            $success = "Machine #$number created.";
        } catch (PDOException $e) { $error = "Machine Number exists."; }
    } 
    elseif ($action === 'update') {
        $id = (int)$_POST['id'];
        $status = cleanInput($_POST['status']);
        $skin = cleanInput($_POST['paint_skin']);
        $jackpot = (float)$_POST['jackpot_seed'];
        $sticker = cleanInput($_POST['sticker_char_id']) ?: null;
        $userSql = ($status === 'maintenance') ? ", current_user_id = NULL, session_token = NULL" : "";
        
        $sql = "UPDATE machines SET status=?, paint_skin=?, sticker_char_id=?, jackpot_seed=? $userSql WHERE id=?";
        $pdo->prepare($sql)->execute([$status, $skin, $sticker, $jackpot, $id]);
        $success = "Machine updated.";
    }
    elseif ($action === 'reset_stats') {
        requireRole(['GOD']);
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE machines SET total_laps=0, total_payout=0, session_win_streak=0, laps_since_bonus=0 WHERE id=?")->execute([$id]);
        $success = "Stats reset.";
    }
    elseif ($action === 'delete') {
        requireRole(['GOD']);
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM machines WHERE id=?")->execute([$id]);
        $success = "Deleted.";
    }
}

// --- FETCH DATA ---
$filterIsland = isset($_GET['island']) ? (int)$_GET['island'] : 0;
$search = $_GET['q'] ?? '';
$where = "1";
$params = [];

if ($filterIsland) { $where .= " AND m.island_id = ?"; $params[] = $filterIsland; }
if ($search) { $where .= " AND (m.machine_number LIKE ? OR m.id = ?)"; $params[] = "%$search%"; $params[] = $search; }

$sql = "SELECT m.*, i.name as island_name, c.name as sticker_name FROM machines m JOIN islands i ON m.island_id = i.id LEFT JOIN characters c ON m.sticker_char_id = c.char_key WHERE $where ORDER BY m.island_id, m.machine_number LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$machines = $stmt->fetchAll();

$islands = $pdo->query("SELECT id, name FROM islands ORDER BY id ASC")->fetchAll();
$chars = $pdo->query("SELECT char_key, name FROM characters ORDER BY name ASC")->fetchAll();
?>

<?php if(isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<!-- TOOLBAR -->
<div class="card mb-4 bg-dark border-secondary">
    <div class="card-body py-2 d-flex gap-2">
        <form method="GET" class="d-flex gap-2 flex-grow-1">
            <input type="hidden" name="route" value="fleet/manage">
            <select name="island" class="form-select form-select-sm bg-black text-white border-secondary" style="width:150px" onchange="this.form.submit()">
                <option value="0">All Islands</option>
                <?php foreach($islands as $isl): ?><option value="<?= $isl['id'] ?>" <?= $filterIsland==$isl['id']?'selected':'' ?>><?= $isl['name'] ?></option><?php endforeach; ?>
            </select>
            <input type="text" name="q" class="form-control form-select-sm bg-black text-white border-secondary" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-sm btn-info fw-bold">SEARCH</button>
        </form>
        <button class="btn btn-sm btn-warning fw-bold" data-bs-toggle="modal" data-bs-target="#createModal">ADD NEW</button>
    </div>
</div>

<!-- LIST -->
<div class="card border-0 bg-transparent">
    <div class="row g-2">
        <?php foreach($machines as $m): 
            $isHot = $m['total_payout'] > 500000;
            $cardBorder = $isHot ? 'border-danger' : 'border-secondary';
            $glow = $isHot ? 'box-shadow: 0 0 10px rgba(255, 0, 0, 0.3);' : '';
        ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 bg-dark <?= $cardBorder ?>" style="<?= $glow ?>">
                <div class="card-body d-flex align-items-center gap-3">
                    <!-- Machine Icon -->
                    <div class="text-center">
                        <div class="fs-4 fw-bold text-white">#<?= $m['machine_number'] ?></div>
                        <span class="badge <?= $m['status']=='free' ? 'bg-success' : 'bg-danger' ?>"><?= strtoupper($m['status']) ?></span>
                    </div>
                    
                    <!-- Stats -->
                    <div class="flex-grow-1 border-start border-secondary ps-3">
                        <div class="d-flex justify-content-between small text-muted">
                            <span>LAPS: <?= number_format($m['total_laps']) ?></span>
                            <span>JP: <?= number_format($m['jackpot_seed']) ?></span>
                        </div>
                        <div class="fs-5 fw-bold text-warning"><?= number_format($m['total_payout']) ?> <small class="fs-6 text-muted">MMK Out</small></div>
                        <div class="small text-info"><?= htmlspecialchars($m['island_name']) ?></div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex flex-col gap-1">
                        <button class="btn btn-sm btn-outline-light" onclick='openEditModal(<?= json_encode($m) ?>)'><i class="bi bi-gear"></i></button>
                        <form method="POST" onsubmit="return confirm('Reset stats?');">
                            <input type="hidden" name="action" value="reset_stats">
                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                            <button class="btn btn-sm btn-outline-secondary border-0"><i class="bi bi-recycle"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content bg-dark border-secondary text-white">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Config Machine #<span id="editNumDisplay"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editId">
                <div class="mb-3">
                    <label class="text-muted small">Status</label>
                    <select name="status" id="editStatus" class="form-select bg-black text-white border-secondary">
                        <option value="free">Active</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="text-muted small">Jackpot Seed (MMK)</label>
                    <input type="number" name="jackpot_seed" id="editJackpot" class="form-control bg-black text-white border-secondary">
                </div>
                <div class="mb-3">
                    <label class="text-muted small">Paint Skin</label>
                    <select name="paint_skin" id="editSkin" class="form-select bg-black text-white border-secondary">
                        <option value="default">Default</option><option value="gold">Gold</option><option value="cyber">Cyber</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="text-muted small">Sticker</label>
                    <select name="sticker_char_id" id="editSticker" class="form-select bg-black text-white border-secondary">
                        <option value="">-- None --</option>
                        <?php foreach($chars as $c): ?><option value="<?= $c['char_key'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="submit" class="btn btn-info fw-bold w-100">SAVE CONFIG</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(m) {
    document.getElementById('editId').value = m.id;
    document.getElementById('editNumDisplay').innerText = m.machine_number;
    document.getElementById('editStatus').value = m.status;
    document.getElementById('editJackpot').value = m.jackpot_seed;
    document.getElementById('editSkin').value = m.paint_skin;
    document.getElementById('editSticker').value = m.sticker_char_id || "";
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>