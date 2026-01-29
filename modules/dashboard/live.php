<?php
// 1. AJAX HANDLER (Must be before layout include to return JSON only)
if (isset($_GET['ajax_fetch'])) {
    // Minimal load for speed (Skip layout)
    require_once __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../../includes/functions.php';

    // Security Check
    if (!isset($_SESSION['admin_id'])) { 
        http_response_code(401); 
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); 
        exit; 
    }

    $limit = 50;
    $minBet = isset($_GET['min_bet']) ? (int)$_GET['min_bet'] : 0;
    $type = $_GET['type'] ?? 'all';

    $where = "1";
    if ($minBet > 0) $where .= " AND g.bet >= $minBet";
    if ($type === 'wins') $where .= " AND g.win > 0";
    if ($type === 'big_wins') $where .= " AND g.win >= (g.bet * 10)";

    try {
        // Fetch Live Logs
        $sql = "
            SELECT g.*, u.username, m.machine_number, i.name as island_name
            FROM game_logs g
            JOIN users u ON g.user_id = u.id
            LEFT JOIN machines m ON g.machine_id = m.id
            LEFT JOIN islands i ON m.island_id = i.id
            WHERE $where
            ORDER BY g.created_at DESC 
            LIMIT $limit
        ";
        $logs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        // Calculate Live KPIs (Last 5 Minutes)
        $kpi = $pdo->query("
            SELECT 
                COUNT(*) as spins_5m, 
                SUM(bet) as vol_5m, 
                SUM(win) as pay_5m 
            FROM game_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ")->fetch(PDO::FETCH_ASSOC);

        // Format Data for Frontend
        foreach ($logs as &$log) {
            $log['formatted_time'] = date('H:i:s', strtotime($log['created_at']));
            // Result is stored as JSON ["7","7","7"], decode for display
            $resArray = json_decode($log['result']);
            $log['result_display'] = is_array($resArray) ? implode(' ', $resArray) : $log['result'];
            
            // Visual Classes
            $isWin = $log['win'] > 0;
            $isBigWin = $isWin && ($log['win'] > $log['bet'] * 10);
            
            $log['row_class'] = $isBigWin ? 'table-warning text-dark' : ($isWin ? 'bg-success bg-opacity-10' : '');
            $log['win_class'] = $isWin ? ($isBigWin ? 'fw-black text-danger' : 'text-success fw-bold') : 'text-muted';
            
            $log['formatted_bet'] = number_format($log['bet']);
            $log['formatted_win'] = number_format($log['win']);
        }

        echo json_encode(['status' => 'success', 'logs' => $logs, 'kpi' => $kpi]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit; // Stop execution here for AJAX requests
}

// 2. MAIN PAGE RENDER
$pageTitle = "Live Game Monitor";
require_once '../../layout/main.php';
?>

<!-- KPI HEADS UP DISPLAY -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card bg-dark border-secondary">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-25 p-3 rounded text-primary"><i class="bi bi-speedometer2"></i></div>
                <div>
                    <h6 class="text-muted mb-0 small">VELOCITY (5m)</h6>
                    <h3 class="fw-bold text-white mb-0" id="kpi-spins">0</h3>
                    <small class="text-muted">spins / 5min</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-dark border-secondary">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-info bg-opacity-25 p-3 rounded text-info"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <h6 class="text-muted mb-0 small">VOLUME (5m)</h6>
                    <h3 class="fw-bold text-white mb-0" id="kpi-vol">0</h3>
                    <small class="text-muted">MMK In</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-dark border-secondary">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-danger bg-opacity-25 p-3 rounded text-danger"><i class="bi bi-graph-up-arrow"></i></div>
                <div>
                    <h6 class="text-muted mb-0 small">REAL RTP (5m)</h6>
                    <h3 class="fw-bold text-white mb-0" id="kpi-rtp">0%</h3>
                    <small class="text-muted">Return Rate</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CONTROLS -->
<div class="d-flex justify-content-between align-items-center mb-3 bg-dark p-2 rounded border border-secondary">
    <div class="d-flex gap-2 align-items-center">
        <span class="text-muted small fw-bold me-2">FILTER STREAM:</span>
        <select id="filterType" class="form-select form-select-sm bg-black text-white border-secondary" style="width: 150px;">
            <option value="all">All Activity</option>
            <option value="wins">Wins Only</option>
            <option value="big_wins">Big Wins (10x+)</option>
        </select>
        <input type="number" id="filterMinBet" class="form-control form-control-sm bg-black text-white border-secondary" placeholder="Min Bet" style="width: 100px;">
        <button class="btn btn-sm btn-outline-info" onclick="fetchLive()">APPLY</button>
    </div>
    <div class="badge bg-success bg-opacity-75 animate-pulse d-flex align-items-center gap-2 px-3 py-2">
        <span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>
        LIVE CONNECTION
    </div>
</div>

<!-- DATA TABLE -->
<div class="card border-secondary">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0 text-sm">
                <thead>
                    <tr class="text-secondary text-uppercase" style="font-size: 0.75rem;">
                        <th style="width: 100px;">Time</th>
                        <th>Player</th>
                        <th>Location</th>
                        <th class="text-end">Bet</th>
                        <th class="text-end">Result</th>
                        <th class="text-end">Win / Loss</th>
                    </tr>
                </thead>
                <tbody id="live-feed-body">
                    <tr><td colspan="6" class="text-center text-muted py-5">Connecting to Game Server...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    let isFetching = false;
    let pollInterval;

    async function fetchLive() {
        if(isFetching) return;
        isFetching = true;

        const type = document.getElementById('filterType').value;
        const minBet = document.getElementById('filterMinBet').value;

        try {
            // Call SELF with ajax param
            const res = await fetch(`?ajax_fetch=1&type=${type}&min_bet=${minBet}`);
            const data = await res.json();

            if (data.status === 'success') {
                updateKPI(data.kpi);
                updateTable(data.logs);
            }
        } catch (e) {
            console.error("Live feed connection lost", e);
        } finally {
            isFetching = false;
        }
    }

    function updateKPI(kpi) {
        document.getElementById('kpi-spins').innerText = kpi.spins_5m;
        document.getElementById('kpi-vol').innerText = Number(kpi.vol_5m).toLocaleString();
        
        const rtp = kpi.vol_5m > 0 ? ((kpi.pay_5m / kpi.vol_5m) * 100).toFixed(1) : 0;
        const rtpEl = document.getElementById('kpi-rtp');
        rtpEl.innerText = rtp + '%';
        
        // Color code RTP
        if(rtp > 100) rtpEl.className = "fw-bold text-danger mb-0";
        else if (rtp > 90) rtpEl.className = "fw-bold text-warning mb-0";
        else rtpEl.className = "fw-bold text-success mb-0";
    }

    function updateTable(logs) {
        const tbody = document.getElementById('live-feed-body');
        if (logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5">No activity matching filters in the last few seconds.</td></tr>';
            return;
        }

        const html = logs.map(log => `
            <tr class="${log.row_class}">
                <td class="text-muted font-monospace">${log.formatted_time}</td>
                <td>
                    <a href="../users/details.php?user_id=${log.user_id}" class="text-decoration-none text-white fw-bold hover-underline">
                        ${log.username}
                    </a>
                </td>
                <td>
                    <span class="badge bg-black border border-secondary text-secondary font-monospace">
                        ${log.island_name ?? 'Lobby'} #${log.machine_number ?? '?'}
                    </span>
                </td>
                <td class="text-end font-monospace text-info">${log.formatted_bet}</td>
                <td class="text-end text-muted font-monospace" style="letter-spacing: 2px;">
                    ${log.result_display}
                </td>
                <td class="text-end ${log.win_class}">
                    ${log.win > 0 ? '+' + log.formatted_win : '-'}
                </td>
            </tr>
        `).join('');

        tbody.innerHTML = html;
    }

    // Start Polling (Every 2 Seconds)
    fetchLive(); 
    pollInterval = setInterval(fetchLive, 2000);
</script>

<style>
    .hover-underline:hover { text-decoration: underline !important; color: #0dcaf0 !important; }
    /* Smooth transitions for table rows if possible, though strict replace is safer for sync */
</style>

<?php require_once '../../layout/footer.php'; ?>