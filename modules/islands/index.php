<?php
$pageTitle = "Island Configuration";
require_once '../../layout/main.php';
requireRole(['GOD']);

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $rtp = (float)$_POST['rtp_rate'];
    $price = (float)$_POST['unlock_price'];
    $atmosphere = cleanInput($_POST['atmosphere_type']);
    $active = isset($_POST['is_active']) ? 1 : 0;

    try {
        $stmt = $pdo->prepare("UPDATE islands SET rtp_rate = ?, unlock_price = ?, atmosphere_type = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$rtp, $price, $atmosphere, $active, $id]);
        $success = "Island #$id updated successfully.";
        
        // Audit
        $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'islands')")
            ->execute([$_SESSION['admin_id'], "Updated Island #$id RTP to $rtp%"]);
            
    } catch (PDOException $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}

// Fetch Islands
$islands = $pdo->query("SELECT * FROM islands ORDER BY id ASC")->fetchAll();
$atmospheres = ['neon_rain', 'sunset', 'ash', 'snow', 'clouds', 'spores', 'static', 'steam', 'stars', 'none'];
?>

<?php if(isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<div class="row g-4">
    <?php foreach($islands as $isl): ?>
    <div class="col-xl-6">
        <div class="card h-100 border-secondary">
            <div class="card-header bg-dark border-secondary d-flex justify-content-between align-items-center">
                <div>
                    <span class="fs-5 me-2"><?= $isl['icon_emoji'] ?></span>
                    <span class="fw-bold text-white"><?= htmlspecialchars($isl['name']) ?></span>
                    <span class="badge bg-secondary ms-2"><?= $isl['slug'] ?></span>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" disabled <?= $isl['is_active'] ? 'checked' : '' ?>>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="id" value="<?= $isl['id'] ?>">
                    
                    <!-- RTP Slider -->
                    <div class="col-12">
                        <label class="form-label d-flex justify-content-between text-muted small">
                            <span>Win Rate (RTP %)</span>
                            <span class="text-info fw-bold" id="rtp-val-<?= $isl['id'] ?>"><?= $isl['rtp_rate'] ?>%</span>
                        </label>
                        <input type="range" class="form-range" name="rtp_rate" min="80" max="99" step="0.5" 
                               value="<?= $isl['rtp_rate'] ?>" 
                               oninput="document.getElementById('rtp-val-<?= $isl['id'] ?>').innerText = this.value + '%'">
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-gradient-info" role="progressbar" style="width: <?= $isl['rtp_rate'] ?>%"></div>
                        </div>
                    </div>

                    <!-- Economy -->
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Unlock Price (MMK)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-dark border-secondary text-secondary">$</span>
                            <input type="number" name="unlock_price" class="form-control bg-dark text-white border-secondary" value="<?= $isl['unlock_price'] ?>">
                        </div>
                    </div>

                    <!-- Visuals -->
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Atmosphere FX</label>
                        <select name="atmosphere_type" class="form-select form-select-sm bg-dark text-white border-secondary">
                            <?php foreach($atmospheres as $at): ?>
                                <option value="<?= $at ?>" <?= $isl['atmosphere_type'] == $at ? 'selected' : '' ?>>
                                    <?= ucfirst(str_replace('_', ' ', $at)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Status -->
                    <div class="col-12 d-flex justify-content-between align-items-center mt-3 pt-3 border-top border-secondary">
                        <div class="form-check">
                            <input class="form-check-input bg-dark border-secondary" type="checkbox" name="is_active" value="1" id="active-<?= $isl['id'] ?>" <?= $isl['is_active'] ? 'checked' : '' ?>>
                            <label class="form-check-label text-muted small" for="active-<?= $isl['id'] ?>">
                                Island Accessible
                            </label>
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-info fw-bold px-4">UPDATE CONFIG</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<style>
    /* Custom Range Style */
    .form-range::-webkit-slider-thumb { background: #00f3ff; }
    .form-range::-moz-range-thumb { background: #00f3ff; }
    .bg-gradient-info { background: linear-gradient(90deg, #0dcaf0 0%, #00f3ff 100%); }
</style>

<?php require_once '../../layout/footer.php'; ?>