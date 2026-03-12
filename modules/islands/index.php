<?php
// Ensure this is loaded via the router
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');

$pageTitle = "V3 Island Engine Config";
requireRole(['GOD']);
require_once ADMIN_BASE_PATH . '/layout/main.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $rtp = (float)$_POST['rtp_rate'];

    try {
        $pdo->prepare("UPDATE islands SET rtp_rate = ? WHERE id = ?")->execute([$rtp, $id]);
        $success = "Island #$id Target RTP recalibrated to $rtp%.";
        $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'islands')")->execute([$_SESSION['admin_id'], "Changed Island #$id RTP to $rtp%"]);
    } catch (Exception $e) {
        $error = "Recalibration failed. Database Error.";
    }
}

// Only fetch the 5 V3 Production Islands
$islands = $pdo->query("SELECT * FROM islands WHERE id <= 5 ORDER BY id ASC")->fetchAll();
?>

<?php if(isset($success)): ?>
    <div class="alert bg-success bg-opacity-20 text-success border border-success border-opacity-50 fw-bold small rounded-lg shadow-sm animate-pulse">
        <i class="bi bi-check-circle-fill me-2"></i><?= $success ?>
    </div>
<?php endif; ?>
<?php if(isset($error)): ?>
    <div class="alert bg-danger bg-opacity-20 text-danger border border-danger border-opacity-50 fw-bold small rounded-lg shadow-sm">
        <i class="bi bi-x-circle-fill me-2"></i><?= $error ?>
    </div>
<?php endif; ?>

<div class="alert bg-black border border-info border-opacity-30 p-4 rounded-xl d-flex gap-4 align-items-center mb-5 shadow-[0_0_20px_rgba(13,202,240,0.1)]">
    <i class="bi bi-cpu display-4 text-info opacity-75"></i>
    <div>
        <h5 class="text-info fw-black italic tracking-widest mb-1">RTP ALGORITHMIC CONTROL</h5>
        <p class="text-gray-400 small mb-0 font-mono">
            Adjusting these sliders modifies the base probability weightings in real-time. 
            The target RTP for V3 environments should remain near <strong>70.0%</strong> to account for the Grand Jackpot math model.
        </p>
    </div>
</div>

<div class="row g-4">
    <?php foreach($islands as $isl): 
        // Visual indicators based on RTP
        $glow = 'border-secondary';
        $iconColor = 'text-gray-400';
        
        if ($isl['rtp_rate'] > 80) { $glow = 'border-danger shadow-[0_0_15px_rgba(239,68,68,0.3)]'; $iconColor = 'text-danger animate-pulse'; }
        elseif ($isl['rtp_rate'] < 50) { $glow = 'border-info shadow-[0_0_15px_rgba(6,182,212,0.3)]'; $iconColor = 'text-info'; }
        else { $glow = 'border-success shadow-[0_0_15px_rgba(34,197,94,0.1)]'; $iconColor = 'text-success'; }
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="glass-card h-100 border border-opacity-50 <?= $glow ?> overflow-hidden">
            
            <div class="card-header bg-black bg-opacity-50 border-bottom border-white border-opacity-10 d-flex justify-content-between p-3">
                <span class="fw-black text-white italic tracking-wider uppercase d-flex align-items-center gap-2">
                    <i class="bi bi-map-fill <?= $iconColor ?>"></i> <?= htmlspecialchars($isl['name']) ?>
                </span>
                <span class="badge bg-dark border border-secondary text-gray-400 font-mono">SYS_ID: <?= $isl['id'] ?></span>
            </div>

            <div class="card-body p-4">
                <div class="bg-black bg-opacity-40 p-3 rounded-xl border border-white border-opacity-5 mb-4" style="height: 65px;">
                    <p class="text-gray-400 small mb-0 font-mono text-[10px] leading-tight">
                        <?= htmlspecialchars($isl['desc']) ?><br/>
                        <span class="text-cyan-500">Volatility: <?= strtoupper($isl['volatility']) ?></span>
                    </p>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="id" value="<?= $isl['id'] ?>">
                    
                    <div class="d-flex justify-content-between align-items-end mb-2">
                        <span class="text-white text-xs font-bold uppercase tracking-widest" style="font-size: 0.7rem;">Base Target RTP</span>
                        <span class="font-mono fs-3 fw-black text-white drop-shadow-md" id="rtp-val-<?= $isl['id'] ?>">
                            <?= $isl['rtp_rate'] ?>%
                        </span>
                    </div>
                    
                    <div class="position-relative py-2">
                        <input type="range" class="form-range" name="rtp_rate" min="10" max="95" step="0.5" 
                               value="<?= $isl['rtp_rate'] ?>" 
                               oninput="document.getElementById('rtp-val-<?= $isl['id'] ?>').innerText = this.value + '%'">
                    </div>
                           
                    <div class="d-flex justify-content-between text-gray-500 font-mono font-bold mt-1" style="font-size: 9px;">
                        <span>HARD (10%)</span>
                        <span class="text-success border-x border-success border-opacity-50 px-2">OPTIMAL (70%)</span>
                        <span>LOOSE (95%)</span>
                    </div>

                    <button type="submit" class="btn w-100 fw-black py-3 mt-4 tracking-widest text-[10px] transition-all hover:scale-[1.02] active:scale-95" style="background: linear-gradient(90deg, #00f3ff, #0066ff); color: #000; box-shadow: 0 5px 15px rgba(0,243,255,0.3);">
                        <i class="bi bi-sliders me-1"></i> COMMIT CALIBRATION
                    </button>
                </form>
            </div>
            
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>