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
    <h1>FCPAMS â€” Membership Satisfaction Survey Report: <?php echo date('Y'); ?></h1>
    <p>Generated: <?php echo $generated; ?> &nbsp;Â·&nbsp; Prepared by: <?php echo htmlspecialchars($_SESSION['name']); ?></p>
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
                <p style="color:rgba(255,255,255,.65);margin:.2rem 0 0;font-size:.9rem;">Citizen engagement &amp; satisfaction survey insights â€” <?php echo date('Y'); ?></p>
            </div>
        </div>
    </div>
    <button onclick="window.print()" class="btn no-print" style="padding:.75rem 1.6rem;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:.75rem;font-weight:700;font-size:.88rem;cursor:pointer;display:flex;align-items:center;gap:.55rem;backdrop-filter:blur(6px);transition:all .2s;white-space:nowrap;" onmouseover="this.style.background='rgba(255,255,255,.25)'" onmouseout="this.style.background='rgba(255,255,255,.15)'">
        <i class="fas fa-print"></i> Print / Export PDF
    </button>
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

<!-- Section 1: Executive Summary -->
<div class="report-section">
    <div class="section-hd"><i class="fas fa-file-alt" style="color:var(--accent);"></i> 1.0 Executive Summary</div>
    <div class="section-body">
        <?php if ($survey_data): ?>
        <div style="margin-bottom:1rem;padding:.75rem 1rem;background:var(--surface2);border-radius:var(--radius-sm);border-left:4px solid var(--accent);">
            <div style="font-weight:800;font-size:1rem;color:var(--text);margin-bottom:.25rem;"><?php echo htmlspecialchars($survey_data['title']); ?></div>
            <?php if ($survey_data['description']): ?><div style="font-size:.85rem;color:var(--text3);"><?php echo htmlspecialchars($survey_data['description']); ?></div><?php endif; ?>
        </div>
        <?php endif; ?>
        <p style="font-size:.88rem;color:var(--text2);margin-bottom:1rem;">
            This report presents the findings of the <?php echo date('Y'); ?> FCPAMS Membership Satisfaction Survey, assessing citizen satisfaction across key areas of cooperative services. The Customer Satisfaction (CSAT) score model is used to provide a quantitative measure of performance.
        </p>
        <div class="exec-grid">
            <div class="exec-item">
                <div class="exec-label">Generated</div>
                <div class="exec-value" style="font-size:.9rem;"><?php echo $generated; ?></div>
            </div>
            <div class="exec-item">
                <div class="exec-label">Prepared by</div>
                <div class="exec-value" style="font-size:.9rem;"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
            </div>
            <div class="exec-item">
                <div class="exec-label">Overall CSAT Score</div>
                <div class="exec-value" style="color:<?php echo $tier['color']; ?>;"><?php echo $csat_overall !== null ? $csat_overall.'%' : 'No rating data yet'; ?></div>
            </div>
            <div class="exec-item">
                <div class="exec-label">Performance Tier</div>
                <div class="exec-value"><span class="tier-pill" style="background:<?php echo $tier['bg']; ?>;color:<?php echo $tier['color']; ?>;"><?php echo $tier['label']; ?></span></div>
            </div>
            <?php if ($f_survey): ?>
            <div class="exec-item">
                <div class="exec-label">Responses (Filtered)</div>
                <div class="exec-value"><?php echo $response_count; ?></div>
            </div>
            <?php else: ?>
            <div class="exec-item">
                <div class="exec-label">Total Responses</div>
                <div class="exec-value"><?php echo $total_responses; ?></div>
            </div>
            <?php endif; ?>
            <div class="exec-item">
                <div class="exec-label">Active Surveys</div>
                <div class="exec-value"><?php echo $active_surveys; ?> of <?php echo $total_surveys; ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Section 2: Methodology -->
