<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
auth_guard('ADMIN');

// ── Filters ───────────────────────────────────────────────────────────────────
$f_type    = isset($_GET['type'])   ? sanitize($_GET['type'])   : '';
$f_status  = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$f_branch  = isset($_GET['branch']) ? sanitize($_GET['branch']) : '';
$f_from    = isset($_GET['from'])   ? sanitize($_GET['from'])   : '';
$f_to      = isset($_GET['to'])     ? sanitize($_GET['to'])     : '';

$where = "WHERE t.status != 'SPAM'";
if ($f_type)   $where .= " AND t.type    = '$f_type'";
if ($f_status) $where .= " AND t.status  = '$f_status'";
if ($f_branch) $where .= " AND t.user_branch = '$f_branch'";
if ($f_from)   $where .= " AND DATE(t.created_at) >= '$f_from'";
if ($f_to)     $where .= " AND DATE(t.created_at) <= '$f_to'";

// ── Summary KPIs (unfiltered totals) ─────────────────────────────────────────
$kpi = [];
foreach(['total'=>"status!='SPAM'",'open'=>"status='OPEN'",'resolved'=>"status='RESOLVED'",'closed'=>"status='CLOSED'"] as $k=>$cond)
    $kpi[$k] = $conn->query("SELECT COUNT(*) c FROM tickets WHERE $cond")->fetch_assoc()['c'];

$type_rows = $conn->query("SELECT type, COUNT(*) cnt FROM tickets WHERE status!='SPAM' GROUP BY type");
$t_types=[]; $t_counts=[];
while($r=$type_rows->fetch_assoc()){$t_types[]=ucfirst(strtolower($r['type']));$t_counts[]=$r['cnt'];}

$stat_rows = $conn->query("SELECT status, COUNT(*) cnt FROM tickets WHERE status!='SPAM' GROUP BY status");
$t_stats=[]; $t_scounts=[];
while($r=$stat_rows->fetch_assoc()){$t_stats[]=$r['status'];$t_scounts[]=$r['cnt'];}

// ── Branch list for filter dropdown ──────────────────────────────────────────
$branches = $conn->query("SELECT name FROM branches ORDER BY name");

// ── Main data ─────────────────────────────────────────────────────────────────
$tickets = $conn->query("SELECT t.*, d.label as option_label FROM tickets t LEFT JOIN dropdown_options d ON t.option_id=d.id $where ORDER BY t.created_at DESC");
$filtered_count = $tickets->num_rows;

