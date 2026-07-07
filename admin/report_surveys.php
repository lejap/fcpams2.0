<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
auth_guard('ADMIN');

// ── Filters ───────────────────────────────────────────────────────────────────
$f_survey = isset($_GET['survey']) ? (int) $_GET['survey'] : 0;
$f_branch = isset($_GET['branch']) ? sanitize($_GET['branch']) : '';
$f_from = isset($_GET['from']) ? sanitize($_GET['from']) : '';
$f_to = isset($_GET['to']) ? sanitize($_GET['to']) : '';

// ── KPIs ──────────────────────────────────────────────────────────────────────
$total_surveys = $conn->query("SELECT COUNT(*) c FROM surveys")->fetch_assoc()['c'];
$active_surveys = $conn->query("SELECT COUNT(*) c FROM surveys WHERE is_active=1")->fetch_assoc()['c'];
$total_responses = $conn->query("SELECT COUNT(*) c FROM survey_responses")->fetch_assoc()['c'];
$avg_per_survey = $total_surveys > 0 ? round($total_responses / $total_surveys, 1) : 0;

// ── Chart: top surveys ────────────────────────────────────────────────────────
$sv_rows = $conn->query("SELECT s.title, COUNT(r.id) cnt FROM surveys s LEFT JOIN survey_responses r ON s.id=r.survey_id GROUP BY s.id ORDER BY cnt DESC LIMIT 6");
$sv_lbl = [];
$sv_cnt = [];
while ($r = $sv_rows->fetch_assoc()) {
    $sv_lbl[] = mb_substr($r['title'], 0, 22) . (mb_strlen($r['title']) > 22 ? '...' : '');
    $sv_cnt[] = $r['cnt'];
}

// ── All surveys list ──────────────────────────────────────────────────────────
$surveys_all = $conn->query("SELECT id, title FROM surveys WHERE is_active=1 OR id IN (SELECT survey_id FROM survey_responses) ORDER BY title");

// ── Branch list ───────────────────────────────────────────────────────────────
$branches = $conn->query("SELECT name FROM branches ORDER BY name");

// ── Responses query (with filter) ─────────────────────────────────────────────
$r_where = "WHERE 1=1";
if ($f_survey)
    $r_where .= " AND r.survey_id = $f_survey";
if ($f_branch)
    $r_where .= " AND r.user_branch = '$f_branch'";
if ($f_from)
    $r_where .= " AND DATE(r.created_at) >= '$f_from'";
if ($f_to)
    $r_where .= " AND DATE(r.created_at) <= '$f_to'";