<div class="report-section">
    <div class="section-hd"><i class="fas fa-flask" style="color:var(--blue);"></i> 2.0 Methodology &amp; CSAT Interpretation</div>
    <div class="section-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
            <div>
                <p style="font-size:.88rem;color:var(--text2);margin-bottom:.75rem;">Responses are collected from citizen members via the FCPAMS portal using a 5-point Likert scale (1 = Strongly Disagree, 5 = Strongly Agree). The CSAT score is computed using the following formula:</p>
                <div style="background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:1rem;text-align:center;font-size:.85rem;color:var(--text3);">
                    <div style="font-size:1rem;font-weight:700;color:var(--text);margin-bottom:.5rem;">CSAT Formula</div>
                    <div style="border-top:2px solid var(--text);padding-top:.4rem;font-size:.88rem;font-weight:600;color:var(--text2);">Agree (4) + Strongly Agree (5) responses</div>
                    <div style="height:2px;background:var(--text);margin:.3rem auto;width:80%;"></div>
                    <div style="font-size:.88rem;font-weight:600;color:var(--text2);">Total Number of Responses</div>
                    <div style="margin-top:.4rem;font-weight:800;color:var(--accent);">Ã— 100</div>
                </div>
            </div>
            <div>
                <div style="font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--text4);margin-bottom:.6rem;">CSAT Score Tiers</div>
                <div style="display:flex;flex-direction:column;gap:.5rem;">
                    <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem .85rem;background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);border-radius:var(--radius-sm);border-left:4px solid #10b981;">
                        <div style="font-weight:800;color:#10b981;min-width:5rem;">85% â€“ 100%</div>
                        <div style="font-size:.82rem;color:var(--text2);"><strong style="color:var(--text);">Excellent</strong> â€” Exceeding expectations; high member satisfaction.</div>
                    </div>
                    <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem .85rem;background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.25);border-radius:var(--radius-sm);border-left:4px solid #3b82f6;">
                        <div style="font-weight:800;color:#3b82f6;min-width:5rem;">70% â€“ 84%</div>
                        <div style="font-size:.82rem;color:var(--text2);"><strong style="color:var(--text);">Good</strong> â€” Room for improvement; satisfaction is acceptable.</div>
                    </div>
                    <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem .85rem;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:var(--radius-sm);border-left:4px solid #ef4444;">
                        <div style="font-weight:800;color:#ef4444;min-width:5rem;">Below 70%</div>
                        <div style="font-size:.82rem;color:var(--text2);"><strong style="color:var(--text);">Needs Improvement</strong> â€” Immediate corrective action needed.</div>
                    </div>
                </div>
            </div>
        </div>
        <div style="font-size:.78rem;color:var(--text4);padding:.5rem .75rem;background:var(--surface2);border-radius:var(--radius-sm);">
            <i class="fas fa-info-circle" style="color:var(--accent);"></i> Rating questions use a 5-point scale. Responses of 4 ("Agree") and 5 ("Strongly Agree") are counted as satisfied. Choice and text questions are analyzed separately for qualitative insights.
        </div>
    </div>
