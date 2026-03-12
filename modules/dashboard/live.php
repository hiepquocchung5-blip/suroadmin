<?php
// Ensure this is loaded via the router or directly for AJAX
if (!defined('__DIR__')) exit;

// 1. AJAX HANDLER (Returns JSON)
if (isset($_GET['ajax_fetch'])) {
    // Security check logic is handled by the router/index.php environment
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
            $resArray = json_decode($log['result']);
            $log['result_display'] = is_array($resArray) ? implode(' ', $resArray) : $log['result'];
            
            $isWin = $log['win'] > 0;
            $isBigWin = $isWin && ($log['win'] >= $log['bet'] * 10);
            
            $log['row_class'] = $isBigWin ? 'bg-yellow-900 bg-opacity-20 border-l-2 border-yellow-500' : ($isWin ? 'bg-green-900 bg-opacity-10 border-l-2 border-green-500' : 'border-l-2 border-transparent');
            $log['win_class'] = $isWin ? ($isBigWin ? 'fw-black text-warning shadow-[0_0_10px_gold]' : 'text-success fw-bold') : 'text-muted';
            
            $log['formatted_bet'] = number_format($log['bet']);
            $log['formatted_win'] = number_format($log['win']);
        }

        echo json_encode(['status' => 'success', 'logs' => $logs, 'kpi' => $kpi]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// 2. MAIN PAGE RENDER
$pageTitle = "Live Telemetry Feed";
requireRole(['GOD', 'FINANCE']);
require_once __DIR__ . '/../../layout/main.php';
?>

<!-- KPI HEADS UP DISPLAY -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="glass-card border-cyan-500 border-opacity-50">
            <div class="card-body d-flex align-items-center gap-4 p-4">
                <div class="bg-cyan-900 bg-opacity-40 p-3 rounded-xl border border-cyan-500 border-opacity-50 text-cyan-400 shadow-[0_0_15px_rgba(6,182,212,0.3)]">
                    <i class="bi bi-speedometer2 fs-3"></i>
                </div>
                <div>
                    <h6 class="text-cyan-500 mb-1 small fw-bold tracking-widest uppercase">Velocity (5m)</h6>
                    <h3 class="fw-black text-white mb-0 font-mono lh-1" id="kpi-spins">0</h3>
                    <small class="text-gray-500 font-mono text-[10px]">spins / 5min</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-card border-green-500 border-opacity-50">
            <div class="card-body d-flex align-items-center gap-4 p-4">
                <div class="bg-green-900 bg-opacity-40 p-3 rounded-xl border border-green-500 border-opacity-50 text-green-400 shadow-[0_0_15px_rgba(34,197,94,0.3)]">
                    <i class="bi bi-cash-stack fs-3"></i>
                </div>
                <div>
                    <h6 class="text-green-500 mb-1 small fw-bold tracking-widest uppercase">Volume (5m)</h6>
                    <h3 class="fw-black text-white mb-0 font-mono lh-1" id="kpi-vol">0</h3>
                    <small class="text-gray-500 font-mono text-[10px]">MMK IN</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-card border-purple-500 border-opacity-50">
            <div class="card-body d-flex align-items-center gap-4 p-4">
                <div class="bg-purple-900 bg-opacity-40 p-3 rounded-xl border border-purple-500 border-opacity-50 text-purple-400 shadow-[0_0_15px_rgba(168,85,247,0.3)]">
                    <i class="bi bi-graph-up-arrow fs-3"></i>
                </div>
                <div>
                    <h6 class="text-purple-500 mb-1 small fw-bold tracking-widest uppercase">Real RTP (5m)</h6>
                    <h3 class="fw-black text-white mb-0 font-mono lh-1" id="kpi-rtp">0%</h3>
                    <small class="text-gray-500 font-mono text-[10px]">PAYOUT RATIO</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CONTROLS -->
<div class="glass-card p-3 mb-4 d-flex flex-wrap justify-content-between align-items-center border-secondary border-opacity-50">
    <div class="d-flex gap-3 align-items-center">
        <span class="text-cyan-400 small fw-bold tracking-widest uppercase"><i class="bi bi-funnel me-1"></i> Data Stream:</span>
        <select id="filterType" class="form-select form-select-sm bg-black text-white border-secondary fw-bold font-mono" style="width: 180px;">
            <option value="all">ALL ACTIVITY</option>
            <option value="wins">WINS ONLY</option>
            <option value="big_wins">BIG WINS (10x+)</option>
        </select>
        <input type="number" id="filterMinBet" class="form-control form-control-sm bg-black text-white border-secondary font-mono" placeholder="Min Bet" style="width: 120px;">
        <button class="btn btn-sm btn-cyan fw-bold px-4" onclick="fetchLive()" style="background: #00f3ff; color: #000;">APPLY</button>
    </div>
    <div class="badge bg-success bg-opacity-20 text-success border border-success px-4 py-2 mt-2 mt-md-0 rounded-pill d-flex align-items-center gap-2 shadow-[0_0_15px_rgba(34,197,94,0.3)]">
        <span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true" style="width: 10px; height: 10px;"></span>
        <span class="tracking-widest uppercase text-[10px] fw-black">LIVE WEBSOCKET SYNC</span>
    </div>
</div>

<!-- DATA TABLE -->
<div class="glass-card p-0 border-secondary border-opacity-50 overflow-hidden">
    <div class="table-responsive hide-scrollbar" style="max-height: 65vh;">
        <table class="table table-dark table-hover align-middle mb-0 text-sm border-secondary">
            <thead class="sticky-top bg-black z-10 shadow-sm">
                <tr class="text-gray-400 text-uppercase tracking-widest" style="font-size: 0.65rem;">
                    <th class="ps-4" style="width: 120px;">Timestamp</th>
                    <th>Player Target</th>
                    <th>Node Location</th>
                    <th class="text-end">Bet Size</th>
                    <th class="text-center">Hash Result</th>
                    <th class="text-end pe-4">Yield</th>
                </tr>
            </thead>
            <tbody id="live-feed-body" class="font-mono text-xs">
                <tr><td colspan="6" class="text-center text-cyan-500 py-5 animate-pulse tracking-widest"><i class="bi bi-cpu me-2"></i>ESTABLISHING CONNECTION...</td></tr>
            </tbody>
        </table>
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
            // Using the router's current path to trigger the AJAX block
            const res = await fetch(`?route=live&ajax_fetch=1&type=${type}&min_bet=${minBet}`);
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
        document.getElementById('kpi-spins').innerText = Number(kpi.spins_5m).toLocaleString();
        document.getElementById('kpi-vol').innerText = Number(kpi.vol_5m).toLocaleString();
        
        const rtp = kpi.vol_5m > 0 ? ((kpi.pay_5m / kpi.vol_5m) * 100).toFixed(1) : 0;
        const rtpEl = document.getElementById('kpi-rtp');
        rtpEl.innerText = rtp + '%';
        
        if(rtp > 100) rtpEl.className = "fw-black text-danger mb-0 font-mono lh-1 drop-shadow-[0_0_10px_red]";
        else if (rtp > 90) rtpEl.className = "fw-black text-warning mb-0 font-mono lh-1";
        else rtpEl.className = "fw-black text-success mb-0 font-mono lh-1";
    }

    function updateTable(logs) {
        const tbody = document.getElementById('live-feed-body');
        if (logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-gray-500 py-8 italic">Silence on the network. No matching events.</td></tr>';
            return;
        }

        const html = logs.map(log => `
            <tr class="transition-colors hover:bg-white/5 ${log.row_class}">
                <td class="text-gray-500 ps-4">${log.formatted_time}</td>
                <td>
                    <a href="?route=players/details&id=${log.user_id}" class="text-decoration-none text-cyan-400 fw-bold hover:text-white transition-colors">
                        <i class="bi bi-person me-1 text-gray-600"></i>${log.username}
                    </a>
                </td>
                <td>
                    <span class="bg-black border border-white/10 px-2 py-1 rounded text-gray-300">
                        ${log.island_name ?? 'Lobby'} <span class="text-gray-500">|</span> <span class="text-info">#${log.machine_number ?? '?'}</span>
                    </span>
                </td>
                <td class="text-end text-white">${log.formatted_bet}</td>
                <td class="text-center text-gray-400" style="letter-spacing: 3px;">
                    [ ${log.result_display} ]
                </td>
                <td class="text-end pe-4 ${log.win_class}">
                    ${log.win > 0 ? '+' + log.formatted_win : '-'}
                </td>
            </tr>
        `).join('');

        tbody.innerHTML = html;
    }

    // Start Polling (Every 2 Seconds for real-time feel)
    fetchLive(); 
    pollInterval = setInterval(fetchLive, 2000);
</script>

<style>
    .hide-scrollbar::-webkit-scrollbar { display: none; }
    .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>