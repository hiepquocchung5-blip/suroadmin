<?php
$pageTitle = "Player Retention Analytics";
require_once '../../layout/main.php';
requireRole(['GOD', 'FINANCE']);

// 1. Daily Active Users (Last 7 Days)
$dau = $pdo->query("
    SELECT DATE(last_login) as date, COUNT(DISTINCT id) as users 
    FROM admin_users -- Note: In real app, track 'users' table logins separately or use access_logs
    -- Using transactions as a proxy for user activity if login logs aren't granular
    WHERE last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(last_login)
    ORDER BY date ASC
")->fetchAll();

// Better DAU query using user_tokens or game_logs
$dauData = $pdo->query("
    SELECT DATE(created_at) as date, COUNT(DISTINCT user_id) as active_users
    FROM game_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll();

// 2. Churn Rate (Users inactive > 7 days)
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$inactiveUsers = $pdo->query("
    SELECT COUNT(*) FROM users 
    WHERE id NOT IN (SELECT DISTINCT user_id FROM game_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
")->fetchColumn();

$churnRate = $totalUsers > 0 ? round(($inactiveUsers / $totalUsers) * 100, 1) : 0;

// 3. Whale Segment (Top 10% by Volume)
$whales = $pdo->query("
    SELECT u.username, SUM(g.bet) as total_volume 
    FROM game_logs g 
    JOIN users u ON g.user_id = u.id 
    GROUP BY u.id 
    ORDER BY total_volume DESC 
    LIMIT 10
")->fetchAll();

?>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-info h-100 bg-info bg-opacity-10">
            <div class="card-body text-center">
                <h6 class="text-info mb-2">ACTIVE PLAYERS (24H)</h6>
                <h2 class="fw-black text-white"><?= number_format(end($dauData)['active_users'] ?? 0) ?></h2>
                <small class="text-muted">Unique spinners today</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-danger h-100 bg-danger bg-opacity-10">
            <div class="card-body text-center">
                <h6 class="text-danger mb-2">CHURN RISK</h6>
                <h2 class="fw-black text-white"><?= $churnRate ?>%</h2>
                <small class="text-muted"><?= number_format($inactiveUsers) ?> inactive > 7 days</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-warning h-100 bg-warning bg-opacity-10">
            <div class="card-body text-center">
                <h6 class="text-warning mb-2">WHALE ACTIVITY</h6>
                <h2 class="fw-black text-white"><?= count($whales) ?></h2>
                <small class="text-muted">High rollers tracked</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Activity Chart (Mock Visual) -->
    <div class="col-md-8">
        <div class="card border-secondary">
            <div class="card-header border-secondary text-white fw-bold">7-DAY ACTIVITY TREND</div>
            <div class="card-body">
                <div class="d-flex align-items-end justify-content-between h-100" style="min-height: 200px;">
                    <?php 
                    $maxVal = 0;
                    foreach($dauData as $d) $maxVal = max($maxVal, $d['active_users']);
                    
                    foreach($dauData as $d): 
                        $height = $maxVal > 0 ? ($d['active_users'] / $maxVal) * 100 : 0;
                    ?>
                    <div class="text-center w-100 mx-1">
                        <div class="bg-info opacity-75 rounded-top" style="height: <?= $height ?>px; transition: height 1s;"></div>
                        <small class="text-muted d-block mt-2" style="font-size: 10px;"><?= date('m/d', strtotime($d['date'])) ?></small>
                    </div>
                    <?php endforeach; ?>
                    <?php if(empty($dauData)) echo '<div class="w-100 text-center text-muted">No recent activity data.</div>'; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Whales -->
    <div class="col-md-4">
        <div class="card border-secondary">
            <div class="card-header border-secondary text-warning fw-bold">TOP WHALES</div>
            <ul class="list-group list-group-flush">
                <?php foreach($whales as $i => $w): ?>
                <li class="list-group-item bg-transparent text-white d-flex justify-content-between border-secondary">
                    <span><?= $i+1 ?>. <?= htmlspecialchars($w['username']) ?></span>
                    <span class="font-monospace text-warning"><?= number_format($w['total_volume']) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<?php require_once '../../layout/footer.php'; ?>