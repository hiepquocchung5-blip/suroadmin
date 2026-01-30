<?php
/**
 * Live Game Monitor - Dashboard Module (v3.1)
 * Optimized for God Mode UI/UX with integrated historical results and live symbol decoding.
 */

// 1. AJAX HANDLER (Responds to polling requests)
if (isset($_GET['ajax_fetch'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

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
        // Fetch Live Logs - Decoding the Result JSON for visual display
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
                IFNULL(SUM(bet), 0) as vol_5m, 
                IFNULL(SUM(win), 0) as pay_5m 
            FROM game_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ")->fetch(PDO::FETCH_ASSOC);

        // Format Data for Frontend
        foreach ($logs as &$log) {
            $log['formatted_time'] = date('H:i:s', strtotime($log['created_at']));
            
            // Handle Slot Result Decoding
            $resArray = json_decode($log['result']);
            $log['result_display'] = is_array($resArray) ? implode(' ', $resArray) : $log['result'];
            
            // Logic for Row Highlighting (Big Wins / Normal Wins)
            $isWin = $log['win'] > 0;
            $isBigWin = $isWin && ($log['win'] >= $log['bet'] * 10);
            
            $log['row_class'] = $isBigWin ? 'bg-amber-50' : ($isWin ? 'bg-emerald-50/30' : '');
            $log['win_class'] = $isWin ? ($isBigWin ? 'text-amber-600 font-black' : 'text-emerald-600 font-bold') : 'text-slate-400 font-medium';
            
            $log['formatted_bet'] = number_format($log['bet']);
            $log['formatted_win'] = number_format($log['win']);
        }

        echo json_encode(['status' => 'success', 'logs' => $logs, 'kpi' => $kpi]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}
?>

<!-- LIVE MONITOR HEADER -->
<div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-black text-slate-800 tracking-tight uppercase">Live Game Engine</h1>
        <p class="text-sm text-slate-500 font-medium">Real-time surveillance of active sessions and game outcomes.</p>
    </div>
    <div class="flex items-center space-x-3 bg-white px-5 py-2.5 rounded-2xl border border-slate-200 shadow-sm">
        <div id="connection-indicator" class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></div>
        <span class="text-[10px] font-black text-slate-700 uppercase tracking-widest">System Link Active</span>
    </div>
</div>

<!-- KPI GRID (Last 5 Minutes Performance) -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-6 rounded-[2rem] border border-slate-200 shadow-sm relative overflow-hidden group">
        <div class="absolute right-0 top-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
            <i data-lucide="zap" class="w-16 h-16"></i>
        </div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Velocity (5m)</p>
        <h3 class="text-3xl font-black text-slate-900" id="kpi-spins">0</h3>
        <p class="text-[10px] font-bold text-slate-400 mt-1 uppercase">Spins processed</p>
    </div>
    <div class="bg-white p-6 rounded-[2rem] border border-slate-200 shadow-sm relative overflow-hidden group">
        <div class="absolute right-0 top-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
            <i data-lucide="banknote" class="w-16 h-16"></i>
        </div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Volume (5m)</p>
        <h3 class="text-3xl font-black text-slate-900" id="kpi-vol">0</h3>
        <p class="text-[10px] font-bold text-slate-400 mt-1 uppercase">MMK Inbound</p>
    </div>
    <div class="bg-white p-6 rounded-[2rem] border border-slate-200 shadow-sm relative overflow-hidden group">
        <div class="absolute right-0 top-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
            <i data-lucide="percent" class="w-16 h-16"></i>
        </div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Return Rate (5m)</p>
        <h3 class="text-3xl font-black text-slate-900" id="kpi-rtp">0%</h3>
        <p class="text-[10px] font-bold text-slate-400 mt-1 uppercase">Real-time RTP</p>
    </div>
</div>

<!-- CONTROL BAR -->
<div class="bg-slate-900 p-5 rounded-[2rem] border border-slate-800 shadow-2xl mb-8 flex flex-wrap items-center gap-6">
    <div class="flex items-center space-x-3 text-white pr-6 border-r border-slate-800">
        <i data-lucide="sliders-horizontal" class="w-5 h-5 text-blue-500"></i>
        <span class="text-xs font-black uppercase tracking-widest">Surveillance Filters</span>
    </div>
    
    <div class="flex flex-wrap items-center gap-4">
        <select id="filterType" class="bg-slate-800 border-none text-white text-[11px] font-bold rounded-xl px-5 py-2.5 outline-none focus:ring-2 focus:ring-blue-500 transition-all cursor-pointer">
            <option value="all">Full Activity Stream</option>
            <option value="wins">Player Wins Only</option>
            <option value="big_wins">Jackpots & Big Wins</option>
        </select>
        
        <div class="relative group">
            <i data-lucide="coins" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 group-focus-within:text-blue-500 transition-colors"></i>
            <input type="number" id="filterMinBet" placeholder="Min Bet (MMK)" 
                class="bg-slate-800 border-none text-white text-[11px] font-bold rounded-xl pl-11 pr-5 py-2.5 w-40 outline-none focus:ring-2 focus:ring-blue-500 transition-all placeholder-slate-600">
        </div>

        <button onclick="fetchLive()" class="bg-blue-600 hover:bg-blue-500 text-white text-[11px] font-black px-8 py-2.5 rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-blue-600/20 active:scale-95">
            Manual Refresh
        </button>
    </div>
</div>

<!-- LIVE STREAM TABLE -->
<div class="bg-white rounded-[2.5rem] border border-slate-200 shadow-sm overflow-hidden mb-12">
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-slate-50 text-slate-400 text-[10px] font-black uppercase tracking-widest border-b border-slate-100">
                <tr>
                    <th class="px-8 py-5">Timestamp</th>
                    <th class="px-8 py-5">Player Entity</th>
                    <th class="px-8 py-5">Origin / Machine</th>
                    <th class="px-8 py-5 text-right">Stake</th>
                    <th class="px-8 py-5 text-center">Reel Outcome</th>
                    <th class="px-8 py-5 text-right">Settlement</th>
                </tr>
            </thead>
            <tbody id="live-feed-body" class="divide-y divide-slate-100 font-medium">
                <tr>
                    <td colspan="6" class="px-8 py-24 text-center">
                        <div class="flex flex-col items-center">
                            <div class="w-10 h-10 border-[3px] border-blue-600 border-t-transparent rounded-full animate-spin mb-6"></div>
                            <span class="text-xs font-black text-slate-400 uppercase tracking-[0.2em]">Synchronizing Stream...</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    let isFetching = false;
    let pollInterval;

    /**
     * Fetch Live Data from Server
     */
    async function fetchLive() {
        if(isFetching) return;
        isFetching = true;

        const type = document.getElementById('filterType').value;
        const minBet = document.getElementById('filterMinBet').value;
        const indicator = document.getElementById('connection-indicator');

        try {
            // Updated fetch URL ensuring module routing is correct
            const res = await fetch(`index.php?module=live&ajax_fetch=1&type=${type}&min_bet=${minBet}`);
            const data = await res.json();

            if (data.status === 'success') {
                updateKPI(data.kpi);
                updateTable(data.logs);
                if(indicator) indicator.className = "w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse";
            }
        } catch (e) {
            console.error("Live Feed Disconnected:", e);
            if(indicator) indicator.className = "w-2.5 h-2.5 bg-red-500 rounded-full";
        } finally {
            isFetching = false;
        }
    }

    /**
     * Update Dashboard Stats
     */
    function updateKPI(kpi) {
        document.getElementById('kpi-spins').innerText = kpi.spins_5m;
        document.getElementById('kpi-vol').innerText = Number(kpi.vol_5m || 0).toLocaleString() + ' MMK';
        
        const rtp = kpi.vol_5m > 0 ? ((kpi.pay_5m / kpi.vol_5m) * 100).toFixed(1) : 0;
        const rtpEl = document.getElementById('kpi-rtp');
        rtpEl.innerText = rtp + '%';
        
        // Dynamic status coloring based on House Edge
        if(rtp > 100) rtpEl.className = "text-3xl font-black text-red-500";
        else if (rtp > 90) rtpEl.className = "text-3xl font-black text-amber-500";
        else rtpEl.className = "text-3xl font-black text-emerald-500";
    }

    /**
     * Refresh the Results Table
     */
    function updateTable(logs) {
        const tbody = document.getElementById('live-feed-body');
        if (logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-8 py-16 text-center text-slate-400 font-black uppercase tracking-widest text-[10px]">No activity signals found in range.</td></tr>';
            return;
        }

        const html = logs.map(log => `
            <tr class="transition-all duration-300 ${log.row_class} hover:bg-slate-50/80">
                <td class="px-8 py-5 text-xs font-bold text-slate-400 font-mono tracking-tighter">${log.formatted_time}</td>
                <td class="px-8 py-5">
                    <span class="text-sm font-black text-slate-800 tracking-tight">${log.username}</span>
                </td>
                <td class="px-8 py-5">
                    <div class="flex items-center space-x-2">
                        <span class="px-2 py-0.5 bg-slate-900 text-white text-[9px] font-black rounded uppercase">${log.island_name || 'Lobby'}</span>
                        <span class="text-[10px] font-bold text-slate-400 tracking-widest">#${log.machine_number || '00'}</span>
                    </div>
                </td>
                <td class="px-8 py-5 text-right text-sm font-black text-slate-900">${log.formatted_bet}</td>
                <td class="px-8 py-5 text-center">
                    <div class="flex items-center justify-center space-x-2 font-mono text-[11px] font-black text-slate-400 tracking-[0.3em] uppercase">
                        ${log.result_display}
                    </div>
                </td>
                <td class="px-8 py-5 text-right ${log.win_class}">
                    ${log.win > 0 ? '+' + log.formatted_win : '-'}
                </td>
            </tr>
        `).join('');

        tbody.innerHTML = html;
        if(window.lucide) lucide.createIcons();
    }

    // Auto-start Polling
    document.addEventListener('DOMContentLoaded', () => {
        fetchLive(); 
        pollInterval = setInterval(fetchLive, 2500); // 2.5s for stability
    });
</script>