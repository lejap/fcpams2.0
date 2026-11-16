<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
auth_guard('ADMIN'); // Admin-only

// ── Filters ───────────────────────────────────────────────────────────────────
$f_from = isset($_GET['from']) ? sanitize($_GET['from']) : date('Y-m-01'); // Default: 1st of current month
$f_to   = isset($_GET['to'])   ? sanitize($_GET['to'])   : date('Y-m-d');  // Default: today
$f_page = isset($_GET['page_filter']) ? sanitize($_GET['page_filter']) : '';

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_from)) $f_from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_to))   $f_to   = date('Y-m-d');

$where_base = "WHERE DATE(visited_at) BETWEEN '$f_from' AND '$f_to'";
if ($f_page) $where_base .= " AND page = '" . $conn->real_escape_string($f_page) . "'";

// ── KPI Summary ───────────────────────────────────────────────────────────────
// Total page views (all rows in range)
$total_views = $conn->query("SELECT COUNT(*) c FROM visitor_logs $where_base")->fetch_assoc()['c'];

// Unique visitors (distinct session_key in range)
$unique_visitors = $conn->query("SELECT COUNT(DISTINCT session_key) c FROM visitor_logs $where_base")->fetch_assoc()['c'];

// Today's visitors
$today_str = date('Y-m-d');
$today_visits = $conn->query("SELECT COUNT(DISTINCT session_key) c FROM visitor_logs WHERE DATE(visited_at) = '$today_str'")->fetch_assoc()['c'];

// This month total
$month_start = date('Y-m-01');
$month_visits = $conn->query("SELECT COUNT(DISTINCT session_key) c FROM visitor_logs WHERE DATE(visited_at) BETWEEN '$month_start' AND '$today_str'")->fetch_assoc()['c'];

// All-time total
$alltime_visits = $conn->query("SELECT COUNT(DISTINCT session_key) c FROM visitor_logs")->fetch_assoc()['c'];

// ── Daily breakdown chart data ─────────────────────────────────────────────────
$daily_res = $conn->query(
    "SELECT DATE(visited_at) AS day,
            COUNT(*) AS views,
            COUNT(DISTINCT session_key) AS unique_v
     FROM visitor_logs
     $where_base
     GROUP BY DATE(visited_at)
     ORDER BY day ASC"
);
$chart_days = []; $chart_views = []; $chart_unique = [];
while ($r = $daily_res->fetch_assoc()) {
    $chart_days[]   = date('M d', strtotime($r['day']));
    $chart_views[]  = (int)$r['views'];
    $chart_unique[] = (int)$r['unique_v'];
}

// ── Top pages ─────────────────────────────────────────────────────────────────
$top_pages = $conn->query(
    "SELECT page,
            COUNT(*) AS views,
            COUNT(DISTINCT session_key) AS unique_v
     FROM visitor_logs
     $where_base
     GROUP BY page
     ORDER BY unique_v DESC
     LIMIT 10"
);

// ── Page filter options ───────────────────────────────────────────────────────
$pages_res = $conn->query("SELECT DISTINCT page FROM visitor_logs ORDER BY page ASC");

// ── Detail table ──────────────────────────────────────────────────────────────
$detail_res = $conn->query(
    "SELECT DATE(visited_at) AS visit_date,
            COUNT(*) AS total_views,
            COUNT(DISTINCT session_key) AS unique_visitors
     FROM visitor_logs
     $where_base
     GROUP BY DATE(visited_at)
     ORDER BY visit_date DESC"
);

