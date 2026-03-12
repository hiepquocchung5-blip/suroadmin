<?php
if (!defined('ADMIN_BASE_PATH')) exit('Direct access denied');

// 1. AJAX HANDLER
if (isset($_GET['ajax_fetch'])) {
    $limit = 50;
    $minBet = isset($_GET['min_bet']) ? (int)$_GET['min_bet'] : 0;
    $type = $_GET['type'] ?? 'all';
    $where = "1";
    if ($minBet > 0) $where .= " AND g.bet >= $minBet";
    if ($type === 'wins') $where .= " AND g.win > 0";
    if ($type === 'big_wins') $where .= " AND g.win >= (g.bet * 10)";

    try {
        $sql = "SELECT g.*, u.username, m.machine_number, i.name as island_name
                FROM game_logs g JOIN users u ON g.user_id = u.id LEFT JOIN machines m ON g.machine_id = m.id LEFT JOIN islands i ON m.island_id = i.id
                WHERE $where ORDER BY g.created_at DESC LIMIT $limit";
        $logs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $kpi = $pdo->query("SELECT COUNT(*) as spins_5m, SUM(bet) as vol_5m, SUM(win) as pay_5m FROM game_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetch(PDO::FETCH_ASSOC);

        foreach ($logs as &$log) {
            $log['formatted_time'] = date('H:i:s.v', strtotime($log['created_at']));
            $resArray = json_decode($log['result']);
            $log['result_display'] = is_array($resArray) ? implode(' ', $resArray) : $log['result'];
            $isWin = $log['win'] > 0;
            $isBigWin = $isWin && ($log['win'] >= $log['bet'] * 10);
            
            $log['row_class'] = $isBigWin ? 'bg-warning bg-opacity-10 border-start border-4 border-warning shadow-[inset_0_0_10px_rgba(234,179,8,0.2)]' : ($isWin ? 'bg-success bg-opacity-10 border-start border-2 border-success' : 'border-start border-2 border-transparent opacity-75');
            $log['win_class'] = $isWin ? ($isBigWin ? 'fw-black text-warning animate-pulse' : 'text-success fw-bold') : 'text-muted';
            $log['formatted_bet'] = number_format($log['bet']);
            $log['formatted_win'] = number_format($log['win']);
        }
        echo json_encode(['status' => 'success', 'logs' => $logs, 'kpi' => $kpi]);
    } catch (Exception $e) { echo json_encode(['status' => 'error']); }
    exit;
}

$pageTitle = "Matrix Telemetry Feed";
requireRole(['GOD', 'FINANCE']);
require_once ADMIN_BASE_PATH . '/layout/main.php';
?>

<div class="row g-3 mb-4">
    <!-- TELEMETRY KPIS -->
    <div class="col-md-4">
        <div class="glass-card border-info p-4 d-flex align-items-center gap-4">
            <div class="bg-info bg-opacity-20 p-3 rounded-circle text-info shadow-[0_0_15px_rgba(13,202,240,0.4)]"><i class="bi bi-cpu fs-3"></i></div>
            <div>
                <h6 class="text-info small fw-bold tracking-widest text-uppercase mb-1">Server Load (5m)</h6>
                <h3 class="fw-black text-white font-mono m-0" id="kpi-spins">0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-card border-success p-4 d-flex align-items-center gap-4">
            <div class="bg-success bg-opacity-20 p-3 rounded-circle text-success shadow-[0_0_15px_rgba(34,197,94,0.4)]"><i class="bi bi-cash-stack fs-3"></i></div>
            <div>
                <h6 class="text-success small fw-bold tracking-widest text-uppercase mb-1">Capital Inflow (5m)</h6>
                <h3 class="fw-black text-white font-mono m-0" id="kpi-vol">0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-card border-purple-500 p-4 d-flex align-items-center gap-4" style="border-color: #a855f7;">
            <div class="bg-purple-900 bg-opacity-40 p-3 rounded-circle text-purple-400 shadow-[0_0_15px_rgba(168,85,247,0.4)]"><i class="bi bi-graph-up-arrow fs-3"></i></div>
            <div>
                <h6 class="text-purple-400 small fw-bold tracking-widest text-uppercase mb-1">Active RTP (5m)</h6>
                <h3 class="fw-black text-white font-mono m-0" id="kpi-rtp">0%</h3>
            </div>
        </div>
    </div>
</div>

<div class="glass-card p-0 border-secondary overflow-hidden border border-opacity-50">
    <div class="bg-black bg-opacity-80 p-3 border-b border-white border-opacity-10 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-success bg-opacity-20 text-success border border-success rounded-pill px-3 py-2 animate-pulse font-mono tracking-widest">
                <i class="bi bi-record-circle-fill"></i> SYNCING
            </span>
            <select id="filterType" class="form-select form-select-sm bg-dark text-white border-secondary font-mono fw-bold rounded-pill w-auto px-4">
                <option value="all">RAW STREAM</option>
                <option value="wins">WINS ONLY</option>
                <option value="big_wins">BIG WINS (>10x)</option>
            </select>
        </div>
        <button class="btn btn-sm btn-info fw-black rounded-pill px-4 shadow-[0_0_10px_cyan]" onclick="fetchLive()">FORCE SYNC</button>
    </div>

    <div class="table-responsive hide-scrollbar bg-black" style="max-height: 60vh;">
        <table class="table table-dark table-hover align-middle mb-0 text-sm font-mono">
            <thead class="sticky-top bg-black z-10 shadow-sm border-secondary border-b">
                <tr class="text-gray-500 text-uppercase tracking-widest text-[10px]">
                    <th class="ps-4">Timestamp</th>
                    <th>Node ID</th>
                    <th class="text-end">Bet</th>
                    <th class="text-center">Hash Result</th>
                    <th class="text-end pe-4">Yield</th>
                </tr>
            </thead>
            <tbody id="live-feed-body" class="text-xs">
                <tr><td colspan="5" class="text-center text-cyan-500 py-5 animate-pulse tracking-widest"><i class="bi bi-terminal me-2"></i>DECRYPTING STREAM...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    let isFetching = false;
    async function fetchLive() {
        if(isFetching) return;
        isFetching = true;
        const type = document.getElementById('filterType').value;
        try {
            const res = await fetch(`index.php?route=live&ajax_fetch=1&type=${type}`);
            const data = await res.json();
            if (data.status === 'success') {
                updateKPI(data.kpi);
                updateTable(data.logs);
            }
        } catch (e) { console.error("Stream lost."); } 
        finally { isFetching = false; }
    }

    function updateKPI(kpi) {
        document.getElementById('kpi-spins').innerText = Number(kpi.spins_5m).toLocaleString();
        document.getElementById('kpi-vol').innerText = Number(kpi.vol_5m).toLocaleString();
        const rtp = kpi.vol_5m > 0 ? ((kpi.pay_5m / kpi.vol_5m) * 100).toFixed(1) : 0;
        const rtpEl = document.getElementById('kpi-rtp');
        rtpEl.innerText = rtp + '%';
        if(rtp > 100) rtpEl.className = "fw-black text-danger m-0 drop-shadow-[0_0_10px_red]";
        else if (rtp > 90) rtpEl.className = "fw-black text-warning m-0";
        else rtpEl.className = "fw-black text-success m-0";
    }

    function updateTable(logs) {
        const tbody = document.getElementById('live-feed-body');
        if (logs.length === 0) { tbody.innerHTML = '<tr><td colspan="5" class="text-center text-gray-600 py-8">No matching packets.</td></tr>'; return; }
        
        tbody.innerHTML = logs.map(log => `
            <tr class="transition-colors hover:bg-white hover:bg-opacity-5 ${log.row_class}">
                <td class="text-gray-600 ps-4 text-[10px]">${log.formatted_time}</td>
                <td>
                    <span class="text-white fw-bold">${log.username}</span><br/>
                    <span class="text-info text-[9px]">${log.island_name} | Unit #${log.machine_number}</span>
                </td>
                <td class="text-end text-gray-400">${log.formatted_bet}</td>
                <td class="text-center text-gray-500 tracking-[0.3em]">[ ${log.result_display} ]</td>
                <td class="text-end pe-4 ${log.win_class} fs-6">${log.win > 0 ? '+' + log.formatted_win : '0'}</td>
            </tr>
        `).join('');
    }

    fetchLive(); 
    setInterval(fetchLive, 2000); // 2 second rapid refresh
</script>

<?php require_once ADMIN_BASE_PATH . '/layout/footer.php'; ?>