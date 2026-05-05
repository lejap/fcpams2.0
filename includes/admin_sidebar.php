<?php
$current_page = basename($_SERVER['PHP_SELF']);
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'ADMIN';

// Notification counts
$open_submissions = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE status='OPEN'")->fetch_assoc()['c'];
$open_complaints  = $conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='OPEN'")->fetch_assoc()['c'];
$closed_total     = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE status IN ('RESOLVED','CLOSED')")->fetch_assoc()['c']
                  + $conn->query("SELECT COUNT(*) as c FROM complaints WHERE status IN ('RESOLVED','CLOSED')")->fetch_assoc()['c'];
$pending_users    = $is_admin ? $conn->query("SELECT COUNT(*) as c FROM users WHERE is_approved=0")->fetch_assoc()['c'] : 0;
?>

<!-- Admin Sidebar -->
<nav class="admin-sidebar" id="admin-sidebar">

    <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="admin-nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
        <i class="fas fa-tachometer-alt"></i> Dashboard
        <?php if ($open_submissions > 0): ?>
        <span style="margin-left:auto;background:#ef4444;color:white;border-radius:9px;min-width:18px;height:18px;font-size:0.65rem;font-weight:800;display:inline-flex;align-items:center;justify-content:center;padding:0 4px;"><?php echo $open_submissions; ?></span>
        <?php endif; ?>
    </a>


    <a href="<?php echo BASE_URL; ?>admin/complaints.php" class="admin-nav-link <?php echo $current_page === 'complaints.php' ? 'active' : ''; ?>" style="color:rgba(255,180,180,0.95) !important;">
        <i class="fas fa-exclamation-triangle"></i> Complaints
        <?php if ($open_complaints > 0): ?>
        <span style="margin-left:auto;background:#ef4444;color:white;border-radius:9px;min-width:18px;height:18px;font-size:0.65rem;font-weight:800;display:inline-flex;align-items:center;justify-content:center;padding:0 4px;"><?php echo $open_complaints; ?></span>
        <?php endif; ?>
    </a>

    <a href="<?php echo BASE_URL; ?>admin/surveys.php" class="admin-nav-link <?php echo $current_page === 'surveys.php' ? 'active' : ''; ?>">
        <i class="fas fa-poll"></i> Surveys
    </a>

    <a href="<?php echo BASE_URL; ?>admin/spam.php" class="admin-nav-link <?php echo $current_page === 'spam.php' ? 'active' : ''; ?>" style="color:rgba(255,180,180,0.95) !important;">
        <i class="fas fa-ban"></i> Spam
    </a>

    <a href="<?php echo BASE_URL; ?>admin/closed.php" class="admin-nav-link <?php echo $current_page === 'closed.php' ? 'active' : ''; ?>" style="color:rgba(200,180,255,0.95) !important;">
        <i class="fas fa-archive"></i> Closed Records
        <?php if ($closed_total > 0): ?>
        <span style="margin-left:auto;background:#8b5cf6;color:white;border-radius:9px;min-width:18px;height:18px;font-size:0.65rem;font-weight:800;display:inline-flex;align-items:center;justify-content:center;padding:0 4px;"><?php echo $closed_total; ?></span>
        <?php endif; ?>
    </a>

    <?php if ($is_admin): ?>
    <div style="padding:0.75rem 1.25rem 0.25rem;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.1em;color:rgba(255,255,255,0.45);font-weight:700;">Admin Only</div>

    <a href="<?php echo BASE_URL; ?>admin/users.php" class="admin-nav-link <?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
        <i class="fas fa-users"></i> Manage Users
        <?php if ($pending_users > 0): ?>
        <span style="margin-left:auto;background:#ef4444;color:white;border-radius:9px;min-width:18px;height:18px;font-size:0.65rem;font-weight:800;display:inline-flex;align-items:center;justify-content:center;padding:0 4px;"><?php echo $pending_users; ?></span>
        <?php endif; ?>
    </a>

    <a href="<?php echo BASE_URL; ?>admin/branches.php" class="admin-nav-link <?php echo $current_page === 'branches.php' ? 'active' : ''; ?>">
        <i class="fas fa-code-branch"></i> Branches
    </a>

    <a href="<?php echo BASE_URL; ?>admin/dropdowns.php" class="admin-nav-link <?php echo $current_page === 'dropdowns.php' ? 'active' : ''; ?>">
        <i class="fas fa-list"></i> Categories
    </a>

    <?php
    $report_pages = ['report_submissions.php','report_complaints.php','report_surveys.php'];
    $on_report = in_array($current_page, $report_pages);
    ?>
    <!-- Reports submenu -->
    <div class="nav-group">
        <button onclick="toggleReportsMenu()" id="reports-toggle"
            style="width:100%;display:flex;align-items:center;gap:0.65rem;padding:0.6rem 1.25rem;background:<?php echo $on_report?'rgba(255,255,255,0.12)':'transparent';?>;border:none;color:<?php echo $on_report?'#fff':'rgba(255,255,255,0.75)';?>;font-size:0.88rem;font-weight:600;cursor:pointer;border-radius:0.5rem;text-align:left;transition:background 0.2s,color 0.2s;"
            onmouseover="this.style.background='rgba(255,255,255,0.12)';this.style.color='#fff'"
            onmouseout="if(!window._rptOpen){this.style.background='<?php echo $on_report?'rgba(255,255,255,0.12)':'transparent';?>';this.style.color='<?php echo $on_report?'#fff':'rgba(255,255,255,0.75)';?>';}">
            <i class="fas fa-chart-bar"></i>
            <span style="flex:1;">Reports</span>
            <i class="fas fa-chevron-down" id="reports-chevron" style="font-size:0.7rem;transition:transform 0.25s;<?php echo $on_report?'transform:rotate(180deg)':'';?>"></i>
        </button>
        <div id="reports-submenu" style="overflow:hidden;max-height:<?php echo $on_report?'200px':'0';?>;transition:max-height 0.3s ease;padding-left:0.75rem;">
            <a href="<?php echo BASE_URL; ?>admin/report_submissions.php"
               class="admin-nav-link <?php echo $current_page==='report_submissions.php'?'active':''; ?>"
               style="font-size:0.82rem;padding:0.45rem 1rem;">
                <i class="fas fa-layer-group"></i> Submissions
            </a>
            <a href="<?php echo BASE_URL; ?>admin/report_complaints.php"
               class="admin-nav-link <?php echo $current_page==='report_complaints.php'?'active':''; ?>"
               style="font-size:0.82rem;padding:0.45rem 1rem;color:rgba(255,180,180,0.95) !important;">
                <i class="fas fa-exclamation-triangle"></i> Complaints
            </a>
            <a href="<?php echo BASE_URL; ?>admin/report_surveys.php"
               class="admin-nav-link <?php echo $current_page==='report_surveys.php'?'active':''; ?>"
               style="font-size:0.82rem;padding:0.45rem 1rem;">
                <i class="fas fa-poll"></i> Surveys
            </a>
        </div>
    </div>
    <?php endif; ?>
<script>
window._rptOpen = <?php echo $on_report?'true':'false'; ?>;
function toggleReportsMenu(){
    var m=document.getElementById('reports-submenu');
    var c=document.getElementById('reports-chevron');
    var b=document.getElementById('reports-toggle');
    window._rptOpen=!window._rptOpen;
    m.style.maxHeight=window._rptOpen?'200px':'0';
    c.style.transform=window._rptOpen?'rotate(180deg)':'rotate(0deg)';
    b.style.background=window._rptOpen?'rgba(255,255,255,0.12)':'transparent';
    b.style.color=window._rptOpen?'#fff':'rgba(255,255,255,0.75)';
}
</script>

</nav>

<!-- Admin Content Area -->
<main class="admin-content" style="position: relative;">
    <button onclick="toggleAdminNav()" style="position: absolute; top: 1.8rem; left: 0.2rem; background: transparent; border: none; color: #64748b; font-size: 1.3rem; cursor: pointer; z-index: 10; padding: 0.2rem 0.5rem; transition: color 0.2s;" onmouseover="this.style.color='#0e83b5'" onmouseout="this.style.color='#64748b'" title="Toggle Sidebar">
        <i class="fas fa-bars"></i>
    </button>