</div>
<!-- Section 3: Key Findings -->
<div class="report-section">
    <div class="section-hd"><i class="fas fa-search" style="color:var(--amber);"></i> 3.0 Key Findings &amp; Analysis</div>
    <div class="section-body">
        <?php if (empty($question_stats)): ?>
        <div style="text-align:center;padding:2rem;color:var(--text4);">
            <i class="fas fa-filter" style="font-size:2rem;margin-bottom:.75rem;display:block;"></i>
            <div style="font-weight:700;margin-bottom:.5rem;">Select a Survey to View Question-Level Analysis</div>
            <div style="font-size:.85rem;">Use the filter above to select a specific survey and see CSAT scores per question.</div>
        </div>
        <?php else: ?>
        <?php
        $rating_qs = array_filter($question_stats, fn($s)=>$s['question']['type']==='RATING');
        $other_qs  = array_filter($question_stats, fn($s)=>$s['question']['type']!=='RATING');
        $qi = 1;
        ?>
        <?php if ($rating_qs): ?>
        <div style="margin-bottom:1.5rem;">
            <div style="font-size:.8rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--text4);margin-bottom:.75rem;display:flex;align-items:center;gap:.5rem;"><i class="fas fa-star" style="color:var(--amber);"></i> Rating Questions (CSAT Analysis)</div>
            <?php foreach ($rating_qs as $s):
                $t = csatTier($s['csat']);
                $q = $s['question'];
                $dist = $s['dist'];
                $totalR = $s['total'];
            ?>
            <div class="q-card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
                    <div class="q-title">Q<?php echo $qi++; ?>. <?php echo htmlspecialchars($q['text']); ?></div>
                    <div style="display:flex;align-items:center;gap:.5rem;flex-shrink:0;">
                        <?php if ($s['csat'] !== null): ?>
                        <span style="font-size:1.4rem;font-weight:900;color:<?php echo $t['color']; ?>;"><?php echo $s['csat']; ?>%</span>
                        <?php endif; ?>
                        <span class="tier-pill" style="background:<?php echo $t['bg']; ?>;color:<?php echo $t['color']; ?>;"><?php echo $t['label']; ?></span>
                    </div>
                </div>
                <?php if ($totalR > 0): ?>
                <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:.4rem;margin-bottom:.75rem;">
                    <?php
                    $labels = [1=>'Strongly Disagree',2=>'Disagree',3=>'Neutral',4=>'Agree',5=>'Strongly Agree'];
                    $colors = [1=>'#ef4444',2=>'#f97316',3=>'#f59e0b',4=>'#3b82f6',5=>'#10b981'];
                    foreach ($dist as $k=>$v):
                        $pct = $totalR>0 ? round($v/$totalR*100) : 0;
                    ?>
                    <div style="text-align:center;">
                        <div style="font-size:.65rem;color:var(--text4);margin-bottom:.2rem;"><?php echo $labels[$k]; ?></div>
                        <div style="background:var(--border);border-radius:2rem;height:6px;overflow:hidden;margin-bottom:.2rem;">
                            <div style="height:100%;background:<?php echo $colors[$k]; ?>;border-radius:2rem;width:<?php echo $pct; ?>%;transition:width .6s;"></div>
                        </div>
                        <div style="font-size:.72rem;font-weight:700;color:var(--text2);"><?php echo $v; ?> <span style="color:var(--text4);">(<?php echo $pct; ?>%)</span></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="font-size:.78rem;color:var(--text3);"><i class="fas fa-users" style="color:var(--accent);"></i> <?php echo $totalR; ?> total rated &nbsp;Â·&nbsp; <?php echo $s['agree']; ?> satisfied (4â€“5) &nbsp;Â·&nbsp; <?php echo $totalR - $s['agree']; ?> below threshold</div>
                <?php else: ?>
                <div style="font-size:.82rem;color:var(--text4);">No responses yet for this question.</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($other_qs): ?>
        <div>
            <div style="font-size:.8rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--text4);margin-bottom:.75rem;display:flex;align-items:center;gap:.5rem;"><i class="fas fa-list" style="color:var(--blue);"></i> Choice &amp; Text Questions</div>
            <?php foreach ($other_qs as $s):
                $q = $s['question'];
            ?>
            <div class="q-card">
                <div class="q-title">Q<?php echo $qi++; ?>. <?php echo htmlspecialchars($q['text']); ?>
                    <span style="font-size:.7rem;font-weight:600;color:var(--text4);margin-left:.5rem;">(<?php echo $q['type']; ?>)</span>
                </div>
                <?php if (!empty($s['dist'])): ?>
                <?php $maxVal = max($s['dist']); foreach ($s['dist'] as $opt=>$cnt):
                    $pct = $maxVal>0 ? round($cnt/$maxVal*100) : 0;
                    $absPct = $response_count > 0 ? round($cnt/$response_count*100) : round($cnt/max(array_sum($s['dist']),1)*100);
                ?>
                <div class="q-bar-wrap">
                    <div class="q-bar-label"><span><?php echo htmlspecialchars($opt); ?></span><span><?php echo $cnt; ?> (<?php echo $absPct; ?>%)</span></div>
                    <div class="q-bar-track"><div class="q-bar" style="width:<?php echo $pct; ?>%;background:linear-gradient(90deg,var(--accent),var(--blue));"></div></div>
                </div>
                <?php endforeach; ?>
                <?php elseif (!empty($s['texts'])): ?>
                <div style="display:flex;flex-direction:column;gap:.4rem;max-height:120px;overflow-y:auto;">
                    <?php foreach (array_slice($s['texts'],0,5) as $txt): ?>
                    <div style="padding:.4rem .7rem;background:var(--surface);border:1px solid var(--border);border-radius:.4rem;font-size:.8rem;color:var(--text2);"><?php echo htmlspecialchars($txt); ?></div>
                    <?php endforeach; ?>
                    <?php if (count($s['texts'])>5): ?><div style="font-size:.75rem;color:var(--text4);">+<?php echo count($s['texts'])-5; ?> more responses...</div><?php endif; ?>
                </div>
                <?php else: ?>
                <div style="font-size:.82rem;color:var(--text4);">No responses yet.</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Section 4: Recommendations -->
