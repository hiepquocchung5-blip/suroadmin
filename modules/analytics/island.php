<?php
// Ensure this is loaded via the router
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');

$pageTitle = "Island RTP Analytics";
requireRole(['GOD', 'FINANCE']);
require_once ADMIN_BASE_PATH . '/layout/main.php';

// Fetch Island Performance
// Compares Total Bets vs Total Wins to find REAL RTP per isolated island ecosystem
$sql = "
    SELECT 
        i.id, i.name, i.rtp_rate as config_rtp, i.volatility,
        COUNT(g.id) as total_spins,
        COALESCE(SUM(g.bet), 0) as total_in,
        COALESCE(SUM(g.win), 0) as total_out
    FROM islands i
    LEFT JOIN machines m ON i.id = m.island_id
    LEFT JOIN game_logs g ON m.id = g.machine_id
    WHERE i.id <= 5 -- V3 Core Islands only
    GROUP BY i.id
    ORDER BY total_in DESC
";
$stats = $pdo->query($sql)->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-black text-info italic tracking-widest mb-0"><i class="bi bi-graph-up-arrow"></i> RTP TELEMETRY</h2>
        <p class="text-muted small mt-1 font-mono">Live theoretical vs actual Return-to-Player monitoring.</p>
    </div>
</div>

<div class="row g-4">
    <?php foreach($stats as $s): 
        $realRtp = $s['total_in'] > 0 ? ($s['total_out'] / $s['total_in']) * 100 : 0;
        $profit = $s['total_in'] - $s['total_out'];
        $bgClass = $profit >= 0 ? 'bg-success' : 'bg-danger';
        $delta = $realRtp - $s['config_rtp']; // Difference between Theory and Reality
        
        $volColor = match($s['volatility']) {
            'low' => 'text-green-400',
            'medium' => 'text-cyan-400',
            'high' => 'text-orange-400',
            'extreme' => 'text-red-500',
            default => 'text-gray-400'
        };
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="glass-card h-100 border-secondary overflow-hidden p-0 transition-transform hover:-translate-y-1">
            <div class="bg-black bg-opacity-60 border-b border-white border-opacity-10 p-3 d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-bold text-white fs-5 italic tracking-wider leading-none"><?= htmlspecialchars($s['name']) ?></div>
                    <div class="text-[9px] <?= $volColor ?> font-bold uppercase tracking-widest mt-1"><i class="bi bi-activity"></i> <?= $s['volatility'] ?> VOLATILITY</div>
                </div>
                <div class="text-end">
                    <span class="badge bg-dark border border-secondary text-info font-mono shadow-inner"><?= number_format($s['total_spins']) ?> SPINS</span>
                </div>
            </div>
            
            <div class="p-4 bg-black bg-opacity-30">
                <div class="row text-center mb-4 g-2">
                    <div class="col-6">
                        <div class="bg-black bg-opacity-50 p-2 rounded-lg border border-white border-opacity-5 h-100">
                            <small class="text-gray-500 d-block text-[9px] uppercase fw-bold tracking-widest mb-1">Target RTP</small>
                            <span class="fs-4 text-info fw-black font-mono"><?= number_format($s['config_rtp'], 1) ?>%</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-black bg-opacity-50 p-2 rounded-lg border border-white border-opacity-5 h-100 <?= $delta > 3 ? 'border-danger shadow-[0_0_10px_rgba(239,68,68,0.2)]' : '' ?>">
                            <small class="text-gray-500 d-block text-[9px] uppercase fw-bold tracking-widest mb-1">Actual RTP</small>
                            <span class="fs-4 fw-black font-mono <?= $delta > 3 ? 'text-danger animate-pulse' : ($delta < -3 ? 'text-yellow-400' : 'text-success') ?>">
                                <?= number_format($realRtp, 2) ?>%
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-black bg-opacity-50 p-3 rounded-xl border border-white border-opacity-5">
                    <div class="mb-2 d-flex justify-content-between align-items-end">
                        <span class="text-gray-500 text-[10px] uppercase fw-bold tracking-widest">Net Profit</span>
                        <span class="<?= $profit >= 0 ? 'text-success' : 'text-danger' ?> fw-black font-mono fs-6">
                            <?= $profit >= 0 ? '+' : '' ?><?= number_format($profit) ?> MMK
                        </span>
                    </div>
                    <div class="progress bg-dark border border-secondary" style="height: 6px;">
                        <div class="progress-bar <?= $bgClass ?> shadow-[0_0_10px_currentColor]" role="progressbar" style="width: <?= min(100, max(5, ($s['total_in'] > 0 ? ($profit / $s['total_in']) * 100 : 0))) ?>%"></div>
                    </div>
                    <div class="mt-2 d-flex justify-content-between text-[9px] font-mono text-gray-500">
                        <span>IN: <?= number_format($s['total_in']) ?></span>
                        <span>OUT: <?= number_format($s['total_out']) ?></span>
                    </div>
                </div>
            </div>
            
            <div class="bg-black bg-opacity-80 border-t border-white border-opacity-10 text-center py-2">
                <?php if($delta > 5): ?>
                    <span class="text-[10px] fw-bold text-danger animate-pulse tracking-widest"><i class="bi bi-exclamation-triangle-fill me-1"></i> CRITICAL: BLEEDING FUNDS</span>
                <?php elseif($delta < -5): ?>
                    <span class="text-[10px] fw-bold text-warning tracking-widest"><i class="bi bi-piggy-bank-fill me-1"></i> HIGH RETENTION (TIGHT)</span>
                <?php else: ?>
                    <span class="text-[10px] fw-bold text-success tracking-widest"><i class="bi bi-check-circle-fill me-1"></i> OPTIMAL BALANCE</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>