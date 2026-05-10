<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

/**
 * Module Configuration
 */
function expiredproducts_config() {
    return [
        'name' => 'Expired Products Manager',
        'description' => 'Advanced manager with analytics, charts, bulk actions, and a professional dark mode interface.',
        'author' => 'Hostinoz.com',
        'language' => 'english',
        'version' => '2.0',
        'fields' => [
            'logo' => [
                'FriendlyName' => 'Module Logo',
                'Type' => 'text',
                'Size' => '50',
                'Default' => 'modules/expiredproducts/logo.png',
                'Description' => 'URL/path to the logo (Recommended: 100x100px)',
            ],
            'expiry_warning_days' => [
                'FriendlyName' => 'Expiry Warning Days',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '7',
                'Description' => 'Days before expiry to flag as "Expiring Soon"',
            ],
            'items_per_page' => [
                'FriendlyName' => 'Items Per Page',
                'Type' => 'dropdown',
                'Options' => '10,25,50,100,All',
                'Default' => '25',
                'Description' => 'Default pagination size',
            ],
            'theme' => [
                'FriendlyName' => 'Theme',
                'Type' => 'dropdown',
                'Options' => 'light,dark',
                'Default' => 'light',
                'Description' => 'Select UI Theme',
            ],
        ],
    ];
}

/**
 * Admin Area Output
 */