<div class="report-section">
    <div class="section-hd"><i class="fas fa-lightbulb" style="color:var(--amber);"></i> 4.0 Recommendations</div>
    <div class="section-body">
        <?php
        $recs = [];
        if ($csat_overall !== null) {
            if ($csat_overall < 70) {
                $recs[] = ['title'=>'Immediate Service Review','text'=>'Overall CSAT is below 70%. Conduct an immediate review of service delivery processes and identify bottleneck areas for urgent corrective action.'];
                $recs[] = ['title'=>'Member Feedback Campaign','text'=>'Launch a targeted feedback campaign to understand specific pain points and gather actionable suggestions from dissatisfied members.'];
            } elseif ($csat_overall < 85) {
                $recs[] = ['title'=>'Enhance Service Accessibility','text'=>'Improve digital service options, streamline member processes, and extend operating hours where feasible to increase member convenience.'];
                $recs[] = ['title'=>'Improve Member Engagement','text'=>'Create dedicated feedback channels (online forums, town halls, suggestion boxes) and acknowledge changes made based on member input.'];
            } else {
                $recs[] = ['title'=>'Sustain High Performance','text'=>'Maintain current service excellence by regular staff training and continuous process improvement to keep CSAT above the 85% threshold.'];
                $recs[] = ['title'=>'Leverage Positive Advocacy','text'=>'Encourage satisfied members to participate in referral programs and share their positive experience to increase membership.'];
            }
            // Check for low-scoring questions
            $low_qs = array_filter($question_stats, fn($s)=>$s['csat']!==null && $s['csat']<70);
            if (!empty($low_qs)) {
                $recs[] = ['title'=>'Address Low-Scoring Areas','text'=>''.count($low_qs).' question(s) scored below 70% CSAT. Review these specific areas with management and implement targeted improvement programs.'];
            }
        }
        $recs[] = ['title'=>'Address Leadership Perception','text'=>'Implement a transparent communication strategy including regular Q&amp;A sessions and a "Meet the Leadership" series to improve governance visibility.'];
        $recs[] = ['title'=>'Regular Survey Cadence','text'=>'Conduct satisfaction surveys quarterly to track trends over time and measure the impact of improvement initiatives.'];
        ?>
        <div>
            <?php foreach ($recs as $i=>$rec): ?>
            <div class="rec-item">
                <div class="rec-num"><?php echo $i+1; ?></div>
                <div class="rec-text"><strong><?php echo $rec['title']; ?></strong><?php echo $rec['text']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Section 5: Conclusion -->
<div class="report-section">
    <div class="section-hd"><i class="fas fa-check-circle" style="color:var(--green);"></i> 5.0 Conclusion</div>
    <div class="section-body">
        <p style="font-size:.88rem;color:var(--text2);margin-bottom:.75rem;">
            The FCPAMS Membership Satisfaction Survey provides a vital window into the member experience. 
            <?php if ($csat_overall !== null): ?>
            The current overall CSAT score of <strong style="color:<?php echo $tier['color']; ?>;"><?php echo $csat_overall; ?>%</strong> indicates a <strong><?php echo strtolower($tier['label']); ?></strong> level of satisfaction among respondents.
            <?php else: ?>
            Insufficient rating data is available to compute a CSAT score at this time.
            <?php endif; ?>
        </p>
        <p style="font-size:.88rem;color:var(--text2);margin:0;">
            Prioritizing service accessibility, member engagement, and leadership transparency will be key drivers of long-term cooperative success. It is recommended that this report be shared with department heads and reviewed during the next board or management meeting.
        </p>
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
    <div class="tbl-hd"><span><i class="fas fa-clipboard-list"></i> All Surveys â€” Summary</span></div>
    <table class="rtbl">
        <thead><tr><th>#</th><th>Survey Title</th><th>Description</th><th>Status</th><th>Responses</th><th>Created</th></tr></thead>
        <tbody>
        <?php while ($s=$surveys_detail->fetch_assoc()): ?>
        <tr>
            <td style="color:var(--text4);"><?php echo $s['id']; ?></td>
            <td style="font-weight:600;color:var(--text);"><?php echo htmlspecialchars($s['title']); ?></td>
            <td style="font-size:.78rem;color:var(--text3);max-width:200px;"><?php echo htmlspecialchars(mb_substr($s['description']??'',0,60)).(mb_strlen($s['description']??'')>60?'...':''); ?></td>
            <td><?php if($s['is_active']): ?><span class="badge" style="background:#dcfce7;color:#16a34a;">Active</span><?php else: ?><span class="badge" style="background:var(--surface2);color:var(--text4);">Inactive</span><?php endif; ?></td>
            <td style="font-weight:800;font-size:1.05rem;color:var(--accent);"><?php echo $s['responses']; ?></td>
            <td style="font-size:.8rem;color:var(--text3);"><?php echo date('M d, Y',strtotime($s['created_at'])); ?></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Response Log -->
<div class="tbl-wrap">
    <div class="tbl-hd">
        <span><i class="fas fa-users"></i> Response Log â€” <?php echo $filtered_count; ?> record<?php echo $filtered_count!=1?'s':''; ?></span>
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

</div><!-- end fade-in -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
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
