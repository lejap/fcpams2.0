<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
auth_guard('ADMIN');

// ── Filters ───────────────────────────────────────────────────────────────────
$f_from = isset($_GET['from']) ? sanitize($_GET['from']) : date('Y-m-01');
$f_to   = isset($_GET['to'])   ? sanitize($_GET['to'])   : date('Y-m-d');
$f_page = isset($_GET['page_filter']) ? sanitize($_GET['page_filter']) : '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_from)) $f_from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_to))   $f_to   = date('Y-m-d');

$where_base = "WHERE DATE(visited_at) BETWEEN '$f_from' AND '$f_to'";
if ($f_page) $where_base .= " AND page = '" . $conn->real_escape_string($f_page) . "'";

// ── KPIs ──────────────────────────────────────────────────────────────────────
// Unique visitors = SUM(is_unique_today) in range (1 per person per day)
$unique_visitors = $conn->query("SELECT SUM(is_unique_today) c FROM visitor_logs $where_base")->fetch_assoc()['c'] ?? 0;
$total_views     = $conn->query("SELECT COUNT(*) c FROM visitor_logs $where_base")->fetch_assoc()['c'];

$today_str    = date('Y-m-d');
$today_visits = $conn->query("SELECT SUM(is_unique_today) c FROM visitor_logs WHERE DATE(visited_at)='$today_str'")->fetch_assoc()['c'] ?? 0;

$month_start  = date('Y-m-01');
$month_visits = $conn->query("SELECT SUM(is_unique_today) c FROM visitor_logs WHERE DATE(visited_at) BETWEEN '$month_start' AND '$today_str'")->fetch_assoc()['c'] ?? 0;

$alltime_visits = $conn->query("SELECT SUM(is_unique_today) c FROM visitor_logs")->fetch_assoc()['c'] ?? 0;

// ── Daily chart data ──────────────────────────────────────────────────────────
$daily_res = $conn->query(
    "SELECT DATE(visited_at) AS day,
            COUNT(*) AS views,
            SUM(is_unique_today) AS unique_v
     FROM visitor_logs $where_base
     GROUP BY DATE(visited_at) ORDER BY day ASC"
);
$chart_days = []; $chart_views = []; $chart_unique = [];
while ($r = $daily_res->fetch_assoc()) {
    $chart_days[]   = date('M d', strtotime($r['day']));
    $chart_views[]  = (int)$r['views'];
    $chart_unique[] = (int)$r['unique_v'];
}

// ── Top pages ─────────────────────────────────────────────────────────────────
$top_pages_res = $conn->query(
    "SELECT page, COUNT(*) AS views, SUM(is_unique_today) AS unique_v
     FROM visitor_logs $where_base
     GROUP BY page ORDER BY unique_v DESC LIMIT 8"
);
$top_rows = []; $max_uv = 1;
while ($r = $top_pages_res->fetch_assoc()) {
    $top_rows[] = $r;
    if ($r['unique_v'] > $max_uv) $max_uv = $r['unique_v'];
}

// ── Page filter options ───────────────────────────────────────────────────────
$pages_res = $conn->query("SELECT DISTINCT page FROM visitor_logs ORDER BY page ASC");

// ── Daily breakdown table ─────────────────────────────────────────────────────
$detail_res = $conn->query(
    "SELECT DATE(visited_at) AS visit_date,
            COUNT(*) AS total_views,
            SUM(is_unique_today) AS unique_visitors
     FROM visitor_logs $where_base
     GROUP BY DATE(visited_at) ORDER BY visit_date DESC"
);
$rows_buf = [];
while ($row = $detail_res->fetch_assoc()) $rows_buf[] = $row;