$page_title = "Visitor Analytics";
include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<style>
/* ── Shared with other report pages ── */
.rpt-hero{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;}
.rpt-hero h1{font-size:2rem;margin-bottom:.2rem;color:#0e83b5;}
.rpt-hero p{color:#64748b;font-size:.9rem;}
.kpi-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.75rem;}
.kpi{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;padding:1.1rem 1.25rem;text-align:center;position:relative;overflow:hidden;}
.kpi-lbl{font-size:.65rem;text-transform:uppercase;color:#94a3b8;font-weight:700;letter-spacing:.05em;margin-bottom:.3rem;}
.kpi-val{font-size:2rem;font-weight:900;line-height:1;}
.kpi-icon{position:absolute;right:1rem;top:50%;transform:translateY(-50%);font-size:2.5rem;opacity:.07;}
.chart-box{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;padding:1.25rem;margin-bottom:1.5rem;}
.chart-box h4{font-size:.88rem;font-weight:700;color:#334155;margin-bottom:1rem;}
.chart-wrap{position:relative;height:280px;}
.filter-bar{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;padding:1.25rem 1.5rem;margin-bottom:1.5rem;display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;}
.filter-grp{display:flex;flex-direction:column;gap:.25rem;}
.filter-grp label{font-size:.68rem;font-weight:700;text-transform:uppercase;color:#94a3b8;letter-spacing:.04em;}
.filter-grp select,.filter-grp input{padding:.45rem .7rem;border:1px solid #e2e8f0;border-radius:.5rem;font-size:.85rem;color:#334155;background:#f8fafc;}
.btn-filter{padding:.48rem 1.1rem;background:#0e83b5;color:#fff;border:none;border-radius:.5rem;font-size:.85rem;font-weight:700;cursor:pointer;align-self:flex-end;}
.btn-filter:hover{background:#0a6a94;}
.btn-reset{padding:.48rem 1rem;background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;border-radius:.5rem;font-size:.85rem;font-weight:600;cursor:pointer;align-self:flex-end;text-decoration:none;display:inline-flex;align-items:center;gap:.35rem;}
.btn-reset:hover{background:#e2e8f0;}
.tbl-wrap{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;overflow:hidden;margin-bottom:1.5rem;}
.tbl-hd{background:#f8fafc;padding:.8rem 1.25rem;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;}
.rtbl{width:100%;border-collapse:collapse;font-size:.85rem;}
.rtbl th{background:#f1f5f9;padding:.7rem 1rem;text-align:left;font-size:.7rem;font-weight:700;text-transform:uppercase;color:#94a3b8;}
.rtbl td{padding:.7rem 1rem;border-bottom:1px solid #f1f5f9;color:#334155;vertical-align:middle;}
.rtbl tr:last-child td{border-bottom:none;}
.rtbl tr:hover td{background:#f8fafc;}
.two-col{display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;margin-bottom:1.5rem;}
.empty-note{text-align:center;padding:3rem 1rem;color:#94a3b8;font-size:.9rem;}
.empty-note i{font-size:2.5rem;margin-bottom:.75rem;display:block;}
.bar-mini{display:inline-block;height:8px;background:#0e83b5;border-radius:4px;min-width:4px;}
.date-range-badge{background:#dbeafe;color:#1d4ed8;padding:.2rem .65rem;border-radius:.4rem;font-size:.75rem;font-weight:700;}

@media(max-width:768px){.two-col{grid-template-columns:1fr;}.kpi-row{grid-template-columns:repeat(2,1fr);}}
@media print{
    @page{size:A4 landscape;margin:10mm 12mm;}
    .admin-sidebar,.admin-header,.filter-bar,.no-print{display:none!important;}
    .admin-wrapper,.admin-body,.admin-content{display:block!important;overflow:visible!important;height:auto!important;padding:0!important;margin:0!important;width:100%!important;position:static!important;}
    body,html{background:#fff!important;overflow:visible!important;height:auto!important;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
    .print-hd{display:block!important;}
    .tbl-wrap,div[style*="overflow"]{overflow:visible!important;max-height:none!important;height:auto!important;}
}
.print-hd{display:none;text-align:center;margin-bottom:1.25rem;padding-bottom:.75rem;border-bottom:2px solid #0e83b5;}
.print-hd h1{font-size:1.4rem;color:#0f172a;margin-bottom:.2rem;}
.print-hd p{font-size:.78rem;color:#64748b;}
</style>

<div class="fade-in">

<!-- Print Header (hidden on screen) -->
<div class="print-hd">
    <h1>FCPAMS — Visitor Analytics Report</h1>
    <p>Generated: <?php echo date('F d, Y \a\t h:i A'); ?> · Period: <?php echo date('M d, Y', strtotime($f_from)); ?> – <?php echo date('M d, Y', strtotime($f_to)); ?></p>
</div>

<!-- Hero -->
<div class="rpt-hero">
    <div>
        <h1><i class="fas fa-eye" style="font-size:1.6rem;"></i> Visitor Analytics</h1>
        <p>Track how many people visited the FCPAMS website, with daily breakdowns and page-level insight.</p>
    </div>
    <div class="no-print" style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;">
        <span class="date-range-badge"><i class="fas fa-calendar-alt"></i>
            <?php echo date('M d, Y', strtotime($f_from)); ?> – <?php echo date('M d, Y', strtotime($f_to)); ?>
        </span>
        <button onclick="window.print()" style="padding:.45rem 1rem;background:#0e83b5;color:#fff;border:none;border-radius:.5rem;font-size:.82rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:.35rem;">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<!-- KPI Cards -->
<div class="kpi-row">
    <div class="kpi">
        <div class="kpi-lbl">Today's Visitors</div>
        <div class="kpi-val" style="color:#0e83b5;"><?php echo number_format($today_visits); ?></div>
        <i class="fas fa-user-clock kpi-icon"></i>
    </div>
    <div class="kpi">
        <div class="kpi-lbl">Unique Visitors <small style="font-weight:500;text-transform:none;">(filtered)</small></div>
        <div class="kpi-val" style="color:#8b5cf6;"><?php echo number_format($unique_visitors); ?></div>
        <i class="fas fa-users kpi-icon"></i>
    </div>
    <div class="kpi">
        <div class="kpi-lbl">Total Page Views <small style="font-weight:500;text-transform:none;">(filtered)</small></div>
        <div class="kpi-val" style="color:#0ea5e9;"><?php echo number_format($total_views); ?></div>
        <i class="fas fa-eye kpi-icon"></i>
    </div>
    <div class="kpi">
        <div class="kpi-lbl">This Month</div>
        <div class="kpi-val" style="color:#10b981;"><?php echo number_format($month_visits); ?></div>
        <i class="fas fa-calendar-check kpi-icon"></i>
    </div>
    <div class="kpi">
        <div class="kpi-lbl">All-Time Visitors</div>
        <div class="kpi-val" style="color:#f59e0b;"><?php echo number_format($alltime_visits); ?></div>
        <i class="fas fa-globe kpi-icon"></i>
    </div>
</div>

<!-- Filter Bar -->
<form method="GET" action="" class="filter-bar no-print">
    <div class="filter-grp">
        <label><i class="fas fa-calendar"></i> From</label>
        <input type="date" name="from" value="<?php echo htmlspecialchars($f_from); ?>" max="<?php echo date('Y-m-d'); ?>">
    </div>
    <div class="filter-grp">
        <label><i class="fas fa-calendar"></i> To</label>
        <input type="date" name="to" value="<?php echo htmlspecialchars($f_to); ?>" max="<?php echo date('Y-m-d'); ?>">
    </div>
    <div class="filter-grp">
        <label><i class="fas fa-file-alt"></i> Page</label>
        <select name="page_filter">
            <option value="">All Pages</option>
            <?php while ($pg = $pages_res->fetch_assoc()): ?>
            <option value="<?php echo htmlspecialchars($pg['page']); ?>" <?php echo $f_page === $pg['page'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($pg['page']); ?>
            </option>
            <?php endwhile; ?>
        </select>
    </div>
    <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Apply Filter</button>
    <a href="report_visitor.php" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
    <!-- Quick presets -->
    <div class="filter-grp" style="margin-left:auto;">
        <label>Quick Select</label>
        <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
            <a href="report_visitor.php?from=<?php echo date('Y-m-d'); ?>&to=<?php echo date('Y-m-d'); ?>"
               style="padding:.38rem .7rem;background:<?php echo ($f_from === date('Y-m-d') && $f_to === date('Y-m-d')) ? '#0e83b5' : '#f1f5f9'; ?>;color:<?php echo ($f_from === date('Y-m-d') && $f_to === date('Y-m-d')) ? '#fff' : '#334155'; ?>;border-radius:.4rem;font-size:.75rem;font-weight:600;text-decoration:none;">Today</a>
            <a href="report_visitor.php?from=<?php echo date('Y-m-d', strtotime('-6 days')); ?>&to=<?php echo date('Y-m-d'); ?>"
               style="padding:.38rem .7rem;background:#f1f5f9;color:#334155;border-radius:.4rem;font-size:.75rem;font-weight:600;text-decoration:none;">Last 7 Days</a>
            <a href="report_visitor.php?from=<?php echo date('Y-m-d', strtotime('-29 days')); ?>&to=<?php echo date('Y-m-d'); ?>"
               style="padding:.38rem .7rem;background:#f1f5f9;color:#334155;border-radius:.4rem;font-size:.75rem;font-weight:600;text-decoration:none;">Last 30 Days</a>
            <a href="report_visitor.php?from=<?php echo date('Y-m-01'); ?>&to=<?php echo date('Y-m-d'); ?>"
               style="padding:.38rem .7rem;background:<?php echo ($f_from === date('Y-m-01') && $f_to === date('Y-m-d')) ? '#0e83b5' : '#f1f5f9'; ?>;color:<?php echo ($f_from === date('Y-m-01') && $f_to === date('Y-m-d')) ? '#fff' : '#334155'; ?>;border-radius:.4rem;font-size:.75rem;font-weight:600;text-decoration:none;">This Month</a>
        </div>
    </div>
</form>

<!-- Daily Chart + Top Pages -->
<div class="two-col">
    <!-- Daily Visitors Chart -->
    <div class="chart-box">
        <h4><i class="fas fa-chart-line" style="color:#0e83b5;"></i> Daily Visitors — <?php echo date('M d', strtotime($f_from)); ?> to <?php echo date('M d, Y', strtotime($f_to)); ?></h4>
        <?php if (empty($chart_days)): ?>
        <div class="empty-note"><i class="fas fa-chart-line"></i>No visitor data for this period.</div>
        <?php else: ?>
        <div class="chart-wrap">
            <canvas id="dailyChart"></canvas>
        </div>
        <?php endif; ?>
    </div>

    <!-- Top Pages -->
    <div class="chart-box">
        <h4><i class="fas fa-list-ol" style="color:#8b5cf6;"></i> Top Pages Visited</h4>
        <?php
        // Re-query top pages for display (we already fetched above)
        $top_pages2 = $conn->query(
            "SELECT page,
                    COUNT(*) AS views,
                    COUNT(DISTINCT session_key) AS unique_v
             FROM visitor_logs
             $where_base
             GROUP BY page
             ORDER BY unique_v DESC
             LIMIT 8"
        );
        $max_uv = 1;
        $top_rows = [];
        while ($r = $top_pages2->fetch_assoc()) { $top_rows[] = $r; if ($r['unique_v'] > $max_uv) $max_uv = $r['unique_v']; }
        ?>
        <?php if (empty($top_rows)): ?>
        <div class="empty-note"><i class="fas fa-file-alt"></i>No data for this period.</div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:.6rem;margin-top:.25rem;">
            <?php foreach ($top_rows as $r): ?>
            <div>
                <div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:.2rem;">
                    <span style="font-weight:600;color:#334155;"><?php echo htmlspecialchars($r['page']); ?></span>
                    <span style="color:#64748b;"><?php echo number_format($r['unique_v']); ?> visitors · <?php echo number_format($r['views']); ?> views</span>
                </div>
                <div style="background:#f1f5f9;border-radius:4px;height:8px;overflow:hidden;">
                    <div style="width:<?php echo round(($r['unique_v'] / $max_uv) * 100); ?>%;height:8px;background:linear-gradient(90deg,#0e83b5,#38bdf8);border-radius:4px;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Daily Breakdown Table -->
<div class="tbl-wrap">
    <div class="tbl-hd">
        <span><i class="fas fa-table"></i> Daily Breakdown</span>
        <span style="color:#94a3b8;font-size:.78rem;"><?php echo $detail_res->num_rows; ?> day(s) in range</span>
    </div>
    <?php if ($detail_res->num_rows === 0): ?>
    <div class="empty-note">
        <i class="fas fa-eye-slash"></i>
        No visitor data recorded for the selected date range.<br>
        <small>Make sure the <code>visitor_logs</code> table was created and pages include the tracker.</small>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="rtbl">
        <thead>
            <tr>
                <th>#</th>
                <th><i class="fas fa-calendar"></i> Date</th>
                <th><i class="fas fa-day"></i> Day</th>
                <th style="text-align:center;"><i class="fas fa-users"></i> Unique Visitors</th>
                <th style="text-align:center;"><i class="fas fa-eye"></i> Total Page Views</th>
                <th style="text-align:center;">Avg. Views / Visitor</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $row_num = 1;
        $grand_uv = 0; $grand_views = 0;
        $rows_buf = [];
        while ($row = $detail_res->fetch_assoc()) { $rows_buf[] = $row; }
        foreach ($rows_buf as $row):
            $grand_uv    += $row['unique_visitors'];
            $grand_views += $row['total_views'];
            $avg = $row['unique_visitors'] > 0 ? round($row['total_views'] / $row['unique_visitors'], 1) : 0;
            $is_today = ($row['visit_date'] === date('Y-m-d'));
        ?>
        <tr <?php echo $is_today ? 'style="background:#eff6ff;"' : ''; ?>>
            <td style="color:#94a3b8;"><?php echo $row_num++; ?></td>
            <td>
                <strong><?php echo date('F d, Y', strtotime($row['visit_date'])); ?></strong>
                <?php if ($is_today): ?><span style="margin-left:.4rem;background:#dbeafe;color:#1d4ed8;font-size:.65rem;font-weight:700;padding:.1rem .4rem;border-radius:.3rem;">TODAY</span><?php endif; ?>
            </td>
            <td style="color:#64748b;"><?php echo date('l', strtotime($row['visit_date'])); ?></td>
            <td style="text-align:center;">
                <strong style="color:#0e83b5;font-size:1rem;"><?php echo number_format($row['unique_visitors']); ?></strong>
            </td>
            <td style="text-align:center;">
                <strong style="color:#334155;"><?php echo number_format($row['total_views']); ?></strong>
            </td>
            <td style="text-align:center;color:#64748b;"><?php echo $avg; ?>x</td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f1f5f9;font-weight:700;">
                <td colspan="3" style="padding:.7rem 1rem;color:#334155;text-align:right;">TOTAL (filtered period):</td>
                <td style="text-align:center;color:#0e83b5;font-size:1.05rem;"><?php echo number_format($unique_visitors); ?></td>
                <td style="text-align:center;color:#334155;font-size:1.05rem;"><?php echo number_format($total_views); ?></td>
                <td style="text-align:center;color:#64748b;">
                    <?php echo $unique_visitors > 0 ? round($total_views / $unique_visitors, 1) : 0; ?>x avg
                </td>
            </tr>
        </tfoot>
    </table>
    </div>
    <?php endif; ?>
</div>

<!-- Note at bottom -->
<p style="font-size:.75rem;color:#94a3b8;text-align:center;margin-top:.5rem;" class="no-print">
    <i class="fas fa-info-circle"></i>
    "Unique Visitors" counts each person once per day based on a hashed combination of IP + browser. Page views count every tracked page load.
    Tracking is active on the <strong>Home (index)</strong> and <strong>Login</strong> pages.
</p>

</div><!-- /.fade-in -->

<?php if (!empty($chart_days)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const labels = <?php echo json_encode($chart_days); ?>;
const views  = <?php echo json_encode($chart_views); ?>;
const unique = <?php echo json_encode($chart_unique); ?>;

const ctx = document.getElementById('dailyChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Unique Visitors',
                data: unique,
                backgroundColor: 'rgba(14,131,181,0.85)',
                borderRadius: 5,
                order: 1
            },
            {
                label: 'Total Page Views',
                data: views,
                type: 'line',
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245,158,11,0.12)',
                borderWidth: 2.5,
                pointRadius: 4,
                pointBackgroundColor: '#f59e0b',
                fill: true,
                tension: 0.35,
                order: 0
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 11 } } },
            tooltip: {
                callbacks: {
                    label: ctx => ' ' + ctx.dataset.label + ': ' + ctx.formattedValue
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1, precision: 0 },
                grid: { color: '#f1f5f9' }
            },
            x: { grid: { display: false } }
        }
    }
});
</script>
<?php endif; ?>

<?php include '../includes/admin_footer.php'; ?>