function expiredproducts_output($vars) {
    // Process Bulk Actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        $ids = $_POST['selected_ids'] ?? [];
        if (empty($ids)) {
            echo '<div style="padding:15px;background:#ef4444;color:#fff;border-radius:8px;margin-bottom:15px;">No items selected.</div>';
        } else {
            if ($action === 'email') {
                foreach ($ids as $id) {
                    $hosting = Capsule::table('tblhosting')->find($id);
                    if ($hosting) {
                        localAPI('SendEmail', [
                            'messagename' => 'Service Overdue Reminder', 
                            'id' => $hosting->userid, 
                            'customtype' => 'product', 
                            'customsubject' => 'Service Expiration Reminder', 
                            'custommessage' => 'This is a reminder that your service is expiring soon or has already expired. Please renew to avoid service interruption.'
                        ]);
                    }
                }
                echo '<div style="padding:15px;background:#22c55e;color:#fff;border-radius:8px;margin-bottom:15px;">Reminder emails sent successfully to selected clients.</div>';
            } elseif ($action === 'terminate') {
                foreach ($ids as $id) {
                    localAPI('ModuleTerminate', ['accountid' => $id]);
                }
                echo '<div style="padding:15px;background:#22c55e;color:#fff;border-radius:8px;margin-bottom:15px;">Selected services have been terminated.</div>';
            }
        }
    }

    $warning_days = (int)($vars['expiry_warning_days'] ?? 7);
    $theme = $vars['theme'] ?? 'light';
    $logo = $vars['logo'] ?? '';
    $itemsPerPage = $vars['items_per_page'] ?? '25';

    // Fetch Data
    $products = Capsule::table('tblhosting')
        ->whereIn('domainstatus', ['Active', 'Suspended'])
        ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
        ->join('tblclients', 'tblhosting.userid', '=', 'tblclients.id')
        ->select(
            'tblhosting.*', 
            'tblproducts.name as package_name', 
            'tblclients.firstname', 
            'tblclients.lastname'
        )
        ->get();

    $currentDate = time();
    $data = [];
    $kpis = [
        'total' => 0, 
        'expiring_soon' => 0, 
        'recently_expired' => 0, 
        'long_expired' => 0, 
        'suspended' => 0, 
        'revenue_at_risk' => 0
    ];
    
    // Initialize chart timeline for next 30 days
    $chartTimeline = [];
    for($i = 0; $i <= 30; $i++) {
        $chartTimeline[date('Y-m-d', strtotime("+$i days"))] = 0;
    }

    // Process Data
    foreach ($products as $p) {
        $nextDue = strtotime($p->nextduedate);
        $days = floor(($nextDue - $currentDate) / 86400);
        
        $cat = 'Active';
        if ($days < 0 && $days >= -$warning_days) $cat = 'Recently Expired';
        elseif ($days < -$warning_days) $cat = 'Long Expired';
        elseif ($days >= 0 && $days <= $warning_days) $cat = 'Expiring Soon';

        $p->days_left = $days;
        $p->category = $cat;
        
        // Update KPIs
        $kpis['total']++;
        if ($cat == 'Expiring Soon') { 
            $kpis['expiring_soon']++; 
            $kpis['revenue_at_risk'] += $p->amount; 
        }
        if ($cat == 'Recently Expired') $kpis['recently_expired']++;
        if ($cat == 'Long Expired') $kpis['long_expired']++;
        if ($p->domainstatus == 'Suspended') $kpis['suspended']++;
        
        // Update Timeline Data
        if ($days >= 0 && $days <= 30) {
            $dateStr = date('Y-m-d', $nextDue);
            if(isset($chartTimeline[$dateStr])) {
                $chartTimeline[$dateStr]++;
            }
        }
        
        $data[] = $p;
    }

    // Theme Variables
    $themeColors = $theme == 'dark' ? [
        'bg' => '#0f172a', 
        'card' => 'rgba(30,41,59,0.8)', 
        'text' => '#f8fafc', 
        'muted' => '#94a3b8', 
        'border' => '#334155'
    ] : [
        'bg' => '#f8fafc', 
        'card' => '#ffffff', 
        'text' => '#0f172a', 
        'muted' => '#64748b', 
        'border' => '#e2e8f0'
    ];
    
    ?>
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    
    :root {
        --bg: <?= $themeColors['bg'] ?>; 
        --card: <?= $themeColors['card'] ?>; 
        --text: <?= $themeColors['text'] ?>; 
        --muted: <?= $themeColors['muted'] ?>; 
        --border: <?= $themeColors['border'] ?>;
    }
    
    .epm-wrap { 
        font-family: 'Inter', sans-serif; 
        background: var(--bg); 
        color: var(--text); 
        padding: 25px; 
        border-radius: 16px; 
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }
    .epm-wrap * { box-sizing: border-box; }
    
    .epm-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 15px;}
    .epm-header h2 { margin: 0; font-weight: 600; font-size: 24px; color: var(--text); }
    
    .epm-card { 
        background: var(--card); 
        backdrop-filter: blur(12px); 
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid var(--border); 
        border-radius: 12px; 
        padding: 20px; 
        margin-bottom: 25px; 
        animation: fadeSlideUp 0.4s ease-out;
    }
    
    @keyframes fadeSlideUp {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .epm-kpi-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); 
        gap: 15px; 
        margin-bottom: 25px; 
    }
    .epm-kpi { 
        text-align: center; 
        padding: 20px 15px; 
        border-radius: 12px; 
        background: rgba(128,128,128,0.05); 
        border: 1px solid var(--border); 
        transition: transform 0.2s ease, box-shadow 0.2s ease; 
    }
    .epm-kpi:hover { 
        transform: translateY(-5px); 
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    }
    .epm-kpi-val { font-size: 28px; font-weight: 700; margin-bottom: 8px; color: #38bdf8; line-height: 1;}
    .epm-kpi-title { font-size: 13px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 500;}
    
    .epm-charts { 
        display: grid; 
        grid-template-columns: 1fr 2fr; 
        gap: 20px; 
    }
    @media (max-width: 900px) { .epm-charts { grid-template-columns: 1fr; } }
    
    .epm-toolbar { 
        display: flex; 
        gap: 12px; 
        margin-bottom: 20px; 
        flex-wrap: wrap; 
        align-items: center;
    }
    .epm-input { 
        background: transparent; 
        border: 1px solid var(--border); 
        color: var(--text); 
        padding: 10px 15px; 
        border-radius: 8px; 
        font-family: inherit;
        outline: none;
        transition: border-color 0.2s;
    }
    .epm-input:focus { border-color: #38bdf8; }
    
    .epm-btn { 
        background: #3b82f6; 
        color: #fff; 
        border: none; 
        padding: 10px 18px; 
        border-radius: 8px; 
        cursor: pointer; 
        font-weight: 500;
        transition: background 0.2s; 
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .epm-btn:hover { background: #2563eb; }
    .epm-btn-danger { background: #ef4444; }
    .epm-btn-danger:hover { background: #dc2626; }
    .epm-btn-secondary { background: rgba(128,128,128,0.1); color: var(--text); border: 1px solid var(--border); }
    .epm-btn-secondary:hover { background: rgba(128,128,128,0.15); }
    
    .epm-table-wrap { overflow-x: auto; }
    table.epm-table { width: 100%; border-collapse: collapse; white-space: nowrap; }
    .epm-table th, .epm-table td { 
        padding: 14px 15px; 
        text-align: left; 
        border-bottom: 1px solid var(--border); 
        font-size: 14px; 
    }
    .epm-table th { 
        color: var(--muted); 
        font-weight: 600;
        cursor: pointer; 
        user-select: none;
    }
    .epm-table th:hover { color: var(--text); }
    .epm-table tr { transition: background 0.1s; }
    .epm-table tbody tr { animation: fadeSlideUp 0.3s ease-out; animation-fill-mode: both; }
    .epm-table tbody tr:nth-child(1) { animation-delay: 0.05s; }
    .epm-table tbody tr:nth-child(2) { animation-delay: 0.1s; }
    .epm-table tbody tr:nth-child(3) { animation-delay: 0.15s; }
    .epm-table tbody tr:nth-child(4) { animation-delay: 0.2s; }
    .epm-table tbody tr:nth-child(5) { animation-delay: 0.25s; }
    .epm-table tr:hover { background: rgba(128,128,128,0.05); }
    
    .badge { 
        padding: 5px 10px; 
        border-radius: 99px; 
        font-size: 12px; 
        font-weight: 600; 
        display: inline-block;
    }
    .badge-Active { background: rgba(34,197,94,0.15); color: #4ade80; }
    .badge-Suspended { background: rgba(239,68,68,0.15); color: #f87171; }
    .badge-cat-Expiring { background: rgba(234,179,8,0.15); color: #facc15; }
    .badge-cat-Recently { background: rgba(249,115,22,0.15); color: #fb923c; }
    .badge-cat-Long { background: rgba(239,68,68,0.15); color: #f87171; }
    .badge-cat-Active { background: rgba(128,128,128,0.1); color: var(--muted); }
    
    .link { color: #38bdf8; text-decoration: none; font-weight: 500;}
    .link:hover { text-decoration: underline; }
    
    @media print { 
        body * { visibility: hidden; }
        .epm-wrap, .epm-wrap * { visibility: visible; }
        .epm-wrap { position: absolute; left: 0; top: 0; width: 100%; background: #fff; color: #000; box-shadow: none; }
        .epm-toolbar, .epm-charts, .epm-btn, input[type="checkbox"] { display: none !important; }
        .epm-table th, .epm-table td { color: #000; border-color: #ccc; }
        .badge { border: 1px solid #000; color: #000 !important; background: transparent !important; }
    }
    </style>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <div class="epm-wrap">
        <div class="epm-header">
            <div style="display: flex; align-items: center; gap: 15px;">
                <?php if ($logo): ?>
                <img src="<?= htmlspecialchars($logo) ?>" alt="Logo" style="max-height: 40px; border-radius: 4px;">
                <?php endif; ?>
                <h2>Expired Products Manager Pro</h2>
            </div>
            <div>v2.0</div>
        </div>
        
        <div class="epm-kpi-grid">
            <div class="epm-kpi">
                <div class="epm-kpi-val"><?= $kpis['total'] ?></div>
                <div class="epm-kpi-title">Total Tracked</div>
            </div>
            <div class="epm-kpi">
                <div class="epm-kpi-val" style="color:#facc15"><?= $kpis['expiring_soon'] ?></div>
                <div class="epm-kpi-title">Expiring Soon</div>
            </div>
            <div class="epm-kpi">
                <div class="epm-kpi-val" style="color:#fb923c"><?= $kpis['recently_expired'] ?></div>
                <div class="epm-kpi-title">Recently Expired</div>
            </div>
            <div class="epm-kpi">
                <div class="epm-kpi-val" style="color:#f87171"><?= $kpis['long_expired'] ?></div>
                <div class="epm-kpi-title">Long Expired</div>
            </div>
            <div class="epm-kpi">
                <div class="epm-kpi-val" style="color:#94a3b8"><?= $kpis['suspended'] ?></div>
                <div class="epm-kpi-title">Suspended</div>
            </div>
            <div class="epm-kpi">
                <div class="epm-kpi-val" style="color:#4ade80">$<?= number_format($kpis['revenue_at_risk'], 2) ?></div>
                <div class="epm-kpi-title">Revenue At Risk</div>
            </div>
        </div>

        <div class="epm-charts">
            <div class="epm-card">
                <canvas id="chartDoughnut"></canvas>
            </div>
            <div class="epm-card">
                <canvas id="chartBar"></canvas>
            </div>
        </div>

        <form method="POST" id="epmForm">
        <div class="epm-card">
            <div class="epm-toolbar">
                <input type="text" id="searchInput" class="epm-input" placeholder="Search client, domain..." style="flex:1; min-width: 200px;">
                <select id="statusFilter" class="epm-input">
                    <option value="">All Statuses</option>
                    <option value="Active">Active</option>
                    <option value="Suspended">Suspended</option>
                </select>
                <button type="button" class="epm-btn epm-btn-secondary" onclick="exportCSV()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg> CSV
                </button>
                <button type="button" class="epm-btn epm-btn-secondary" onclick="window.print()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg> PDF
                </button>
                <div style="margin-left:auto; display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" name="action" value="email" class="epm-btn" onclick="return confirm('Send reminder emails to selected clients?')">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg> Email Selected
                    </button>
                    <button type="submit" name="action" value="terminate" class="epm-btn epm-btn-danger" onclick="return confirm('WARNING: Are you sure you want to terminate the selected services? This action cannot be undone.')">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg> Terminate Selected
                    </button>
                </div>
            </div>

<?php
$groups = [
    'Expiring Soon' => ['color' => '#facc15', 'title' => 'Expiring Soon (Next '.$warning_days.' Days)'],
    'Recently Expired' => ['color' => '#fb923c', 'title' => 'Recently Expired (Last '.$warning_days.' Days)'],
    'Long Expired' => ['color' => '#f87171', 'title' => 'Long Expired'],
];

foreach ($groups as $groupKey => $groupConfig):
    $groupData = array_filter($data, function($r) use ($groupKey) { return $r->category === $groupKey; });
?>
            <div class="epm-category-section" style="border-left: 5px solid <?= $groupConfig['color'] ?>; margin-bottom: 30px; background: rgba(128,128,128,0.02); border-radius: 0 12px 12px 0; padding: 20px;">
                <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; display: flex; align-items: center; gap: 10px; color: var(--text);">
                    <?= $groupConfig['title'] ?>
                    <span class="badge" style="background: <?= $groupConfig['color'] ?>; color: #fff;"><?= count($groupData) ?></span>
                </h3>
                <div class="epm-table-wrap">
                    <table class="epm-table epm-data-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" onchange="toggleGroupCheckboxes(this)"></th>
                                <th onclick="sortTable(this, 1)">Client ↕</th>
                                <th onclick="sortTable(this, 2)">Package ↕</th>
                                <th onclick="sortTable(this, 3)">Domain ↕</th>
                                <th onclick="sortTable(this, 4)">Next Due ↕</th>
                                <th onclick="sortTable(this, 5)">Days Left ↕</th>
                                <th onclick="sortTable(this, 6)">Amount ↕</th>
                                <th onclick="sortTable(this, 7)">Status ↕</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($groupData)): ?>
                            <tr class="empty-row"><td colspan="8" style="text-align: center; padding: 20px; color: var(--muted);">No services in this category.</td></tr>
                            <?php else: foreach($groupData as $row): ?>
                            <tr>
                                <td><input type="checkbox" name="selected_ids[]" value="<?= $row->id ?>" class="row-chk"></td>
                                <td><a href="clientssummary.php?userid=<?= $row->userid ?>" class="link"><?= htmlspecialchars($row->firstname . ' ' . $row->lastname) ?></a></td>
                                <td><a href="clientshosting.php?userid=<?= $row->userid ?>&id=<?= $row->id ?>" class="link"><?= htmlspecialchars($row->package_name) ?></a></td>
                                <td><?= $row->domain ? htmlspecialchars($row->domain) : '<span style="color:var(--muted)">N/A</span>' ?></td>
                                <td><?= $row->nextduedate ?></td>
                                <td>
                                    <?php 
                                        if($row->days_left < 0) echo abs($row->days_left) . ' days ago';
                                        elseif($row->days_left == 0) echo 'Today';
                                        else echo $row->days_left . ' days';
                                    ?>
                                </td>
                                <td>$<?= number_format($row->amount, 2) ?></td>
                                <td class="status-cell"><span class="badge badge-<?= $row->domainstatus ?>"><?= $row->domainstatus ?></span></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
<?php endforeach; ?>
        </div>
        </form>
    </div>

    <script>
    // Initialize Charts
    document.addEventListener('DOMContentLoaded', function() {
        Chart.defaults.color = '<?= $themeColors['muted'] ?>';
        Chart.defaults.font.family = "'Inter', sans-serif";
        
        const doughnutCtx = document.getElementById('chartDoughnut').getContext('2d');
        new Chart(doughnutCtx, {
            type: 'doughnut',
            data: {
                labels: ['Expiring Soon', 'Recently Expired', 'Long Expired'],
                datasets: [{
                    data: [<?= $kpis['expiring_soon'] ?>, <?= $kpis['recently_expired'] ?>, <?= $kpis['long_expired'] ?>],
                    backgroundColor: ['#facc15', '#fb923c', '#f87171'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: { 
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    title: { display: true, text: 'Expiration Status Breakdown', color: '<?= $themeColors['text'] ?>', font: {size: 16} },
                    legend: { position: 'bottom' }
                },
                cutout: '70%'
            }
        });
        
        const barCtx = document.getElementById('chartBar').getContext('2d');
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($chartTimeline)) ?>,
                datasets: [{
                    label: 'Items Expiring',
                    data: <?= json_encode(array_values($chartTimeline)) ?>,
                    backgroundColor: '#38bdf8',
                    borderRadius: 4
                }]
            },
            options: { 
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    title: { display: true, text: 'Expirations in Next 30 Days', color: '<?= $themeColors['text'] ?>', font: {size: 16} },
                    legend: { display: false }
                }, 
                scales: { 
                    y: { beginAtZero: true, ticks: { stepSize: 1 } },
                    x: { ticks: { maxRotation: 45, minRotation: 45 } }
                } 
            }
        });
    });

    // Select All Checkboxes per table
    function toggleGroupCheckboxes(masterCheckbox) {
        let table = masterCheckbox.closest('table');
        table.querySelectorAll('.row-chk').forEach(c => c.checked = masterCheckbox.checked);
    }

    // Client-side Filtering
    function filterTable() {
        const s = document.getElementById('searchInput').value.toLowerCase();
        const st = document.getElementById('statusFilter').value.toLowerCase();
        
        document.querySelectorAll('.epm-data-table tbody tr').forEach(row => {
            if(row.classList.contains('empty-row')) return;
            const text = row.innerText.toLowerCase();
            const status = row.querySelector('.status-cell').innerText.toLowerCase();
            
            const match = text.includes(s) && (st === '' || status === st);
            row.style.display = match ? '' : 'none';
        });
    }

    document.getElementById('searchInput').addEventListener('keyup', filterTable);
    document.getElementById('statusFilter').addEventListener('change', filterTable);

    // Client-side Sorting
    function sortTable(th, n) {
        const table = th.closest("table");
        let rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
        switching = true; 
        dir = "asc"; 
        
        while (switching) {
            switching = false; 
            rows = table.querySelectorAll("tbody tr:not(.empty-row)");
            for (i = 0; i < (rows.length - 1); i++) {
                shouldSwitch = false;
                x = rows[i].getElementsByTagName("TD")[n];
                y = rows[i + 1].getElementsByTagName("TD")[n];
                
                let cmpX = x.innerText.toLowerCase();
                let cmpY = y.innerText.toLowerCase();
                
                if(n === 5 || n === 6) {
                    cmpX = parseFloat(cmpX.replace(/[^0-9.-]+/g,"")) || 0;
                    cmpY = parseFloat(cmpY.replace(/[^0-9.-]+/g,"")) || 0;
                }
                
                if (dir == "asc") { 
                    if (cmpX > cmpY) { shouldSwitch = true; break; } 
                } else if (dir == "desc") { 
                    if (cmpX < cmpY) { shouldSwitch = true; break; } 
                }
            }
            if (shouldSwitch) {
                rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                switching = true; 
                switchcount++;
            } else {
                if (switchcount == 0 && dir == "asc") { dir = "desc"; switching = true; }
            }
        }
    }

    // CSV Export
    function exportCSV() {
        const csv = [];
        
        // Add headers
        const headers = [];
        const headerCols = document.querySelector(".epm-data-table thead tr").children;
        for (let j = 1; j < headerCols.length; j++) {
            headers.push('"' + headerCols[j].innerText.replace(' ↕', '') + '"');
        }
        csv.push(headers.join(","));

        // Add filtered rows
        const visibleRows = document.querySelectorAll('.epm-data-table tbody tr');
        for (let i = 0; i < visibleRows.length; i++) {
            if(visibleRows[i].style.display !== 'none' && !visibleRows[i].classList.contains('empty-row')) {
                const row = [];
                const cols = visibleRows[i].querySelectorAll("td");
                for (let j = 1; j < cols.length; j++) {
                    let text = cols[j].innerText.replace(/"/g, '""').replace(/(\r\n|\n|\r)/gm, "");
                    row.push('"' + text + '"');
                }
                csv.push(row.join(","));
            }
        }
        
        const csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
        const downloadLink = document.createElement("a");
        downloadLink.download = "expired_products_report.csv";
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = "none";
        document.body.appendChild(downloadLink);
        downloadLink.click();
    }
    </script>
    <?php
}