$page_title = "Submissions Report";
include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<style>
/* ── Shared report styles ── */
.rpt-hero{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:2rem;}
.rpt-hero h1{font-size:2rem;margin-bottom:.2rem;}
.rpt-hero p{color:#64748b;font-size:.9rem;}
.kpi-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;margin-bottom:1.75rem;}
.kpi{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;padding:1.1rem 1.25rem;text-align:center;}
.kpi-lbl{font-size:.65rem;text-transform:uppercase;color:#94a3b8;font-weight:700;letter-spacing:.05em;margin-bottom:.3rem;}
.kpi-val{font-size:1.9rem;font-weight:900;line-height:1;}
.chart-pair{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.75rem;}
.chart-box{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;padding:1.25rem;}
.chart-box h4{font-size:.88rem;font-weight:700;color:#334155;margin-bottom:1rem;}
.chart-wrap{position:relative;height:240px;}
.filter-bar{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;padding:1.25rem 1.5rem;margin-bottom:1.5rem;display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;}
.filter-grp{display:flex;flex-direction:column;gap:.25rem;}
.filter-grp label{font-size:.68rem;font-weight:700;text-transform:uppercase;color:#94a3b8;letter-spacing:.04em;}
.filter-grp select,.filter-grp input{padding:.45rem .7rem;border:1px solid #e2e8f0;border-radius:.5rem;font-size:.85rem;color:#334155;background:#f8fafc;}
.tbl-wrap{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;overflow:hidden;}
.tbl-hd{background:#f8fafc;padding:.8rem 1.25rem;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;}
.rtbl{width:100%;border-collapse:collapse;font-size:.82rem;}
.rtbl th{background:#f1f5f9;padding:.6rem .9rem;text-align:left;font-size:.7rem;font-weight:700;text-transform:uppercase;color:#94a3b8;}
.rtbl td{padding:.6rem .9rem;border-bottom:1px solid #f1f5f9;color:#334155;vertical-align:middle;}
.rtbl tr:last-child td{border-bottom:none;}
.rtbl tr:hover td{background:#f8fafc;}
.badge{display:inline-block;padding:.15rem .5rem;border-radius:.3rem;font-size:.7rem;font-weight:700;}

@media print{
    @page{size:A4 landscape;margin:10mm 12mm;}
    .admin-sidebar,.admin-header,.filter-bar,.no-print,.rpt-hero{display:none!important;}
    .admin-wrapper,.admin-body,.admin-content{display:block!important;overflow:visible!important;height:auto!important;max-height:none!important;padding:0!important;margin:0!important;width:100%!important;position:static!important;}
    body,html{background:#fff!important;overflow:visible!important;height:auto!important;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
    .print-hd{display:block!important;}
    .kpi-row{grid-template-columns:repeat(5,1fr);}
    .chart-pair{grid-template-columns:1fr 1fr;}
    /* Remove ALL overflow clipping so scrollable content is fully visible */
    .tbl-wrap,div[style*="overflow"]{overflow:visible!important;max-height:none!important;height:auto!important;}
    .rtbl th{background:#e2e8f0!important;}
    .rtbl{table-layout:auto;width:100%;font-size:.72rem;}
    .rtbl td,.rtbl th{padding:.4rem .6rem;}
    /* Let table rows flow across pages */
    .tbl-wrap{break-inside:auto;}
    .rtbl tr{break-inside:avoid;}
    /* Keep KPIs and charts together */
    .kpi-row,.chart-pair,.chart-box,.kpi{break-inside:avoid;}
}
.print-hd{display:none;text-align:center;margin-bottom:1.25rem;padding-bottom:.75rem;border-bottom:2px solid #3b82f6;}
.print-hd h1{font-size:1.4rem;color:#0f172a;margin-bottom:.2rem;}
.print-hd p{font-size:.78rem;color:#64748b;}
</style>

<div class="fade-in">
<div class="print-hd">
    <h1>FCPAMS — Submissions Report</h1>
    <p>Generated: <?php echo date('F d, Y \a\t h:i A'); ?> · Prepared by: <?php echo htmlspecialchars($_SESSION['name']); ?></p>
    <?php if($f_type||$f_status||$f_branch||$f_from||$f_to): ?>
    <p style="margin-top:.3rem;font-size:.75rem;">
        Filters applied:
        <?php if($f_type) echo "Type: <strong>$f_type</strong> &nbsp;"; ?>
        <?php if($f_status) echo "Status: <strong>$f_status</strong> &nbsp;"; ?>
        <?php if($f_branch) echo "Branch: <strong>$f_branch</strong> &nbsp;"; ?>
        <?php if($f_from) echo "From: <strong>$f_from</strong> &nbsp;"; ?>
        <?php if($f_to) echo "To: <strong>$f_to</strong>"; ?>
    </p>
    <?php endif; ?>
</div>

<!-- Hero -->
<div class="rpt-hero no-print">
    <div>
        <a href="dashboard.php" style="color:#64748b;text-decoration:none;font-size:.85rem;display:flex;align-items:center;gap:.4rem;margin-bottom:.4rem;"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <h1 style="color:#3b82f6;">Submissions Report</h1>
        <p>Inquiries, Suggestions &amp; Requests — detailed analytics</p>
    </div>
    <button onclick="window.print()" class="btn btn-primary" style="padding:.7rem 1.4rem;">
        <i class="fas fa-print"></i> Print / PDF
    </button>
</div>

<!-- KPIs -->
<div class="kpi-row">
    <div class="kpi"><div class="kpi-lbl">Total</div><div class="kpi-val" style="color:#3b82f6;"><?php echo $kpi['total']; ?></div></div>
    <div class="kpi"><div class="kpi-lbl">Open</div><div class="kpi-val" style="color:#eab308;"><?php echo $kpi['open']; ?></div></div>
    <div class="kpi"><div class="kpi-lbl">Resolved</div><div class="kpi-val" style="color:#10b981;"><?php echo $kpi['resolved']; ?></div></div>
    <div class="kpi"><div class="kpi-lbl">Closed</div><div class="kpi-val" style="color:#8b5cf6;"><?php echo $kpi['closed']; ?></div></div>
    <div class="kpi"><div class="kpi-lbl">Resolution Rate</div><div class="kpi-val" style="color:#0d9488;"><?php echo $kpi['total']>0?round(($kpi['resolved']+$kpi['closed'])/$kpi['total']*100):0; ?>%</div></div>
</div>

<!-- Charts -->
<div class="chart-pair">
    <div class="chart-box"><h4>By Category</h4><div class="chart-wrap"><canvas id="typeChart"></canvas></div></div>
    <div class="chart-box"><h4>By Status</h4><div class="chart-wrap"><canvas id="statusChart"></canvas></div></div>
</div>

<!-- Filter bar -->
<form method="GET" class="filter-bar no-print">
    <div class="filter-grp">
        <label>Type</label>
        <select name="type">
            <option value="">All Types</option>
            <?php foreach(['INQUIRY','SUGGESTION','REQUEST'] as $t): ?>
            <option value="<?php echo $t; ?>" <?php echo $f_type===$t?'selected':''; ?>><?php echo ucfirst(strtolower($t)); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-grp">
        <label>Status</label>
        <select name="status">
            <option value="">All Statuses</option>
            <?php foreach(['OPEN','RESOLVED','CLOSED'] as $s): ?>
            <option value="<?php echo $s; ?>" <?php echo $f_status===$s?'selected':''; ?>><?php echo $s; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-grp">
        <label>Branch</label>
        <select name="branch">
            <option value="">All Branches</option>
            <?php $branches->data_seek(0); while($b=$branches->fetch_assoc()): ?>
            <option value="<?php echo htmlspecialchars($b['name']); ?>" <?php echo $f_branch===$b['name']?'selected':''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
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
        <button type="submit" class="btn btn-primary" style="padding:.45rem 1.1rem;font-size:.85rem;">Filter</button>
        <a href="report_submissions.php" class="btn btn-outline" style="padding:.45rem 1rem;font-size:.85rem;">Clear</a>
    </div>
</form>

<!-- Table -->
<div class="tbl-wrap">
    <div class="tbl-hd">
        <span><i class="fas fa-layer-group"></i> Submissions — <?php echo $filtered_count; ?> record<?php echo $filtered_count!=1?'s':''; ?></span>
        <?php if($f_type||$f_status||$f_branch||$f_from||$f_to): ?>
        <span style="font-size:.75rem;color:#3b82f6;font-weight:600;background:#eff6ff;padding:.2rem .6rem;border-radius:.4rem;">Filtered</span>
        <?php endif; ?>
    </div>
    <div style="overflow-x:auto;">
    <table class="rtbl">
        <thead>
            <tr>
                <th>#</th><th>Ref No</th><th>Member</th><th>Email</th><th>Branch</th>
                <th>Type</th><th>Category</th><th>Status</th>
                <th>Date Filed</th><th>Date Resolved</th><th>Resolved By</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $tc=['INQUIRY'=>'#3b82f6','SUGGESTION'=>'#8b5cf6','REQUEST'=>'#0d9488'];
        $sc=['OPEN'=>'#eab308','RESOLVED'=>'#10b981','CLOSED'=>'#8b5cf6'];
        if($tickets->num_rows>0):
        while($t=$tickets->fetch_assoc()):
            $tcol=$tc[$t['type']]??'#64748b';
            $scol=$sc[$t['status']]??'#94a3b8';
        ?>
        <tr>
            <td style="color:#94a3b8;"><?php echo $t['id']; ?></td>
            <td style="font-size:.75rem;color:#94a3b8;"><?php echo htmlspecialchars($t['ref_no']??'-'); ?></td>
            <td style="font-weight:600;"><?php echo htmlspecialchars($t['user_name']); ?></td>
            <td style="font-size:.78rem;"><?php echo htmlspecialchars($t['user_email']??'-'); ?></td>
            <td><?php echo htmlspecialchars($t['user_branch']??'-'); ?></td>
            <td><span class="badge" style="background:<?php echo $tcol;?>22;color:<?php echo $tcol;?>;"><?php echo $t['type']; ?></span></td>
            <td style="font-size:.8rem;"><?php echo htmlspecialchars($t['option_label']??'-'); ?></td>
            <td><span class="badge" style="background:<?php echo $scol;?>22;color:<?php echo $scol;?>;"><?php echo $t['status']; ?></span></td>
            <td style="white-space:nowrap;font-size:.8rem;"><?php echo date('M d, Y', strtotime($t['created_at'])); ?></td>
            <td style="white-space:nowrap;font-size:.8rem;"><?php echo $t['resolved_at']?date('M d, Y',strtotime($t['resolved_at'])):'-'; ?></td>
            <td style="font-size:.8rem;"><?php echo htmlspecialchars($t['resolved_by_name']??'-'); ?></td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="11" style="text-align:center;padding:2rem;color:#94a3b8;">No records match the selected filters.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
</div><!-- /fade-in -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
Chart.defaults.font.family="'Inter',sans-serif";Chart.defaults.color='#94a3b8';
new Chart(document.getElementById('typeChart'),{
    type:'doughnut',
    data:{labels:<?php echo json_encode($t_types);?>,datasets:[{data:<?php echo json_encode($t_counts);?>,backgroundColor:['#3b82f6','#8b5cf6','#0d9488'],borderWidth:4,borderColor:'#fff',hoverOffset:10}]},
    options:{maintainAspectRatio:false,cutout:'65%',plugins:{legend:{position:'bottom',labels:{padding:18,usePointStyle:true,font:{size:11,weight:'600'}}}}}
});
new Chart(document.getElementById('statusChart'),{
    type:'bar',
    data:{labels:<?php echo json_encode($t_stats);?>,datasets:[{label:'Count',data:<?php echo json_encode($t_scounts);?>,backgroundColor:['#eab308','#10b981','#8b5cf6'],borderRadius:8,barThickness:36}]},
    options:{maintainAspectRatio:false,scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{stepSize:1}},x:{grid:{display:false},ticks:{font:{weight:'600'}}}},plugins:{legend:{display:false}}}
});
window.addEventListener('beforeprint',function(){
    Chart.instances.forEach(c=>c.resize());
    // Force all overflow wrappers to expand fully for print
    document.querySelectorAll('[style*="overflow"]').forEach(function(el){
        el.dataset.overflowBak = el.style.overflow;
        el.dataset.maxhBak     = el.style.maxHeight;
        el.style.overflow  = 'visible';
        el.style.maxHeight = 'none';
        el.style.height    = 'auto';
    });
    document.querySelectorAll('.tbl-wrap,.admin-content,.admin-body,.admin-wrapper').forEach(function(el){
        el.dataset.overflowBak = el.style.overflow;
        el.style.overflow = 'visible';
        el.style.height   = 'auto';
    });
});
window.addEventListener('afterprint',function(){
    document.querySelectorAll('[data-overflow-bak]').forEach(function(el){
        el.style.overflow  = el.dataset.overflowBak || '';
        el.style.maxHeight = el.dataset.maxhBak || '';
        el.style.height    = '';
        delete el.dataset.overflowBak;
        delete el.dataset.maxhBak;
    });
});
</script>
<?php include '../includes/admin_footer.php'; ?>
