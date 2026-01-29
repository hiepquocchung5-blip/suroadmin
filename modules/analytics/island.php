<?php
$pageTitle = "Island RTP Analytics";
require_once '../../layout/main.php';
requireRole(['GOD']);

// Fetch Island Performance
// Compares Total Bets vs Total Wins to find REAL RTP
$sql = "
    SELECT 
        i.id, i.name, i.rtp_rate as config_rtp,
        COUNT(g.id) as total_spins,
        SUM(g.bet) as total_in,
        SUM(g.win) as total_out
    FROM islands i
    LEFT JOIN machines m ON i.id = m.island_id
    LEFT JOIN game_logs g ON m.id = g.machine_id
    GROUP BY i.id
    ORDER BY total_in DESC
";
$stats = $pdo->query($sql)->fetchAll();
?>

<div class="row g-4">
    <?php foreach($stats as $s): 
        $realRtp = $s['total_in'] > 0 ? ($s['total_out'] / $s['total_in']) * 100 : 0;
        $profit = $s['total_in'] - $s['total_out'];
        $bgClass = $profit >= 0 ? 'bg-success' : 'bg-danger';
        $delta = $realRtp - $s['config_rtp']; // Difference between Theory and Reality
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="card h-100 border-secondary">
            <div class="card-header bg-dark d-flex justify-content-between">
                <span class="fw-bold text-white"><?= htmlspecialchars($s['name']) ?></span>
                <span class="badge bg-dark border border-secondary"><?= number_format($s['total_spins']) ?> Spins</span>
            </div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col-6 border-end border-secondary">
                        <small class="text-muted d-block">THEORETICAL RTP</small>
                        <span class="fs-4 text-info fw-bold"><?= $s['config_rtp'] ?>%</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">ACTUAL RTP</small>
                        <span class="fs-4 fw-bold <?= $delta > 2 ? 'text-danger' : 'text-success' ?>">
                            <?= number_format($realRtp, 2) ?>%
                        </span>
                    </div>
                </div>
                
                <!-- Profit Bar -->
                <div class="mb-2 d-flex justify-content-between small">
                    <span class="text-muted">Net Profit</span>
                    <span class="<?= $profit >= 0 ? 'text-success' : 'text-danger' ?> fw-bold">
                        <?= number_format($profit) ?> MMK
                    </span>
                </div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar <?= $bgClass ?>" role="progressbar" style="width: <?= min(100, ($s['total_in'] > 0 ? ($profit / $s['total_in']) * 100 : 0)) ?>%"></div>
                </div>
            </div>
            <div class="card-footer border-secondary text-center">
                <?php if($delta > 5): ?>
                    <span class="badge bg-danger text-white"><i class="bi bi-exclamation-triangle"></i> PAYING TOO MUCH</span>
                <?php elseif($delta < -5): ?>
                    <span class="badge bg-warning text-dark"><i class="bi bi-piggy-bank"></i> TIGHT SLOT</span>
                <?php else: ?>
                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> BALANCED</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php require_once '../../layout/footer.php'; ?>