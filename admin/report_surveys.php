<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require_once '../config/db.php';
require_once '../includes/functions.php';
auth_guard('ADMIN');

$f_survey = isset($_GET['survey']) ? (int)$_GET['survey'] : 0;
$f_branch = isset($_GET['branch']) ? sanitize($_GET['branch']) : '';
$f_from   = isset($_GET['from'])   ? sanitize($_GET['from'])   : '';
$f_to     = isset($_GET['to'])     ? sanitize($_GET['to'])     : '';

$total_surveys   = $conn->query("SELECT COUNT(*) c FROM surveys")->fetch_assoc()['c'];
$active_surveys  = $conn->query("SELECT COUNT(*) c FROM surveys WHERE is_active=1")->fetch_assoc()['c'];
$total_responses = $conn->query("SELECT COUNT(*) c FROM survey_responses")->fetch_assoc()['c'];
$avg_per_survey  = $total_surveys > 0 ? round($total_responses / $total_surveys, 1) : 0;

$survey_data = null; $question_stats = []; $csat_overall = null; $response_count = 0;

if ($f_survey) {
    $survey_data = $conn->query("SELECT * FROM surveys WHERE id=$f_survey")->fetch_assoc();
    $rc_q = "SELECT COUNT(*) c FROM survey_responses WHERE survey_id=$f_survey";
    if ($f_branch) $rc_q .= " AND user_branch='".mysqli_real_escape_string($conn,$f_branch)."'";
    if ($f_from)   $rc_q .= " AND DATE(created_at)>='$f_from'";
    if ($f_to)     $rc_q .= " AND DATE(created_at)<='$f_to'";
    $response_count = $conn->query($rc_q)->fetch_assoc()['c'];

    $qs = $conn->query("SELECT * FROM questions WHERE survey_id=$f_survey ORDER BY id ASC");
    $ta = 0; $tr = 0;
    while ($q = $qs->fetch_assoc()) {
        $qid = $q['id'];
        $aW = "WHERE a.question_id=$qid";
        if ($f_branch) $aW .= " AND r.user_branch='".mysqli_real_escape_string($conn,$f_branch)."'";
        if ($f_from)   $aW .= " AND DATE(r.created_at)>='$f_from'";
        if ($f_to)     $aW .= " AND DATE(r.created_at)<='$f_to'";
        $ans = $conn->query("SELECT a.value FROM answers a JOIN survey_responses r ON a.response_id=r.id $aW");
        $s = ['question'=>$q,'csat'=>null,'dist'=>[],'texts'=>[],'agree'=>0,'total'=>0];
        if ($q['type'] === 'RATING') {
            $d = [1=>0,2=>0,3=>0,4=>0,5=>0];
            while ($a=$ans->fetch_assoc()) { $v=(int)$a['value']; if($v>=1&&$v<=5)$d[$v]++; }
            $agree=$d[4]+$d[5]; $tot=array_sum($d);
            $s['csat']=$tot>0?round($agree/$tot*100,1):null;
            $s['dist']=$d; $s['agree']=$agree; $s['total']=$tot;
            $ta+=$agree; $tr+=$tot;
        } elseif (in_array($q['type'],['CHOICE','MULTI_SELECT'])) {
            $d=[];
            while ($a=$ans->fetch_assoc()) { foreach(explode(',',$a['value']) as $v){$v=trim($v);if($v!=='')$d[$v]=($d[$v]??0)+1;} }
            arsort($d); $s['dist']=$d;
        } else {
            while ($a=$ans->fetch_assoc()) { if(trim($a['value'])!=='')$s['texts'][]=$a['value']; }
        }
        $question_stats[] = $s;
    }
    $csat_overall = $tr>0 ? round($ta/$tr*100,1) : null;
} else {
    $gl = $conn->query("SELECT a.value FROM answers a JOIN questions q ON a.question_id=q.id WHERE q.type='RATING'");
    $ga=0; $gt=0;
    while ($r=$gl->fetch_assoc()) { $v=(int)$r['value']; if($v>=1&&$v<=5){$gt++; if($v>=4)$ga++;} }
    $csat_overall = $gt>0 ? round($ga/$gt*100,1) : null;
}

$surveys_all    = $conn->query("SELECT id,title FROM surveys ORDER BY title");
$branches       = $conn->query("SELECT name FROM branches ORDER BY name");
$surveys_detail = $conn->query("SELECT s.*,COUNT(r.id) responses FROM surveys s LEFT JOIN survey_responses r ON s.id=r.survey_id GROUP BY s.id ORDER BY s.created_at DESC");

$sv_rows=$conn->query("SELECT s.title,COUNT(r.id) cnt FROM surveys s LEFT JOIN survey_responses r ON s.id=r.survey_id GROUP BY s.id ORDER BY cnt DESC LIMIT 6");
$sv_lbl=[]; $sv_cnt=[];
while ($r=$sv_rows->fetch_assoc()) { $sv_lbl[]=mb_substr($r['title'],0,22).(mb_strlen($r['title'])>22?'...':''); $sv_cnt[]=$r['cnt']; }

$tq = $f_survey ? "AND survey_id=$f_survey" : '';
$trend=$conn->query("SELECT DATE_FORMAT(created_at,'%b %Y') mo,YEAR(created_at) yr,MONTH(created_at) mn,COUNT(*) c FROM survey_responses WHERE created_at>=DATE_SUB(NOW(),INTERVAL 6 MONTH) $tq GROUP BY yr,mn,mo ORDER BY yr,mn");
$tlbl=[]; $tdat=[];
while ($t=$trend->fetch_assoc()) { $tlbl[]=$t['mo']; $tdat[]=$t['c']; }

$rw="WHERE 1=1";
if ($f_survey) $rw.=" AND r.survey_id=$f_survey";
if ($f_branch) $rw.=" AND r.user_branch='".mysqli_real_escape_string($conn,$f_branch)."'";
if ($f_from)   $rw.=" AND DATE(r.created_at)>='$f_from'";
if ($f_to)     $rw.=" AND DATE(r.created_at)<='$f_to'";
$responses=$conn->query("SELECT r.*,s.title st,(SELECT COUNT(*) FROM answers WHERE response_id=r.id) ac FROM survey_responses r JOIN surveys s ON r.survey_id=s.id $rw ORDER BY r.created_at DESC");
$filtered_count=$responses->num_rows;