$page_title = "Visitor Analytics";
include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<style>
/* ── Screen styles ── */
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
.btn-pdf{padding:.48rem 1.1rem;background:#ef4444;color:#fff;border:none;border-radius:.5rem;font-size:.85rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;}
.btn-pdf:hover{background:#dc2626;}
.btn-print{padding:.48rem 1.1rem;background:#334155;color:#fff;border:none;border-radius:.5rem;font-size:.85rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;}
.btn-print:hover{background:#1e293b;}
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
.date-range-badge{background:#dbeafe;color:#1d4ed8;padding:.2rem .65rem;border-radius:.4rem;font-size:.75rem;font-weight:700;}
@media(max-width:768px){.two-col{grid-template-columns:1fr;}.kpi-row{grid-template-columns:repeat(2,1fr);}}

/* ── Print / PDF styles ── */
@media print {
    @page { size: A4 portrait; margin: 12mm 14mm; }
    /* Hide everything that isn't content */
    .admin-sidebar, .admin-header, .filter-bar, .no-print,
    .btn-pdf, .btn-print, .btn-reset, .btn-filter { display: none !important; }
    /* Flatten layout */
    .admin-wrapper, .admin-body, .admin-content {
        display: block !important; overflow: visible !important;
        height: auto !important; padding: 0 !important;
        margin: 0 !important; width: 100% !important; position: static !important;
    }
    body, html { background: #fff !important; overflow: visible !important; height: auto !important; }
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
    /* Show print header */
    .print-hd { display: block !important; }
    /* Two-col becomes single col */
    .two-col { grid-template-columns: 1fr !important; }
    /* Tables */
    .tbl-wrap, div[style*="overflow"] { overflow: visible !important; max-height: none !important; height: auto !important; }
    .rtbl { font-size: .75rem; }
    .rtbl th { background: #e2e8f0 !important; }
    .rtbl td, .rtbl th { padding: .4rem .6rem; }
    /* KPI cards */
    .kpi-row { grid-template-columns: repeat(5, 1fr) !important; }
    .kpi { border: 1px solid #cbd5e1 !important; border-radius: .5rem !important; padding: .6rem .8rem !important; }
    .kpi-val { font-size: 1.4rem !important; }
    /* Charts — make them visible */
    .chart-wrap { height: 180px !important; }
    .chart-box { border: 1px solid #cbd5e1 !important; break-inside: avoid; }
    /* Page breaks */
    .tbl-wrap { break-inside: auto; }
    .rtbl tr { break-inside: avoid; }
    .kpi-row, .chart-box, .kpi { break-inside: avoid; }
    /* Signature section */
    .pdf-sig { display: flex !important; }
}

/* Print header (hidden on screen, shown in print) */
.print-hd { display:none; margin-bottom:1.5rem; padding-bottom:.75rem; border-bottom:3px solid #0e83b5; }
.print-hd .ph-logo { display:flex; align-items:center; justify-content:space-between; margin-bottom:.5rem; }
.print-hd h1 { font-size:1.3rem; color:#0f172a; margin:0; }
.print-hd .ph-sub { font-size:.75rem; color:#64748b; margin-top:.2rem; }
.print-hd .ph-meta { font-size:.72rem; color:#94a3b8; text-align:right; }

/* Signature block (PDF only) */
.pdf-sig { display:none; margin-top:2.5rem; border-top:1px solid #e2e8f0; padding-top:1.5rem; }
.pdf-sig-col { flex:1; text-align:center; }
.pdf-sig-line { border-top:1px solid #334155; margin:0 2rem 0.3rem; }
.pdf-sig-lbl { font-size:.72rem; color:#64748b; }

/* Export spinner */
#pdf-spinner { display:none; position:fixed; top:0; left:0; width:100%; height:100%;
    background:rgba(0,0,0,.45); z-index:9999; align-items:center; justify-content:center; flex-direction:column; gap:1rem; }
#pdf-spinner span { color:#fff; font-size:1rem; font-weight:600; }
</style>

<!-- PDF export spinner -->
<div id="pdf-spinner">
    <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" style="animation:spin 1s linear infinite;">
        <circle cx="24" cy="24" r="20" stroke="rgba(255,255,255,0.3)" stroke-width="4"/>
        <path d="M44 24a20 20 0 0 0-20-20" stroke="#fff" stroke-width="4" stroke-linecap="round"/>
        <style>@keyframes spin{to{transform:rotate(360deg)}}</style>
    </svg>
    <span>Generating PDF, please wait…</span>
</div>

<div class="fade-in" id="report-content">

<!-- ── Print/PDF Header (hidden on screen) ── -->
<div class="print-hd">
    <div class="ph-logo">
        <div>
            <h1><i class="fas fa-eye"></i> FCPAMS — Visitor Analytics Report</h1>
            <div class="ph-sub">First CBISCO Pampanga Area Multi-Purpose Cooperative</div>
        </div>
        <div class="ph-meta">
            Generated: <?php echo date('F d, Y \a\t h:i A'); ?><br>
            Prepared by: <?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?><br>
            Period: <?php echo date('M d, Y', strtotime($f_from)); ?> – <?php echo date('M d, Y', strtotime($f_to)); ?>
        </div>
    </div>
</div>

<!-- ── Hero (screen only) ── -->
<div class="rpt-hero no-print" style="margin-bottom:1.25rem;">
    <div>
        <h1><i class="fas fa-eye" style="font-size:1.6rem;"></i> Visitor Analytics</h1>
        <p>Track how many people visited FCPAMS, with daily breakdowns and page-level insight.</p>
    </div>
    <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;">
        <span class="date-range-badge"><i class="fas fa-calendar-alt"></i>
            <?php echo date('M d, Y', strtotime($f_from)); ?> – <?php echo date('M d, Y', strtotime($f_to)); ?>
        </span>
        <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        <button class="btn-pdf" id="btn-export-pdf"><i class="fas fa-file-pdf"></i> Export PDF</button>
    </div>
</div>

<!-- ── Filter Bar ── -->
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
    <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Apply</button>
    <a href="report_visitor" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
    <div class="filter-grp" style="margin-left:auto;">
        <label>Quick Select</label>
        <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
            <?php
            $presets = [
                'Today'       => [date('Y-m-d'), date('Y-m-d')],
                'Last 7 Days' => [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')],
                'Last 30 Days'=> [date('Y-m-d', strtotime('-29 days')), date('Y-m-d')],
                'This Month'  => [date('Y-m-01'), date('Y-m-d')],
            ];
            foreach ($presets as $label => [$pf, $pt]):
                $active = ($f_from === $pf && $f_to === $pt);
            ?>
            <a href="report_visitor?from=<?php echo $pf; ?>&to=<?php echo $pt; ?>"
               style="padding:.38rem .7rem;background:<?php echo $active ? '#0e83b5' : '#f1f5f9'; ?>;color:<?php echo $active ? '#fff' : '#334155'; ?>;border-radius:.4rem;font-size:.75rem;font-weight:600;text-decoration:none;">
               <?php echo $label; ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</form>

<!-- ── KPI Cards ── -->
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

<!-- ── Chart + Top Pages ── -->
<div class="two-col">
    <div class="chart-box">
        <h4><i class="fas fa-chart-bar" style="color:#0e83b5;"></i>
            Daily Visitors — <?php echo date('M d', strtotime($f_from)); ?> to <?php echo date('M d, Y', strtotime($f_to)); ?>
        </h4>
        <?php if (empty($chart_days)): ?>
        <div class="empty-note"><i class="fas fa-chart-line"></i>No visitor data for this period.</div>
        <?php else: ?>
        <div class="chart-wrap"><canvas id="dailyChart"></canvas></div>
        <?php endif; ?>
    </div>

    <div class="chart-box">
        <h4><i class="fas fa-list-ol" style="color:#8b5cf6;"></i> Top Pages Visited</h4>
        <?php if (empty($top_rows)): ?>
        <div class="empty-note"><i class="fas fa-file-alt"></i>No data.</div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:.65rem;margin-top:.25rem;">
            <?php foreach ($top_rows as $r):
                $pct = $max_uv > 0 ? round(($r['unique_v'] / $max_uv) * 100) : 0;
            ?>
            <div>
                <div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:.2rem;">
                    <span style="font-weight:600;color:#334155;"><?php echo htmlspecialchars($r['page']); ?></span>
                    <span style="color:#64748b;"><?php echo number_format($r['unique_v']); ?> visitors · <?php echo number_format($r['views']); ?> views</span>
                </div>
                <div style="background:#f1f5f9;border-radius:4px;height:8px;overflow:hidden;">
                    <div style="width:<?php echo $pct; ?>%;height:8px;background:linear-gradient(90deg,#0e83b5,#38bdf8);border-radius:4px;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Daily Breakdown Table ── -->
<div class="tbl-wrap">
    <div class="tbl-hd">
        <span><i class="fas fa-table"></i> Daily Breakdown</span>
        <span style="color:#94a3b8;font-size:.78rem;"><?php echo count($rows_buf); ?> day(s) · Period: <?php echo date('M d, Y', strtotime($f_from)); ?> – <?php echo date('M d, Y', strtotime($f_to)); ?></span>
    </div>
    <?php if (empty($rows_buf)): ?>
    <div class="empty-note">
        <i class="fas fa-eye-slash"></i>
        No visitor data recorded for the selected date range.<br>
        <small>Make sure the <code>visitor_logs</code> table was created and pages include the tracker.</small>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="rtbl" id="daily-table">
        <thead>
            <tr>
                <th style="width:40px;">#</th>
                <th>Date</th>
                <th>Day</th>
                <th style="text-align:center;">Unique Visitors</th>
                <th style="text-align:center;">Total Page Views</th>
                <th style="text-align:center;">Avg Views / Visitor</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $n = 1; $grand_uv = 0; $grand_views = 0;
        foreach ($rows_buf as $row):
            $grand_uv    += $row['unique_visitors'];
            $grand_views += $row['total_views'];
            $avg = $row['unique_visitors'] > 0 ? round($row['total_views'] / $row['unique_visitors'], 1) : 0;
            $is_today = ($row['visit_date'] === date('Y-m-d'));
        ?>
        <tr <?php echo $is_today ? 'style="background:#eff6ff;"' : ''; ?>>
            <td style="color:#94a3b8;"><?php echo $n++; ?></td>
            <td>
                <strong><?php echo date('F d, Y', strtotime($row['visit_date'])); ?></strong>
                <?php if ($is_today): ?>
                <span style="margin-left:.35rem;background:#dbeafe;color:#1d4ed8;font-size:.62rem;font-weight:700;padding:.1rem .4rem;border-radius:.3rem;">TODAY</span>
                <?php endif; ?>
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

<!-- ── Top Pages Table (for print/PDF) ── -->
<div class="tbl-wrap" style="margin-top:1rem;" id="top-pages-table">
    <div class="tbl-hd">
        <span><i class="fas fa-list-ol"></i> Top Pages — Detailed</span>
    </div>
    <?php if (empty($top_rows)): ?>
    <div class="empty-note"><i class="fas fa-file"></i>No page data.</div>
    <?php else: ?>
    <table class="rtbl">
        <thead>
            <tr>
                <th>#</th>
                <th>Page</th>
                <th style="text-align:center;">Unique Visitors</th>
                <th style="text-align:center;">Total Views</th>
                <th style="text-align:center;">Avg Views / Visitor</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($top_rows as $i => $r):
            $pavg = $r['unique_v'] > 0 ? round($r['views'] / $r['unique_v'], 1) : 0;
        ?>
        <tr>
            <td style="color:#94a3b8;"><?php echo $i + 1; ?></td>
            <td><strong><?php echo htmlspecialchars($r['page']); ?></strong></td>
            <td style="text-align:center;"><strong style="color:#0e83b5;"><?php echo number_format($r['unique_v']); ?></strong></td>
            <td style="text-align:center;"><?php echo number_format($r['views']); ?></td>
            <td style="text-align:center;color:#64748b;"><?php echo $pavg; ?>x</td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- ── Signature block (shows in PDF/print) ── -->
<div class="pdf-sig">
    <div class="pdf-sig-col">
        <div class="pdf-sig-line"></div>
        <div class="pdf-sig-lbl">Prepared by</div>
        <div style="font-size:.8rem;font-weight:700;color:#334155;margin-top:.15rem;"><?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?></div>
    </div>
    <div class="pdf-sig-col">
        <div class="pdf-sig-line"></div>
        <div class="pdf-sig-lbl">Date Generated</div>
        <div style="font-size:.8rem;font-weight:700;color:#334155;margin-top:.15rem;"><?php echo date('F d, Y \a\t h:i A'); ?></div>
    </div>
    <div class="pdf-sig-col">
        <div class="pdf-sig-line"></div>
        <div class="pdf-sig-lbl">Reviewed by</div>
        <div style="font-size:.8rem;color:#94a3b8;margin-top:.15rem;">____________________</div>
    </div>
</div>

<!-- ── Note ── -->
<p style="font-size:.73rem;color:#94a3b8;text-align:center;margin-top:.75rem;" class="no-print">
    <i class="fas fa-info-circle"></i>
    <strong>Unique Visitors</strong> = 1 person counted once per calendar day (regardless of how many pages they visit).
    <strong>Page Views</strong> = every page load. Tracking active on <strong>index</strong> and <strong>login</strong> pages.
</p>

</div><!-- /#report-content -->

<!-- ── Scripts ── -->
<?php if (!empty($chart_days)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const labels = <?php echo json_encode($chart_days); ?>;
const views  = <?php echo json_encode($chart_views); ?>;
const unique = <?php echo json_encode($chart_unique); ?>;

const ctx = document.getElementById('dailyChart').getContext('2d');
window._dailyChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels,
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
        animation: { duration: 400 },
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 11 } } }
        },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 }, grid: { color: '#f1f5f9' } },
            x: { grid: { display: false } }
        }
    }
});
</script>
<?php endif; ?>

<!-- jsPDF + html2canvas for PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
document.getElementById('btn-export-pdf')?.addEventListener('click', async function () {
    const spinner = document.getElementById('pdf-spinner');
    spinner.style.display = 'flex';

    try {
        const { jsPDF } = window.jspdf;

        // Temporarily show print-only elements for capture
        document.querySelectorAll('.print-hd, .pdf-sig').forEach(el => el.style.display = 'block');
        document.querySelectorAll('.no-print').forEach(el => el.style.display = 'none');

        // Wait for chart animation to settle
        await new Promise(r => setTimeout(r, 300));

        const element = document.getElementById('report-content');

        const canvas = await html2canvas(element, {
            scale: 1.8,
            useCORS: true,
            backgroundColor: '#ffffff',
            logging: false,
            windowWidth: 1100
        });

        // Restore UI
        document.querySelectorAll('.print-hd, .pdf-sig').forEach(el => el.style.display = '');
        document.querySelectorAll('.no-print').forEach(el => el.style.display = '');

        const imgData   = canvas.toDataURL('image/jpeg', 0.9);
        const pdf       = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
        const pageW     = pdf.internal.pageSize.getWidth();
        const pageH     = pdf.internal.pageSize.getHeight();
        const margin    = 10;
        const imgW      = pageW - margin * 2;
        const imgH      = (canvas.height * imgW) / canvas.width;
        const totalPages = Math.ceil(imgH / (pageH - margin * 2));

        for (let i = 0; i < totalPages; i++) {
            if (i > 0) pdf.addPage();
            const yOffset = -(i * (pageH - margin * 2));
            pdf.addImage(imgData, 'JPEG', margin, margin + yOffset, imgW, imgH);
            // Footer
            pdf.setFontSize(7);
            pdf.setTextColor(150);
            pdf.text(
                `FCPAMS Visitor Analytics · Page ${i + 1} of ${totalPages} · Generated ${new Date().toLocaleString('en-PH', {timeZone:'Asia/Manila'})}`,
                margin, pageH - 4
            );
        }

        const fname = `FCPAMS_Visitor_Analytics_<?php echo $f_from; ?>_to_<?php echo $f_to; ?>.pdf`;
        pdf.save(fname);

    } catch (err) {
        console.error('PDF export error:', err);
        alert('PDF export failed. Please try using the Print button instead.');
    } finally {
        spinner.style.display = 'none';
    }
});
</script>

<?php include '../includes/admin_footer.php'; ?>
