<?php
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');
$pageTitle = "Jackpot Control Room";
requireRole(['GOD', 'FINANCE']);
require_once ADMIN_BASE_PATH . '/layout/main.php';

// Handle Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole(['GOD']);
    $action = $_POST['action'];
    $id = (int)$_POST['id'];

    if ($action === 'update_seed') {
        $amount = (float)$_POST['amount'];
        $pdo->prepare("UPDATE global_jackpots SET current_amount = ? WHERE id = ?")->execute([$amount, $id]);
        $success = "Mainframe pool manually injected.";
    } 
    elseif ($action === 'update_rate') {
        $rate = (float)$_POST['rate'];
        $pdo->prepare("UPDATE global_jackpots SET contribution_rate = ? WHERE id = ?")->execute([$rate, $id]);
        $success = "Siphon rate re-calibrated.";
    }
}

$jackpots = $pdo->query("SELECT * FROM global_jackpots")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-black text-warning italic tracking-widest m-0 drop-shadow-[0_0_15px_rgba(234,179,8,0.3)]"><i class="bi bi-trophy-fill"></i> POOL COMMAND</h2>
</div>

<?php if(isset($success)): ?><div class="alert bg-success bg-opacity-20 text-success border border-success fw-bold shadow-sm"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>

<div class="row">
    <?php foreach($jackpots as $jp): ?>
    <div class="col-12 col-xl-8 mx-auto">
        <div class="glass-card p-0 border-warning border-opacity-50 overflow-hidden shadow-[0_0_50px_rgba(234,179,8,0.15)] position-relative">
            
            <!-- Animated Tech Background -->
            <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/circuit-board.png')] opacity-10 mix-blend-color-dodge pointer-events-none animate-[pulse_4s_ease-in-out_infinite]"></div>
            
            <div class="bg-black bg-opacity-80 p-4 border-b border-warning border-opacity-30 d-flex justify-content-between align-items-center relative z-10">
                <span class="text-white fw-bold tracking-widest uppercase fs-5"><?= htmlspecialchars($jp['name']) ?></span>
                <span class="badge bg-success bg-opacity-20 text-success border border-success px-3 py-2 rounded-pill shadow-[0_0_10px_lime] animate-pulse">ACTIVE LINK</span>
            </div>
            
            <div class="card-body text-center py-5 bg-gradient-to-b from-warning/10 to-transparent relative z-10">
                <h6 class="text-warning fw-bold tracking-widest mb-3 small">CURRENT LIQUIDITY POOL</h6>
                <h1 class="display-2 fw-black text-transparent bg-clip-text bg-gradient-to-b from-yellow-200 via-yellow-500 to-orange-600 font-mono drop-shadow-[0_0_30px_gold] mb-0 lh-1">
                    <?= number_format($jp['current_amount']) ?>
                </h1>
                <div class="text-warning mt-2 fw-bold tracking-widest font-mono">MMK</div>
            </div>
            
            <div class="bg-black bg-opacity-90 p-4 relative z-10">
                <form method="POST" class="row g-4 align-items-end mb-4">
                    <input type="hidden" name="id" value="<?= $jp['id'] ?>">
                    
                    <div class="col-md-6">
                        <label class="text-muted small fw-bold text-uppercase tracking-widest mb-2"><i class="bi bi-pencil-square"></i> Manual Override</label>
                        <div class="input-group">
                            <input type="number" name="amount" class="form-control bg-dark text-white border-secondary font-mono fs-5" value="<?= $jp['current_amount'] ?>">
                            <button name="action" value="update_seed" class="btn btn-warning fw-black px-4 shadow-[0_0_10px_gold]">INJECT</button>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="text-muted small fw-bold text-uppercase tracking-widest mb-2"><i class="bi bi-funnel"></i> Contribution Rate</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-gray-500">%</span>
                            <input type="number" step="0.0001" name="rate" class="form-control bg-dark text-white border-secondary font-mono fs-5" value="<?= $jp['contribution_rate'] ?>">
                            <button name="action" value="update_rate" class="btn btn-info fw-black px-4 text-dark shadow-[0_0_10px_cyan]">SET</button>
                        </div>
                    </div>
                </form>
                
                <div class="bg-white bg-opacity-5 rounded-3 p-3 border border-white border-opacity-10 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small d-block text-uppercase fw-bold tracking-widest mb-1">Last Crack</span>
                        <div class="text-white fw-bold d-flex align-items-center gap-2">
                            <i class="bi bi-person-circle text-info"></i> <?= $jp['last_won_by'] ?? 'Undiscovered' ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="text-muted small d-block text-uppercase fw-bold tracking-widest mb-1">Time / Yield</span>
                        <div class="text-success font-mono fw-black">
                            <?= $jp['last_won_amount'] ? '+'.number_format($jp['last_won_amount']) : '0' ?> MMK
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>