function csatTier($s){
    if($s===null) return['label'=>'N/A','color'=>'#94a3b8','bg'=>'rgba(148,163,184,0.1)'];
    if($s>=85) return['label'=>'Excellent','color'=>'#10b981','bg'=>'rgba(16,185,129,0.15)'];
    if($s>=70) return['label'=>'Good','color'=>'#3b82f6','bg'=>'rgba(59,130,246,0.15)'];
    return['label'=>'Needs Improvement','color'=>'#ef4444','bg'=>'rgba(239,68,68,0.15)'];
}

$page_title = "Surveys Report";
include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>
<style>
:root {
    --bg: #f8fafc; --surface: #ffffff; --surface2: #f1f5f9;
    --text: #0f172a; --text2: #334155; --text3: #64748b; --text4: #94a3b8;
    --border: #e2e8f0; --border2: #cbd5e1;
    --accent: #0e83b5; --accent2: #0369a1;
    --purple: #8b5cf6; --purple2: #7c3aed;
    --green: #10b981; --red: #ef4444; --blue: #3b82f6; --amber: #f59e0b;
    --shadow: 0 2px 12px rgba(0,0,0,0.07);
    --shadow-lg: 0 8px 32px rgba(0,0,0,0.10);
    --radius: 1rem; --radius-sm: 0.6rem;
}
@media (prefers-color-scheme: dark) {
    :root {
        --bg: #0f172a; --surface: #1e293b; --surface2: #283548;
        --text: #f1f5f9; --text2: #cbd5e1; --text3: #94a3b8; --text4: #64748b;
        --border: #334155; --border2: #475569;
        --accent: #38bdf8; --accent2: #0ea5e9;
        --shadow: 0 2px 12px rgba(0,0,0,0.3);
        --shadow-lg: 0 8px 32px rgba(0,0,0,0.4);
    }
    .kpi { background: var(--surface) !important; border-color: var(--border) !important; }
    .chart-box { background: var(--surface) !important; border-color: var(--border) !important; }
    .filter-bar { background: var(--surface) !important; border-color: var(--border) !important; }
    .tbl-wrap { background: var(--surface) !important; border-color: var(--border) !important; }
    .tbl-hd { background: var(--surface2) !important; border-color: var(--border) !important; }
    .rtbl th { background: var(--surface2) !important; color: var(--text3) !important; }
    .rtbl td { color: var(--text2) !important; border-color: var(--border) !important; }
    .rtbl tr:hover td { background: var(--surface2) !important; }
    .filter-grp select, .filter-grp input { background: var(--surface2) !important; border-color: var(--border) !important; color: var(--text) !important; }
    .report-section { background: var(--surface) !important; border-color: var(--border) !important; }
    .section-hd { background: var(--surface2) !important; border-color: var(--border) !important; }
    .q-card { background: var(--surface2) !important; border-color: var(--border) !important; }
    .q-bar { background: var(--border) !important; }
    .print-hd { border-color: var(--purple) !important; }
}
.rpt-hero { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:2rem; }
.kpi-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:1rem; margin-bottom:1.75rem; }
.kpi { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:1.1rem 1.25rem; text-align:center; transition:box-shadow .2s; }
.kpi:hover { box-shadow:var(--shadow-lg); }
.kpi-lbl { font-size:.65rem; text-transform:uppercase; color:var(--text4); font-weight:700; letter-spacing:.05em; margin-bottom:.3rem; }
.kpi-val { font-size:1.9rem; font-weight:900; line-height:1; }
.chart-pair { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:1.75rem; }
@media(max-width:900px){.chart-pair{grid-template-columns:1fr;}}
.chart-box { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:1.25rem; }
.chart-box h4 { font-size:.88rem; font-weight:700; color:var(--text2); margin-bottom:1rem; }
.chart-wrap { position:relative; height:260px; }
.filter-bar { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:1.25rem 1.5rem; margin-bottom:1.5rem; display:flex; gap:.75rem; flex-wrap:wrap; align-items:flex-end; }
.filter-grp { display:flex; flex-direction:column; gap:.25rem; }
.filter-grp label { font-size:.68rem; font-weight:700; text-transform:uppercase; color:var(--text4); letter-spacing:.04em; }
.filter-grp select,.filter-grp input { padding:.45rem .7rem; border:1px solid var(--border); border-radius:.5rem; font-size:.85rem; color:var(--text2); background:var(--surface2); }
.tbl-wrap { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; margin-bottom:1.75rem; }
.tbl-hd { background:var(--surface2); padding:.8rem 1.25rem; font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--text3); border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
.rtbl { width:100%; border-collapse:collapse; font-size:.82rem; }
.rtbl th { background:var(--surface2); padding:.6rem .9rem; text-align:left; font-size:.7rem; font-weight:700; text-transform:uppercase; color:var(--text4); }
.rtbl td { padding:.6rem .9rem; border-bottom:1px solid var(--border); color:var(--text2); vertical-align:middle; }
.rtbl tr:last-child td { border-bottom:none; }
.rtbl tr:hover td { background:var(--surface2); }
.report-section { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; margin-bottom:1.5rem; }
.section-hd { background:var(--surface2); padding:.9rem 1.25rem; font-size:.8rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:var(--text3); border-bottom:1px solid var(--border); display:flex; align-items:center; gap:.5rem; }
.section-body { padding:1.25rem; }
.csat-badge { display:inline-flex; align-items:center; gap:.4rem; padding:.3rem .8rem; border-radius:2rem; font-size:.8rem; font-weight:800; }
.q-card { background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); padding:1rem; margin-bottom:1rem; }
.q-title { font-size:.88rem; font-weight:700; color:var(--text); margin-bottom:.75rem; }
.q-bar-wrap { margin-bottom:.35rem; }
.q-bar-label { display:flex; justify-content:space-between; font-size:.72rem; color:var(--text3); margin-bottom:.2rem; }
.q-bar-track { background:var(--border); border-radius:2rem; height:8px; overflow:hidden; }
.q-bar { height:100%; border-radius:2rem; transition:width .6s ease; }
.tier-pill { display:inline-block; padding:.2rem .65rem; border-radius:2rem; font-size:.7rem; font-weight:800; }
.exec-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem; }
.exec-item { background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); padding:1rem 1.1rem; }
.exec-label { font-size:.65rem; text-transform:uppercase; font-weight:700; color:var(--text4); letter-spacing:.05em; margin-bottom:.3rem; }
.exec-value { font-size:1.05rem; font-weight:800; color:var(--text); }
.rec-item { display:flex; gap:.75rem; padding:.75rem 0; border-bottom:1px solid var(--border); }
.rec-item:last-child { border-bottom:none; }
.rec-num { background:var(--purple); color:#fff; border-radius:50%; width:1.6rem; height:1.6rem; display:flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:800; flex-shrink:0; margin-top:.1rem; }
.rec-text { font-size:.88rem; color:var(--text2); line-height:1.5; }
.rec-text strong { color:var(--text); display:block; margin-bottom:.2rem; }
.badge { display:inline-block; padding:.15rem .5rem; border-radius:.3rem; font-size:.7rem; font-weight:700; }
.print-hd { display:none; text-align:center; margin-bottom:1.25rem; padding-bottom:.75rem; border-bottom:2px solid var(--purple); }
.print-hd h1 { font-size:1.4rem; color:var(--text); margin-bottom:.2rem; }
.print-hd p { font-size:.78rem; color:var(--text3); }
@media print {
    @page { size:A4 portrait; margin:12mm; }
    .admin-sidebar,.admin-header,.filter-bar,.no-print,.rpt-hero { display:none !important; }
    .admin-wrapper,.admin-body,.admin-content { display:block !important; overflow:visible !important; height:auto !important; padding:0 !important; margin:0 !important; width:100% !important; position:static !important; }
    body,html { background:#fff !important; overflow:visible !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .print-hd { display:block !important; }
    .kpi-row { grid-template-columns:repeat(4,1fr); }
    .chart-pair { grid-template-columns:1fr 1fr; }
    .tbl-wrap,.report-section { break-inside:auto; border:1px solid #e2e8f0 !important; }
    .rtbl tr { break-inside:avoid; }
    .rtbl th { background:#e2e8f0 !important; }
    :root { --bg:#fff; --surface:#fff; --surface2:#f8fafc; --text:#0f172a; --text2:#334155; --text3:#64748b; --text4:#94a3b8; --border:#e2e8f0; }
}
</style>
<?php
// Part 3: Hero, KPIs, Executive Summary, Methodology, CSAT Overview
$tier = csatTier($csat_overall);
$generated = date('F d, Y \a\t h:i A');
?>
<div class="fade-in">

<!-- Print Header -->
<div class="print-hd">
    <h1>FCPAMS - Membership Satisfaction Survey Report: <?php echo date('Y'); ?></h1>
    <p>Generated: <?php echo $generated; ?> &nbsp;&middot;&nbsp; Prepared by: <?php echo htmlspecialchars($_SESSION['name']); ?></p>
    <?php if ($survey_data): ?><p style="margin-top:.3rem;font-size:.75rem;">Survey: <strong><?php echo htmlspecialchars($survey_data['title']); ?></strong><?php if($f_branch) echo " &nbsp;| Branch: <strong>$f_branch</strong>"; ?></p><?php endif; ?>
</div>

<!-- Hero -->
<div class="rpt-hero no-print" style="background:linear-gradient(135deg,#1e1b4b 0%,#312e81 40%,#0e3a6e 100%);border-radius:1.25rem;padding:2rem 2.25rem;margin-bottom:2rem;box-shadow:0 8px 32px rgba(14,131,181,0.3);">
    <div>
        <a href="dashboard.php" style="color:rgba(255,255,255,0.6);text-decoration:none;font-size:.82rem;display:inline-flex;align-items:center;gap:.4rem;margin-bottom:.75rem;transition:color .2s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.6)'"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
            <div style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.18);border-radius:.9rem;width:3.2rem;height:3.2rem;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(6px);">
                <i class="fas fa-poll-h" style="font-size:1.4rem;color:#93c5fd;"></i>
            </div>
            <div>
                <div style="display:flex;align-items:center;gap:.6rem;">
                    <h1 style="color:#fff;margin:0;font-size:1.75rem;font-weight:800;letter-spacing:-0.02em;">Surveys Report</h1>
                    <span style="background:linear-gradient(135deg,#3b82f6,#0e83b5);color:#fff;font-size:.65rem;font-weight:800;padding:.2rem .6rem;border-radius:1rem;letter-spacing:.06em;text-transform:uppercase;">CSAT Analytics</span>
                </div>
                <p style="color:rgba(255,255,255,.65);margin:.2rem 0 0;font-size:.9rem;">Citizen engagement &amp; satisfaction survey insights - <?php echo date('Y'); ?></p>
            </div>
        </div>
    </div>
    <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;" class="no-print">
        <button onclick="openExportModal('<?php echo $f_survey; ?>', '<?php echo htmlspecialchars($f_branch, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($f_from, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($f_to, ENT_QUOTES); ?>')"
                style="padding:.75rem 1.6rem;background:linear-gradient(135deg,#10b981,#059669);border:1px solid rgba(16,185,129,.5);color:#fff;border-radius:.75rem;font-weight:700;font-size:.88rem;cursor:pointer;display:flex;align-items:center;gap:.55rem;backdrop-filter:blur(6px);transition:all .2s;white-space:nowrap;box-shadow:0 4px 14px rgba(16,185,129,0.35);">
            <i class="fas fa-file-excel"></i> Export to Excel
        </button>
        <button onclick="window.print()" style="padding:.75rem 1.6rem;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:.75rem;font-weight:700;font-size:.88rem;cursor:pointer;display:flex;align-items:center;gap:.55rem;backdrop-filter:blur(6px);transition:all .2s;white-space:nowrap;" onmouseover="this.style.background='rgba(255,255,255,.25)'" onmouseout="this.style.background='rgba(255,255,255,.15)'">
            <i class="fas fa-print"></i> Print / PDF
        </button>
    </div>
</div>

<!-- KPIs -->
<div class="kpi-row">
    <div class="kpi" style="border-left:4px solid var(--purple);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem;">
            <div class="kpi-lbl">Total Surveys</div>
            <div style="background:rgba(139,92,246,.12);border-radius:.5rem;width:2rem;height:2rem;display:flex;align-items:center;justify-content:center;"><i class="fas fa-clipboard-list" style="color:var(--purple);font-size:.85rem;"></i></div>
        </div>
        <div class="kpi-val" style="color:var(--purple);"><?php echo $total_surveys; ?></div>
    </div>
    <div class="kpi" style="border-left:4px solid var(--green);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem;">
            <div class="kpi-lbl">Active Surveys</div>
            <div style="background:rgba(16,185,129,.12);border-radius:.5rem;width:2rem;height:2rem;display:flex;align-items:center;justify-content:center;"><i class="fas fa-toggle-on" style="color:var(--green);font-size:.85rem;"></i></div>
        </div>
        <div class="kpi-val" style="color:var(--green);"><?php echo $active_surveys; ?></div>
    </div>
    <div class="kpi" style="border-left:4px solid var(--blue);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem;">
            <div class="kpi-lbl">Total Responses</div>
            <div style="background:rgba(59,130,246,.12);border-radius:.5rem;width:2rem;height:2rem;display:flex;align-items:center;justify-content:center;"><i class="fas fa-users" style="color:var(--blue);font-size:.85rem;"></i></div>
        </div>
        <div class="kpi-val" style="color:var(--blue);"><?php echo $total_responses; ?></div>
    </div>
    <div class="kpi" style="border-left:4px solid <?php echo $tier['color']; ?>;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem;">
            <div class="kpi-lbl">Overall CSAT</div>
            <div style="background:<?php echo $tier['bg']; ?>;border-radius:.5rem;width:2rem;height:2rem;display:flex;align-items:center;justify-content:center;"><i class="fas fa-star" style="color:<?php echo $tier['color']; ?>;font-size:.85rem;"></i></div>
        </div>
        <div class="kpi-val" style="color:<?php echo $tier['color']; ?>;"><?php echo $csat_overall !== null ? $csat_overall.'%' : 'N/A'; ?></div>
        <div style="font-size:.68rem;font-weight:700;color:<?php echo $tier['color']; ?>;margin-top:.3rem;"><?php echo $tier['label']; ?></div>
    </div>
    <div class="kpi" style="border-left:4px solid var(--amber);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem;">
            <div class="kpi-lbl">Avg / Survey</div>
            <div style="background:rgba(245,158,11,.12);border-radius:.5rem;width:2rem;height:2rem;display:flex;align-items:center;justify-content:center;"><i class="fas fa-chart-line" style="color:var(--amber);font-size:.85rem;"></i></div>
        </div>
        <div class="kpi-val" style="color:var(--amber);"><?php echo $avg_per_survey; ?></div>
    </div>
</div>

<!-- Filter -->
<form method="GET" class="filter-bar no-print">
    <div class="filter-grp">
        <label>Survey</label>
        <select name="survey">
            <option value="">All Surveys</option>
            <?php $surveys_all->data_seek(0); while ($sv=$surveys_all->fetch_assoc()): ?>
            <option value="<?php echo $sv['id']; ?>" <?php echo $f_survey==$sv['id']?'selected':''; ?>><?php echo htmlspecialchars($sv['title']); ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="filter-grp">
        <label>Branch</label>
        <select name="branch">
            <option value="">All Branches</option>
            <?php $branches->data_seek(0); while ($b=$branches->fetch_assoc()): ?>
            <option value="<?php echo htmlspecialchars($b['name']); ?>" <?php echo $f_branch===$b['name']?'selected':''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="filter-grp"><label>From</label><input type="date" name="from" value="<?php echo htmlspecialchars($f_from); ?>"></div>
    <div class="filter-grp"><label>To</label><input type="date" name="to" value="<?php echo htmlspecialchars($f_to); ?>"></div>
    <div style="display:flex;gap:.5rem;align-items:flex-end;">
        <button type="submit" class="btn btn-primary" style="padding:.45rem 1.1rem;font-size:.85rem;">Filter</button>
        <a href="report_surveys.php" class="btn btn-outline" style="padding:.45rem 1rem;font-size:.85rem;">Clear</a>
    </div>
</form>

<!-- Methodology & CSAT Interpretation -->
<div class="report-section">
    <div class="section-hd"><i class="fas fa-flask" style="color:var(--blue);"></i> Methodology &amp; CSAT Interpretation</div>
    <div class="section-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
            <div>
                <p style="font-size:.88rem;color:var(--text2);margin-bottom:.75rem;">Responses are collected from citizen members via the FCPAMS portal using a 5-point Likert scale (1 = Strongly Disagree, 5 = Strongly Agree). The CSAT score is computed using the following formula:</p>
                <div style="background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:1rem;text-align:center;font-size:.85rem;color:var(--text3);">
                    <div style="font-size:1rem;font-weight:700;color:var(--text);margin-bottom:.5rem;">CSAT Formula</div>
                    <div style="border-top:2px solid var(--text);padding-top:.4rem;font-size:.88rem;font-weight:600;color:var(--text2);">Agree (4) + Strongly Agree (5) responses</div>
                    <div style="height:2px;background:var(--text);margin:.3rem auto;width:80%;"></div>
                    <div style="font-size:.88rem;font-weight:600;color:var(--text2);">Total Number of Responses</div>
                    <div style="margin-top:.4rem;font-weight:800;color:var(--accent);">&times; 100</div>
                </div>
            </div>
            <div>
                <div style="font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--text4);margin-bottom:.6rem;">CSAT Score Tiers</div>
                <div style="display:flex;flex-direction:column;gap:.5rem;">
                    <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem .85rem;background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);border-radius:var(--radius-sm);border-left:4px solid #10b981;">
                        <div style="font-weight:800;color:#10b981;min-width:5rem;">85% - 100%</div>
                        <div style="font-size:.82rem;color:var(--text2);"><strong style="color:var(--text);">Excellent</strong> - Exceeding expectations; high member satisfaction.</div>
                    </div>
                    <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem .85rem;background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.25);border-radius:var(--radius-sm);border-left:4px solid #3b82f6;">
                        <div style="font-weight:800;color:#3b82f6;min-width:5rem;">70% - 84%</div>
                        <div style="font-size:.82rem;color:var(--text2);"><strong style="color:var(--text);">Good</strong> - Room for improvement; satisfaction is acceptable.</div>
                    </div>
                    <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem .85rem;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:var(--radius-sm);border-left:4px solid #ef4444;">
                        <div style="font-weight:800;color:#ef4444;min-width:5rem;">Below 70%</div>
                        <div style="font-size:.82rem;color:var(--text2);"><strong style="color:var(--text);">Needs Improvement</strong> - Immediate corrective action needed.</div>
                    </div>
                </div>
            </div>
        </div>
        <div style="font-size:.78rem;color:var(--text4);padding:.5rem .75rem;background:var(--surface2);border-radius:var(--radius-sm);">
            <i class="fas fa-info-circle" style="color:var(--accent);"></i> Rating questions use a 5-point scale. Responses of 4 ("Agree") and 5 ("Strongly Agree") are counted as satisfied. Choice and text questions are analyzed separately for qualitative insights.
        </div>
    </div>
</div>

<!-- Charts -->
<div class="chart-pair">
    <div class="chart-box" style="border-top:3px solid var(--accent);">
        <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:1rem;">
            <div style="background:linear-gradient(135deg,var(--accent),var(--blue));border-radius:.5rem;width:2rem;height:2rem;display:flex;align-items:center;justify-content:center;"><i class="fas fa-chart-bar" style="color:#fff;font-size:.8rem;"></i></div>
            <h4 style="margin:0;">Responses by Survey <span style="color:var(--text4);font-weight:400;font-size:.8rem;">(Top 6)</span></h4>
        </div>
        <div class="chart-wrap"><canvas id="surveysChart"></canvas></div>
    </div>
    <div class="chart-box" style="border-top:3px solid var(--purple);">
        <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:1rem;">
            <div style="background:linear-gradient(135deg,var(--purple2),var(--purple));border-radius:.5rem;width:2rem;height:2rem;display:flex;align-items:center;justify-content:center;"><i class="fas fa-chart-line" style="color:#fff;font-size:.8rem;"></i></div>
            <h4 style="margin:0;">Response Trend <span style="color:var(--text4);font-weight:400;font-size:.8rem;">(Last 6 Months)</span></h4>
        </div>
        <div class="chart-wrap"><canvas id="trendChart"></canvas></div>
    </div>
</div>

<!-- Survey Summary Table -->
<div class="tbl-wrap">
    <div class="tbl-hd"><span><i class="fas fa-clipboard-list"></i> All Surveys - Summary &amp; Analytics</span></div>
    <table class="rtbl">
        <thead><tr><th>#</th><th>Survey Title</th><th>Description</th><th>Status</th><th>Responses</th><th>Created</th><th class="no-print" style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php while ($s=$surveys_detail->fetch_assoc()): ?>
        <tr>
            <td style="color:var(--text4);"><?php echo $s['id']; ?></td>
            <td style="font-weight:600;color:var(--text);"><?php echo htmlspecialchars($s['title']); ?></td>
            <td style="font-size:.78rem;color:var(--text3);max-width:200px;"><?php echo htmlspecialchars(mb_substr($s['description']??'',0,60)).(mb_strlen($s['description']??'')>60?'...':''); ?></td>
            <td><?php if($s['is_active']): ?><span class="badge" style="background:#dcfce7;color:#16a34a;">Active</span><?php else: ?><span class="badge" style="background:var(--surface2);color:var(--text4);">Inactive</span><?php endif; ?></td>
            <td style="font-weight:800;font-size:1.05rem;color:var(--accent);"><?php echo $s['responses']; ?></td>
            <td style="font-size:.8rem;color:var(--text3);"><?php echo date('M d, Y',strtotime($s['created_at'])); ?></td>
            <td class="no-print" style="text-align:right;white-space:nowrap;">
                <a href="report_surveys.php?survey=<?php echo $s['id']; ?>" class="btn btn-outline" style="padding:.25rem .65rem;font-size:.75rem;margin-right:.3rem;" title="View Data & Statistical Analysis">
                    <i class="fas fa-chart-pie"></i> View Data
                </a>
                <button type="button" onclick="openExportModal(<?php echo $s['id']; ?>)" class="btn" style="padding:.25rem .65rem;font-size:.75rem;background:#10b981;color:#fff;border-radius:.4rem;font-weight:600;border:none;cursor:pointer;" title="Export Survey Report Analysis (Date).xlsx">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php if ($f_survey && !empty($question_stats)): ?>
<!-- ═══════════════════════════════════════════════════════════════════════════
     PER-QUESTION STATISTICAL ANALYSIS (Google Forms-style)
═══════════════════════════════════════════════════════════════════════════ -->
<div class="report-section" id="survey-analysis-section" style="border-top:4px solid var(--purple);">
    <div class="section-hd" style="justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
        <span><i class="fas fa-chart-pie" style="color:var(--purple);"></i>&nbsp; Question-by-Question Analysis
            <?php if ($survey_data): ?>
            <span style="font-size:.72rem;font-weight:400;color:var(--text3);margin-left:.5rem;">— <?php echo htmlspecialchars($survey_data['title']); ?> &nbsp;·&nbsp; <?php echo $response_count; ?> response<?php echo $response_count!=1?'s':''; ?></span>
            <?php endif; ?>
        </span>
        <div style="display:flex;align-items:center;gap:.6rem;" class="no-print">
            <span style="font-size:.72rem;font-weight:600;color:var(--purple);background:rgba(139,92,246,.1);padding:.25rem .6rem;border-radius:1rem;">
                Overall CSAT: <?php echo $csat_overall !== null ? $csat_overall.'%' : 'N/A'; ?>
            </span>
            <button type="button" onclick="openExportModal('<?php echo $f_survey; ?>', '<?php echo htmlspecialchars($f_branch, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($f_from, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($f_to, ENT_QUOTES); ?>')"
               class="btn" style="background:#10b981;color:#fff;padding:.35rem .9rem;font-size:.78rem;font-weight:700;border-radius:.5rem;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;">
                <i class="fas fa-file-excel"></i> Export Analysis to Excel
            </button>
        </div>
    </div>
    <div class="section-body">
        <?php foreach ($question_stats as $qi => $qs):
            $q   = $qs['question'];
            $typ = $q['type'];
        ?>
        <div class="q-card" style="margin-bottom:1.25rem;">
            <!-- Question header -->
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.85rem;flex-wrap:wrap;gap:.5rem;">
                <div class="q-title">
                    <span style="color:var(--text4);font-size:.75rem;font-weight:700;margin-right:.4rem;">Q<?php echo $qi+1; ?>.</span>
                    <?php echo htmlspecialchars($q['text']); ?>
                </div>
                <div style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;">
                    <span class="badge" style="background:rgba(139,92,246,.12);color:var(--purple);font-size:.68rem;"><?php echo $typ; ?></span>
                    <?php if ($typ === 'RATING' && $qs['csat'] !== null):
                        $qt = csatTier($qs['csat']); ?>
                    <span class="tier-pill" style="background:<?php echo $qt['bg']; ?>;color:<?php echo $qt['color']; ?>;"><?php echo $qs['csat']; ?>% CSAT &mdash; <?php echo $qt['label']; ?></span>
                    <?php endif; ?>
                    <span class="badge" style="background:rgba(14,131,181,.1);color:var(--accent);"><?php echo $qs['total']; ?> response<?php echo $qs['total']!=1?'s':''; ?></span>
                </div>
            </div>

            <?php if ($typ === 'RATING'): ?>
            <!-- Rating distribution bars -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div>
                    <?php
                    $labels = [1=>'Strongly Disagree',2=>'Disagree',3=>'Neutral',4=>'Agree',5=>'Strongly Agree'];
                    $colors = [1=>'#ef4444',2=>'#f97316',3=>'#eab308',4=>'#3b82f6',5=>'#10b981'];
                    foreach (array_reverse($qs['dist'], true) as $star => $cnt):
                        $pct = $qs['total'] > 0 ? round($cnt / $qs['total'] * 100, 1) : 0;
                    ?>
                    <div class="q-bar-wrap">
                        <div class="q-bar-label">
                            <span><?php echo $star; ?> ★ <?php echo $labels[$star]; ?></span>
                            <span style="font-weight:700;color:var(--text);"><?php echo $cnt; ?> <span style="color:var(--text4);font-weight:400;">(<?php echo $pct; ?>%)</span></span>
                        </div>
                        <div class="q-bar-track">
                            <div class="q-bar" style="width:<?php echo $pct; ?>%;background:<?php echo $colors[$star]; ?>;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="display:flex;flex-direction:column;gap:.6rem;">
                    <div class="exec-item">
                        <div class="exec-label">Average Rating</div>
                        <?php
                        $sum=0; $tot=0;
                        foreach($qs['dist'] as $s=>$c){$sum+=$s*$c;$tot+=$c;}
                        $avg = $tot>0?round($sum/$tot,2):0;
                        $acolor = $avg>=4?'var(--green)':($avg>=3?'var(--amber)':'var(--red)');
                        ?>
                        <div class="exec-value" style="color:<?php echo $acolor; ?>;font-size:1.4rem;"><?php echo $avg; ?> <span style="font-size:.85rem;color:var(--text4);">/ 5.0</span></div>
                    </div>
                    <div class="exec-item">
                        <div class="exec-label">Satisfied (4-5 ★)</div>
                        <div class="exec-value" style="color:var(--green);"><?php echo $qs['agree']; ?> <span style="font-size:.8rem;color:var(--text4);">respondents</span></div>
                    </div>
                    <div class="exec-item">
                        <div class="exec-label">CSAT Score</div>
                        <?php $ct=csatTier($qs['csat']); ?>
                        <div class="exec-value" style="color:<?php echo $ct['color']; ?>;"><?php echo $qs['csat'] !== null ? $qs['csat'].'%' : 'N/A'; ?></div>
                    </div>
                </div>
            </div>

            <?php elseif (in_array($typ, ['CHOICE', 'MULTI_SELECT'])): ?>
            <!-- Choice distribution bars -->
            <?php if (empty($qs['dist'])): ?>
                <p style="color:var(--text4);font-size:.85rem;">No responses yet.</p>
            <?php else:
                $maxVal = max($qs['dist']);
                $palette = ['#0e83b5','#8b5cf6','#10b981','#f59e0b','#ef4444','#ec4899','#06b6d4','#84cc16'];
                $ci = 0;
                foreach ($qs['dist'] as $opt => $cnt):
                    $pct = $qs['total'] > 0 ? round($cnt / $qs['total'] * 100, 1) : 0;
                    $col = $palette[$ci % count($palette)];
                    $ci++;
            ?>
                <div class="q-bar-wrap">
                    <div class="q-bar-label">
                        <span style="font-weight:500;"><?php echo htmlspecialchars($opt); ?></span>
                        <span style="font-weight:700;color:var(--text);"><?php echo $cnt; ?> <span style="color:var(--text4);font-weight:400;">(<?php echo $pct; ?>%)</span></span>
                    </div>
                    <div class="q-bar-track">
                        <div class="q-bar" style="width:<?php echo $pct; ?>%;background:<?php echo $col; ?>;"></div>
                    </div>
                </div>
            <?php endforeach; endif; ?>

            <?php else: ?>
            <!-- Text / open-ended responses -->
            <?php if (empty($qs['texts'])): ?>
                <p style="color:var(--text4);font-size:.85rem;">No text responses yet.</p>
            <?php else: ?>
                <div style="max-height:220px;overflow-y:auto;display:flex;flex-direction:column;gap:.5rem;padding-right:.25rem;">
                    <?php foreach ($qs['texts'] as $ti => $txt): ?>
                    <div style="background:var(--surface);border:1px solid var(--border);border-radius:.5rem;padding:.6rem .85rem;font-size:.85rem;color:var(--text2);display:flex;gap:.6rem;align-items:flex-start;">
                        <span style="color:var(--text4);font-size:.72rem;flex-shrink:0;margin-top:.1rem;"><?php echo $ti+1; ?>.</span>
                        <span><?php echo nl2br(htmlspecialchars($txt)); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Response Log -->
<div class="tbl-wrap">
    <div class="tbl-hd">
        <span><i class="fas fa-users"></i> Response Log - <?php echo $filtered_count; ?> record<?php echo $filtered_count!=1?'s':''; ?></span>
        <?php if ($f_survey||$f_branch||$f_from||$f_to): ?><span style="font-size:.75rem;color:var(--accent);font-weight:600;background:var(--surface2);padding:.2rem .6rem;border-radius:.4rem;">Filtered</span><?php endif; ?>
    </div>
    <div style="overflow-x:auto;">
        <table class="rtbl">
            <thead><tr><th>#</th><th>Survey</th><th>Respondent</th><th>Email</th><th>Branch</th><th>Answers</th><th>Submitted</th><th class="no-print">Detail</th></tr></thead>
            <tbody>
            <?php if ($filtered_count>0): while ($r=$responses->fetch_assoc()): ?>
            <tr>
                <td style="color:var(--text4);"><?php echo $r['id']; ?></td>
                <td style="font-weight:600;max-width:150px;color:var(--text);"><?php echo htmlspecialchars($r['st']); ?></td>
                <td style="font-weight:600;color:var(--text);"><?php echo htmlspecialchars($r['user_name']); ?></td>
                <td style="font-size:.78rem;color:var(--text3);"><?php echo htmlspecialchars($r['user_email']??'-'); ?></td>
                <td style="color:var(--text2);"><?php echo htmlspecialchars($r['user_branch']??'-'); ?></td>
                <td style="font-weight:700;color:var(--accent);"><?php echo $r['ac']; ?></td>
                <td style="white-space:nowrap;font-size:.8rem;color:var(--text3);"><?php echo date('M d, Y',strtotime($r['created_at'])); ?></td>
                <td class="no-print"><a href="<?php echo BASE_URL; ?>admin/survey_response.php?id=<?php echo $r['id']; ?>" class="btn btn-outline" style="padding:.2rem .6rem;font-size:.75rem;">View</a></td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text4);">No responses match the selected filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     EXPORT / PREVIEW OPTIONS MODAL
═══════════════════════════════════════════════════════════════════════════ -->
<div id="exportSurveyModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,0.6);backdrop-filter:blur(6px);align-items:center;justify-content:center;">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:1.25rem;padding:2rem;width:100%;max-width:500px;margin:1rem;box-shadow:var(--shadow-lg);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;border-bottom:1px solid var(--border);padding-bottom:.85rem;">
            <div style="display:flex;align-items:center;gap:.6rem;">
                <div style="background:rgba(16,185,129,.12);border-radius:.6rem;width:2.4rem;height:2.4rem;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-file-excel" style="color:#10b981;font-size:1.2rem;"></i>
                </div>
                <div>
                    <h3 style="margin:0;color:var(--text);font-size:1.1rem;font-weight:800;">Export Survey Analysis</h3>
                    <p style="margin:.1rem 0 0;font-size:.78rem;color:var(--text3);">Select a survey to view statistical analysis or export to Excel.</p>
                </div>
            </div>
            <button onclick="closeExportModal()" style="background:transparent;border:none;font-size:1.4rem;cursor:pointer;color:var(--text4);line-height:1;">&#x2715;</button>
        </div>

        <form id="exportModalForm" method="GET" action="export_survey_excel.php">
            <div style="margin-bottom:1rem;">
                <label style="display:block;font-size:.75rem;font-weight:700;text-transform:uppercase;color:var(--text4);letter-spacing:.04em;margin-bottom:.3rem;">Select Survey <span style="color:#ef4444;">*</span></label>
                <select id="modal_survey_select" name="survey" required style="width:100%;padding:.6rem .85rem;border:1px solid var(--border);border-radius:.6rem;font-size:.9rem;background:var(--surface2);color:var(--text);">
                    <option value="">-- Choose a Survey --</option>
                    <?php $surveys_all->data_seek(0); while ($sv=$surveys_all->fetch_assoc()): ?>
                    <option value="<?php echo $sv['id']; ?>" <?php echo $f_survey==$sv['id']?'selected':''; ?>><?php echo htmlspecialchars($sv['title']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Date Range & Branch Filters -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem;">
                <div>
                    <label style="display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--text4);letter-spacing:.04em;margin-bottom:.25rem;"><i class="far fa-calendar-alt"></i> Date From</label>
                    <input type="date" id="modal_from_date" name="from" value="<?php echo htmlspecialchars($f_from); ?>" style="width:100%;padding:.45rem .7rem;border:1px solid var(--border);border-radius:.5rem;font-size:.85rem;background:var(--surface2);color:var(--text);">
                </div>
                <div>
                    <label style="display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--text4);letter-spacing:.04em;margin-bottom:.25rem;"><i class="far fa-calendar-alt"></i> Date To</label>
                    <input type="date" id="modal_to_date" name="to" value="<?php echo htmlspecialchars($f_to); ?>" style="width:100%;padding:.45rem .7rem;border:1px solid var(--border);border-radius:.5rem;font-size:.85rem;background:var(--surface2);color:var(--text);">
                </div>
            </div>

            <div style="margin-bottom:1.25rem;">
                <label style="display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--text4);letter-spacing:.04em;margin-bottom:.25rem;"><i class="fas fa-building"></i> Branch (Optional)</label>
                <select id="modal_branch_select" name="branch" style="width:100%;padding:.5rem .85rem;border:1px solid var(--border);border-radius:.6rem;font-size:.85rem;background:var(--surface2);color:var(--text);">
                    <option value="">All Branches</option>
                    <?php $branches->data_seek(0); while ($b=$branches->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($b['name']); ?>" <?php echo $f_branch===$b['name']?'selected':''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:.75rem;padding:.8rem 1rem;margin-bottom:1.25rem;font-size:.8rem;color:var(--text2);">
                <div style="font-weight:700;color:var(--text);margin-bottom:.3rem;display:flex;align-items:center;gap:.4rem;">
                    <i class="fas fa-info-circle" style="color:var(--accent);"></i> Export File Details:
                </div>
                <ul style="margin:.3rem 0 0 1.2rem;padding:0;color:var(--text3);line-height:1.5;">
                    <li><strong>Filename:</strong> <code>Survey Report Analysis (<?php echo date('M d, Y'); ?>).xlsx</code></li>
                    <li><strong>Format:</strong> Microsoft Excel (.xlsx) with 3 sheets</li>
                    <li><strong>Includes:</strong> CSAT %, Rating Distributions, Choices, Open-ended Text &amp; Response Logs</li>
                </ul>
            </div>

            <div style="display:flex;gap:.75rem;flex-wrap:wrap;justify-content:flex-end;">
                <button type="button" onclick="viewAnalysisFromModal()" class="btn btn-outline" style="padding:.6rem 1.1rem;font-size:.85rem;font-weight:600;display:flex;align-items:center;gap:.4rem;">
                    <i class="fas fa-eye"></i> Preview Filtered Data First
                </button>
                <button type="submit" class="btn" style="padding:.6rem 1.3rem;font-size:.85rem;font-weight:700;background:linear-gradient(135deg,#10b981,#059669);color:#fff;border-radius:.5rem;border:none;cursor:pointer;display:flex;align-items:center;gap:.4rem;">
                    <i class="fas fa-download"></i> Export Excel File
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function openExportModal(surveyId, branch, dateFrom, dateTo) {
    if (surveyId) {
        document.getElementById('modal_survey_select').value = surveyId;
    }
    if (branch !== undefined && branch !== '') {
        document.getElementById('modal_branch_select').value = branch;
    }
    if (dateFrom !== undefined && dateFrom !== '') {
        document.getElementById('modal_from_date').value = dateFrom;
    }
    if (dateTo !== undefined && dateTo !== '') {
        document.getElementById('modal_to_date').value = dateTo;
    }
    document.getElementById('exportSurveyModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeExportModal() {
    document.getElementById('exportSurveyModal').style.display = 'none';
    document.body.style.overflow = '';
}
function viewAnalysisFromModal() {
    const sel    = document.getElementById('modal_survey_select').value;
    const fromDt = document.getElementById('modal_from_date').value;
    const toDt   = document.getElementById('modal_to_date').value;
    const branch = document.getElementById('modal_branch_select').value;
    if (!sel) {
        alert('Please select a survey first to view its data.');
        return;
    }
    let url = 'report_surveys.php?survey=' + sel;
    if (fromDt) url += '&from=' + encodeURIComponent(fromDt);
    if (toDt)   url += '&to=' + encodeURIComponent(toDt);
    if (branch) url += '&branch=' + encodeURIComponent(branch);
    window.location.href = url;
}
document.getElementById('exportSurveyModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeExportModal();
});

const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
const gridColor = isDark ? 'rgba(255,255,255,0.08)' : '#f1f5f9';
const tickColor = isDark ? '#94a3b8' : '#64748b';
const labelColor = isDark ? '#e2e8f0' : '#1e293b';
Chart.defaults.font.family = "'Inter',sans-serif";
Chart.defaults.color = tickColor;

new Chart(document.getElementById('surveysChart'), {
    type:'bar',
    data:{
        labels:<?php echo json_encode($sv_lbl); ?>,
        datasets:[{label:'Responses',data:<?php echo json_encode($sv_cnt); ?>,backgroundColor:'rgba(14,131,181,0.82)',borderColor:'#0e83b5',borderRadius:8,barThickness:36}]
    },
    options:{indexAxis:'y',maintainAspectRatio:false,scales:{x:{beginAtZero:true,grid:{color:gridColor},ticks:{stepSize:1,color:tickColor}},y:{grid:{display:false},ticks:{font:{weight:'600',size:11},color:labelColor}}},plugins:{legend:{display:false}}}
});

new Chart(document.getElementById('trendChart'), {
    type:'line',
    data:{
        labels:<?php echo json_encode($tlbl); ?>,
        datasets:[{label:'Responses',data:<?php echo json_encode($tdat); ?>,borderColor:'#8b5cf6',backgroundColor:'rgba(139,92,246,0.12)',borderWidth:2.5,pointBackgroundColor:'#8b5cf6',pointRadius:5,tension:0.4,fill:true}]
    },
    options:{maintainAspectRatio:false,scales:{x:{grid:{color:gridColor},ticks:{color:tickColor}},y:{beginAtZero:true,grid:{color:gridColor},ticks:{stepSize:1,color:tickColor}}},plugins:{legend:{display:false}}}
});

window.addEventListener('beforeprint',function(){
    Chart.instances.forEach(c=>c.resize());
    document.querySelectorAll('[style*="overflow"]').forEach(el=>{el.dataset.ovBak=el.style.overflow;el.style.overflow='visible';el.style.maxHeight='none';el.style.height='auto';});
});
window.addEventListener('afterprint',function(){
    document.querySelectorAll('[data-ov-bak]').forEach(el=>{el.style.overflow=el.dataset.ovBak||'';el.style.maxHeight='';el.style.height='';delete el.dataset.ovBak;});
});
</script>
<?php include '../includes/admin_footer.php'; ?>
