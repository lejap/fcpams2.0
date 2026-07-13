<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Get current staff's branch info for filtering
$_staff_branch_id = $_SESSION['branch_id'] ?? null;
$_staff_is_ho     = $_SESSION['is_ho'] ?? 0;
$_staff_role      = $_SESSION['role'] ?? 'STAFF';

// Resolve branch name
$_staff_branch_name = '';
if ($_staff_branch_id) {
    $_br = $conn->query("SELECT name FROM branches WHERE id=$_staff_branch_id");
    if ($_br && $_r = $_br->fetch_assoc()) {
        $_staff_branch_name = $_r['name'];
    }
}

// Notification counts
$open_submissions = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE status='OPEN'")->fetch_assoc()['c'];

// Complaints: branch-filtered
if ($_staff_role === 'ADMIN' || $_staff_is_ho) {
    $open_complaints = $conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='OPEN' AND complexity IS NOT NULL")->fetch_assoc()['c'];
    $closed_total    = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE status IN ('RESOLVED','CLOSED')")->fetch_assoc()['c']
                     + $conn->query("SELECT COUNT(*) as c FROM complaints WHERE status IN ('RESOLVED','CLOSED') AND complexity IS NOT NULL")->fetch_assoc()['c'];
} else {
    $_safe_branch = $conn->real_escape_string($_staff_branch_name);
    $open_complaints = $conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='OPEN' AND complexity IS NOT NULL AND user_branch = '$_safe_branch'")->fetch_assoc()['c'];
    $closed_total    = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE status IN ('RESOLVED','CLOSED')")->fetch_assoc()['c']
                     + $conn->query("SELECT COUNT(*) as c FROM complaints WHERE status IN ('RESOLVED','CLOSED') AND complexity IS NOT NULL AND user_branch = '$_safe_branch'")->fetch_assoc()['c'];
}
?>

<!-- Staff Sidebar -->
<nav class="admin-sidebar" id="admin-sidebar">

    <a href="<?php echo BASE_URL; ?>staff/dashboard.php" class="admin-nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
        <i class="fas fa-tachometer-alt"></i> Dashboard
        <?php if ($open_submissions > 0 || $open_complaints > 0): ?>
        <span style="margin-left:auto;background:#ef4444;color:white;border-radius:9px;min-width:18px;height:18px;font-size:0.65rem;font-weight:800;display:inline-flex;align-items:center;justify-content:center;padding:0 4px;"><?php echo $open_submissions + $open_complaints; ?></span>
        <?php endif; ?>
    </a>

    <a href="<?php echo BASE_URL; ?>staff/submissions.php" class="admin-nav-link <?php echo $current_page === 'submissions.php' ? 'active' : ''; ?>">
        <i class="fas fa-layer-group"></i> Submissions
        <?php if ($open_submissions > 0): ?>
        <span style="margin-left:auto;background:#ef4444;color:white;border-radius:9px;min-width:18px;height:18px;font-size:0.65rem;font-weight:800;display:inline-flex;align-items:center;justify-content:center;padding:0 4px;"><?php echo $open_submissions; ?></span>
        <?php endif; ?>
    </a>

    <a href="<?php echo BASE_URL; ?>staff/complaints.php" class="admin-nav-link <?php echo $current_page === 'complaints.php' ? 'active' : ''; ?>" style="color:rgba(255,180,180,0.95) !important;">
        <i class="fas fa-exclamation-triangle"></i> Complaints
        <?php if ($open_complaints > 0): ?>
        <span style="margin-left:auto;background:#ef4444;color:white;border-radius:9px;min-width:18px;height:18px;font-size:0.65rem;font-weight:800;display:inline-flex;align-items:center;justify-content:center;padding:0 4px;"><?php echo $open_complaints; ?></span>
        <?php endif; ?>
    </a>

    <a href="<?php echo BASE_URL; ?>staff/closed.php" class="admin-nav-link <?php echo $current_page === 'closed.php' ? 'active' : ''; ?>" style="color:rgba(200,180,255,0.95) !important;">
        <i class="fas fa-archive"></i> Closed Records
        <?php if ($closed_total > 0): ?>
        <span style="margin-left:auto;background:#8b5cf6;color:white;border-radius:9px;min-width:18px;height:18px;font-size:0.65rem;font-weight:800;display:inline-flex;align-items:center;justify-content:center;padding:0 4px;"><?php echo $closed_total; ?></span>
        <?php endif; ?>
    </a>

</nav>

<!-- Staff Content Area -->
<main class="admin-content" style="position: relative;">
    <button onclick="toggleAdminNav()" style="position: absolute; top: 1.8rem; left: 0.2rem; background: transparent; border: none; color: #64748b; font-size: 1.3rem; cursor: pointer; z-index: 10; padding: 0.2rem 0.5rem; transition: color 0.2s;" onmouseover="this.style.color='#0e83b5'" onmouseout="this.style.color='#64748b'" title="Toggle Sidebar">
        <i class="fas fa-bars"></i>
    </button>