$responses = $conn->query("
    SELECT r.*, s.title as survey_title,
           (SELECT COUNT(*) FROM answers WHERE response_id=r.id) as ans_count
    FROM survey_responses r
    JOIN surveys s ON r.survey_id = s.id
    $r_where
    ORDER BY r.created_at DESC
");
$filtered_count = $responses->num_rows;

// ── Survey summary table ──────────────────────────────────────────────────────
$surveys_detail = $conn->query("SELECT s.*, COUNT(r.id) responses FROM surveys s LEFT JOIN survey_responses r ON s.id=r.survey_id GROUP BY s.id ORDER BY s.created_at DESC");

$page_title = "Surveys Report";
include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<style>
    .rpt-hero {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 2rem;
    }

    .rpt-hero h1 {
        font-size: 2rem;
        margin-bottom: .2rem;
    }

    .kpi-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 1rem;
        margin-bottom: 1.75rem;
    }

    .kpi {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1.1rem 1.25rem;
        text-align: center;
    }

    .kpi-lbl {
        font-size: .65rem;
        text-transform: uppercase;
        color: #94a3b8;
        font-weight: 700;
        letter-spacing: .05em;
        margin-bottom: .3rem;
    }

    .kpi-val {
        font-size: 1.9rem;
        font-weight: 900;
        line-height: 1;
    }

    .chart-pair {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
        margin-bottom: 1.75rem;
    }

    .chart-box {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1.25rem;
    }

    .chart-box h4 {
        font-size: .88rem;
        font-weight: 700;
        color: #334155;
        margin-bottom: 1rem;
    }

    .chart-wrap {
        position: relative;
        height: 280px;
    }

    .filter-bar {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
        display: flex;
        gap: .75rem;
        flex-wrap: wrap;
        align-items: flex-end;
    }

    .filter-grp {
        display: flex;
        flex-direction: column;
        gap: .25rem;
    }

    .filter-grp label {
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #94a3b8;
        letter-spacing: .04em;
    }

    .filter-grp select,
    .filter-grp input {
        padding: .45rem .7rem;
        border: 1px solid #e2e8f0;
        border-radius: .5rem;
        font-size: .85rem;
        color: #334155;
        background: #f8fafc;
    }

    .tbl-wrap {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        overflow: hidden;
        margin-bottom: 1.75rem;
    }

    .tbl-hd {
        background: #f8fafc;
        padding: .8rem 1.25rem;
        font-size: .75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #64748b;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .rtbl {
        width: 100%;
        border-collapse: collapse;
        font-size: .82rem;
    }

    .rtbl th {
        background: #f1f5f9;
        padding: .6rem .9rem;
        text-align: left;
        font-size: .7rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #94a3b8;
    }

    .rtbl td {
        padding: .6rem .9rem;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        vertical-align: middle;
    }

    .rtbl tr:last-child td {
        border-bottom: none;
    }

    .rtbl tr:hover td {
        background: #f8fafc;
    }

    .badge {
        display: inline-block;
        padding: .15rem .5rem;
        border-radius: .3rem;
        font-size: .7rem;
        font-weight: 700;
    }

    @media print {
        @page {
            size: A4 landscape;
            margin: 10mm 12mm;
        }

        .admin-sidebar,
        .admin-header,
        .filter-bar,
        .no-print,
        .rpt-hero {
            display: none !important;
        }

        .admin-wrapper,
        .admin-body,
        .admin-content {
            display: block !important;
            overflow: visible !important;
            height: auto !important;
            max-height: none !important;
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
            position: static !important;
        }

        body,
        html {
            background: #fff !important;
            overflow: visible !important;
            height: auto !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .print-hd {
            display: block !important;
        }

        .kpi-row {
            grid-template-columns: repeat(4, 1fr);
        }

        .tbl-wrap,
        div[style*="overflow"] {
            overflow: visible !important;
            max-height: none !important;
            height: auto !important;
        }

        .rtbl th {
            background: #e2e8f0 !important;
        }

        .rtbl {
            table-layout: auto;
            width: 100%;
            font-size: .72rem;
        }

        .rtbl td,
        .rtbl th {
            padding: .4rem .6rem;
        }

        .tbl-wrap {
            break-inside: auto;
        }

        .rtbl tr {
            break-inside: avoid;
        }

        .kpi-row,
        .chart-pair,
        .chart-box,
        .kpi {
            break-inside: avoid;
        }

        .no-print {
            display: none !important;
        }
    }

    .print-hd {
        display: none;
        text-align: center;
        margin-bottom: 1.25rem;
        padding-bottom: .75rem;
        border-bottom: 2px solid #8b5cf6;
    }

    .print-hd h1 {
        font-size: 1.4rem;
        color: #0f172a;
        margin-bottom: .2rem;
    }

    .print-hd p {
        font-size: .78rem;
        color: #64748b;
    }
</style>

<div class="fade-in">
    <div class="print-hd">
        <h1>FCPAMS — Surveys Report</h1>
        <p>Generated: <?php echo date('F d, Y \a\t h:i A'); ?> · Prepared by:
            <?php echo htmlspecialchars($_SESSION['name']); ?>
        </p>
        <?php if ($f_survey || $f_branch || $f_from || $f_to): ?>
            <p style="margin-top:.3rem;font-size:.75rem;">
                Filters:
                <?php if ($f_survey) {
                    $st = $conn->query("SELECT title FROM surveys WHERE id=$f_survey")->fetch_assoc();
                    echo "Survey: <strong>" . htmlspecialchars($st['title'] ?? '#' . $f_survey) . "</strong> &nbsp;";
                } ?>
                <?php if ($f_branch)
                    echo "Branch: <strong>$f_branch</strong> &nbsp;"; ?>
                <?php if ($f_from)
                    echo "From: <strong>$f_from</strong> &nbsp;"; ?>
                <?php if ($f_to)
                    echo "To: <strong>$f_to</strong>"; ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Hero -->
    <div class="rpt-hero no-print" style="background:linear-gradient(135deg,#1e1b4b 0%,#312e81 40%,#4c1d95 100%);border-radius:1.25rem;padding:2rem 2.25rem;margin-bottom:2rem;box-shadow:0 8px 32px rgba(139,92,246,0.25);">
        <div>
            <a href="dashboard.php"
                style="color:rgba(255,255,255,0.6);text-decoration:none;font-size:.82rem;display:inline-flex;align-items:center;gap:.4rem;margin-bottom:.75rem;transition:color .2s;"
                onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.6)'">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                <div style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.18);border-radius:0.9rem;width:3.2rem;height:3.2rem;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(6px);">
                    <i class="fas fa-poll-h" style="font-size:1.4rem;color:#c4b5fd;"></i>
                </div>
                <div>
                    <div style="display:flex;align-items:center;gap:0.6rem;">
                        <h1 style="color:#fff;margin:0;font-size:1.75rem;font-weight:800;letter-spacing:-0.02em;">Surveys Report</h1>
                        <span style="background:linear-gradient(135deg,#a855f7,#7c3aed);color:#fff;font-size:0.65rem;font-weight:800;padding:0.2rem 0.6rem;border-radius:1rem;letter-spacing:0.06em;text-transform:uppercase;box-shadow:0 2px 8px rgba(168,85,247,0.5);">Analytics</span>
                    </div>
                    <p style="color:rgba(255,255,255,0.65);margin:0.2rem 0 0;font-size:0.9rem;">Citizen engagement &amp; survey response insights</p>
                </div>
            </div>
        </div>
        <button onclick="window.print()" class="btn no-print"
            style="padding:.75rem 1.6rem;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.3);color:#fff;border-radius:0.75rem;font-weight:700;font-size:0.88rem;cursor:pointer;display:flex;align-items:center;gap:.55rem;backdrop-filter:blur(6px);transition:all .2s;white-space:nowrap;"
            onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">
            <i class="fas fa-print"></i> Print / Export PDF
        </button>
    </div>

    <!-- KPIs -->
    <div class="kpi-row">
        <div class="kpi" style="border-left:4px solid #8b5cf6;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem;">
                <div class="kpi-lbl">Total Surveys</div>
                <div style="background:rgba(139,92,246,0.1);border-radius:0.5rem;width:2rem;height:2rem;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-clipboard-list" style="color:#8b5cf6;font-size:.85rem;"></i>
                </div>
            </div>
            <div class="kpi-val" style="color:#8b5cf6;"><?php echo $total_surveys; ?></div>
        </div>
        <div class="kpi" style="border-left:4px solid #10b981;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem;">
                <div class="kpi-lbl">Active Surveys</div>
                <div style="background:rgba(16,185,129,0.1);border-radius:0.5rem;width:2rem;height:2rem;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-toggle-on" style="color:#10b981;font-size:.85rem;"></i>
                </div>
            </div>
            <div class="kpi-val" style="color:#10b981;"><?php echo $active_surveys; ?></div>
        </div>
        <div class="kpi" style="border-left:4px solid #3b82f6;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem;">
                <div class="kpi-lbl">Total Responses</div>
                <div style="background:rgba(59,130,246,0.1);border-radius:0.5rem;width:2rem;height:2rem;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-users" style="color:#3b82f6;font-size:.85rem;"></i>
                </div>
            </div>
            <div class="kpi-val" style="color:#3b82f6;"><?php echo $total_responses; ?></div>
        </div>
        <div class="kpi" style="border-left:4px solid #0d9488;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem;">
                <div class="kpi-lbl">Avg / Survey</div>
                <div style="background:rgba(13,148,136,0.1);border-radius:0.5rem;width:2rem;height:2rem;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-chart-line" style="color:#0d9488;font-size:.85rem;"></i>
                </div>
            </div>
            <div class="kpi-val" style="color:#0d9488;"><?php echo $avg_per_survey; ?></div>
        </div>
    </div>

    <!-- Chart -->
    <div class="chart-pair">
        <div class="chart-box" style="border-top:3px solid #8b5cf6;">
            <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:1rem;">
                <div style="background:linear-gradient(135deg,#7c3aed,#a855f7);border-radius:0.5rem;width:2rem;height:2rem;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-chart-bar" style="color:#fff;font-size:.8rem;"></i>
                </div>
                <h4 style="margin:0;">Response Volume by Survey <span style="color:#94a3b8;font-weight:400;font-size:.8rem;">(Top 6)</span></h4>
            </div>
            <div class="chart-wrap"><canvas id="surveysChart"></canvas></div>
        </div>
    </div>

    <!-- Survey Summary Table -->
    <div class="tbl-wrap">
        <div class="tbl-hd"><span><i class="fas fa-clipboard-list"></i> All Surveys — Summary</span></div>
        <table class="rtbl">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Survey Title</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Total Responses</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($s = $surveys_detail->fetch_assoc()): ?>
                    <tr>
                        <td style="color:#94a3b8;"><?php echo $s['id']; ?></td>
                        <td style="font-weight:600;"><?php echo htmlspecialchars($s['title']); ?></td>
                        <td style="font-size:.78rem;color:#64748b;max-width:220px;">
                            <?php echo htmlspecialchars(mb_substr($s['description'] ?? '', 0, 70)) . (mb_strlen($s['description'] ?? '') > 70 ? '...' : ''); ?>
                        </td>
                        <td>
                            <?php if ($s['is_active']): ?>
                                <span class="badge" style="background:#dcfce7;color:#16a34a;">Active</span>
                            <?php else: ?>
                                <span class="badge" style="background:#f1f5f9;color:#64748b;">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight:800;font-size:1.05rem;color:#8b5cf6;"><?php echo $s['responses']; ?></td>
                        <td style="font-size:.8rem;"><?php echo date('M d, Y', strtotime($s['created_at'])); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Filter bar for response log -->
    <form method="GET" class="filter-bar no-print">
        <div class="filter-grp">
            <label>Survey</label>
            <select name="survey">
                <option value="">All Surveys</option>
                <?php $surveys_all->data_seek(0);
                while ($sv = $surveys_all->fetch_assoc()): ?>
                    <option value="<?php echo $sv['id']; ?>" <?php echo $f_survey == $sv['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($sv['title']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="filter-grp">
            <label>Branch</label>
            <select name="branch">
                <option value="">All Branches</option>
                <?php $branches->data_seek(0);
                while ($b = $branches->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($b['name']); ?>" <?php echo $f_branch === $b['name'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="filter-grp">
            <label>Date From</label>
            <input type="date" name="from" value="<?php echo htmlspecialchars($f_from); ?>">
        </div>
        <div class="filter-grp">
            <label>Date To</label>
            <input type="date" name="to" value="<?php echo htmlspecialchars($f_to); ?>">
        </div>
        <div style="display:flex;gap:.5rem;align-items:flex-end;">
            <button type="submit" class="btn btn-primary"
                style="padding:.45rem 1.1rem;font-size:.85rem;background:#8b5cf6;border-color:#8b5cf6;">Filter</button>
            <a href="report_surveys.php" class="btn btn-outline" style="padding:.45rem 1rem;font-size:.85rem;">Clear</a>
        </div>
    </form>

    <!-- Response log table -->
    <div class="tbl-wrap">
        <div class="tbl-hd">
            <span><i class="fas fa-users"></i> Response Log — <?php echo $filtered_count; ?>
                record<?php echo $filtered_count != 1 ? 's' : ''; ?></span>
            <?php if ($f_survey || $f_branch || $f_from || $f_to): ?>
                <span
                    style="font-size:.75rem;color:#8b5cf6;font-weight:600;background:#f5f3ff;padding:.2rem .6rem;border-radius:.4rem;">Filtered</span>
            <?php endif; ?>
        </div>
        <div style="overflow-x:auto;">
            <table class="rtbl">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Survey</th>
                        <th>Respondent</th>
                        <th>Email</th>
                        <th>Branch</th>
                        <th>Answers</th>
                        <th>Submitted</th>
                        <th class="no-print">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($responses->num_rows > 0):
                        while ($r = $responses->fetch_assoc()): ?>
                            <tr>
                                <td style="color:#94a3b8;"><?php echo $r['id']; ?></td>
                                <td style="font-weight:600;max-width:160px;"><?php echo htmlspecialchars($r['survey_title']); ?>
                                </td>
                                <td style="font-weight:600;"><?php echo htmlspecialchars($r['user_name']); ?></td>
                                <td style="font-size:.78rem;"><?php echo htmlspecialchars($r['user_email'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($r['user_branch'] ?? '-'); ?></td>
                                <td style="font-weight:700;color:#8b5cf6;"><?php echo $r['ans_count']; ?></td>
                                <td style="white-space:nowrap;font-size:.8rem;">
                                    <?php echo date('M d, Y', strtotime($r['created_at'])); ?>
                                </td>
                                <td class="no-print">
                                    <a href="<?php echo BASE_URL; ?>admin/survey_response.php?id=<?php echo $r['id']; ?>"
                                        class="btn btn-outline" style="padding:.2rem .6rem;font-size:.75rem;">View</a>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:2rem;color:#94a3b8;">No responses match the
                                selected filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    Chart.defaults.font.family = "'Inter',sans-serif"; Chart.defaults.color = '#94a3b8';
    new Chart(document.getElementById('surveysChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($sv_lbl); ?>,
            datasets: [{ label: 'Responses', data: <?php echo json_encode($sv_cnt); ?>, backgroundColor: 'rgba(139,92,246,0.85)', borderColor: '#8b5cf6', borderRadius: 10, barThickness: 48 }]
        },
        options: {
            indexAxis: 'y', maintainAspectRatio: false,
            scales: { x: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { stepSize: 1 } }, y: { grid: { display: false }, ticks: { font: { weight: '600', size: 12 }, color: '#1e293b' } } },
            plugins: { legend: { display: false } }
        }
    });
    window.addEventListener('beforeprint', function () {
        Chart.instances.forEach(c => c.resize());
        document.querySelectorAll('[style*="overflow"]').forEach(function (el) {
            el.dataset.overflowBak = el.style.overflow;
            el.dataset.maxhBak = el.style.maxHeight;
            el.style.overflow = 'visible';
            el.style.maxHeight = 'none';
            el.style.height = 'auto';
        });
        document.querySelectorAll('.tbl-wrap,.admin-content,.admin-body,.admin-wrapper').forEach(function (el) {
            el.dataset.overflowBak = el.style.overflow;
            el.style.overflow = 'visible';
            el.style.height = 'auto';
        });
    });
    window.addEventListener('afterprint', function () {
        document.querySelectorAll('[data-overflow-bak]').forEach(function (el) {
            el.style.overflow = el.dataset.overflowBak || '';
            el.style.maxHeight = el.dataset.maxhBak || '';
            el.style.height = '';
            delete el.dataset.overflowBak;
            delete el.dataset.maxhBak;
        });
    });
</script>
<?php include '../includes/admin_footer.php'; ?>