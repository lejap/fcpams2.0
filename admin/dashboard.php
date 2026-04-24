<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('ADMIN');

// Handle Mark as Spam from Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_spam'])) {
    $tid = (int)$_POST['mark_spam'];
    $conn->query("UPDATE tickets SET status='SPAM' WHERE id=$tid");
    header("Location: dashboard.php");
    exit();
}

// Fetch counts (tickets = submissions)
$total_tickets  = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE status != 'SPAM'")->fetch_assoc()['c'];
$open_tickets   = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE status='OPEN'")->fetch_assoc()['c'];
$resolved_tickets = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE status='RESOLVED'")->fetch_assoc()['c'];
$active_surveys = $conn->query("SELECT COUNT(*) as c FROM surveys WHERE is_active=1")->fetch_assoc()['c'];

// Fetch recent submissions with filter support
$type_filter   = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$branch_filter = isset($_GET['branch']) ? sanitize($_GET['branch']) : '';
$date_from     = isset($_GET['dateFrom']) ? sanitize($_GET['dateFrom']) : '';
$date_to       = isset($_GET['dateTo']) ? sanitize($_GET['dateTo']) : '';

$where = "WHERE status != 'SPAM'";
if ($type_filter)   $where .= " AND type = '$type_filter'";
if ($branch_filter) $where .= " AND user_branch = '$branch_filter'";
if ($date_from)     $where .= " AND DATE(created_at) >= '$date_from'";
if ($date_to)       $where .= " AND DATE(created_at) <= '$date_to'";

$submissions = $conn->query("SELECT * FROM tickets $where ORDER BY created_at DESC");
$branches    = $conn->query("SELECT * FROM branches ORDER BY name");

$page_title = "Admin Dashboard";
include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<div class="fade-in">
    <div style="margin-bottom: 1.5rem;">
        <h1 style="font-size: 1.75rem; color: #0e83b5; margin-bottom: 0.25rem;">Dashboard</h1>
    </div>

    <!-- Stat Cards -->
    <div class="stat-card-row">
        <div class="stat-card">
            <div class="stat-card-title">Total Submissions</div>
            <div class="stat-card-value" style="color:#3b82f6;"><?php echo $total_tickets; ?></div>
            <i class="fas fa-layer-group" style="position:absolute;right:1.25rem;top:1.25rem;font-size:2.2rem;opacity:0.1;color:#3b82f6;"></i>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">Open Submissions</div>
            <div class="stat-card-value" style="color:#ef4444;"><?php echo $open_tickets; ?></div>
            <i class="fas fa-exclamation-circle" style="position:absolute;right:1.25rem;top:1.25rem;font-size:2.2rem;opacity:0.1;color:#ef4444;"></i>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">Resolved Submissions</div>
            <div class="stat-card-value" style="color:#10b981;"><?php echo $resolved_tickets; ?></div>
            <i class="fas fa-check-circle" style="position:absolute;right:1.25rem;top:1.25rem;font-size:2.2rem;opacity:0.1;color:#10b981;"></i>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">Active Surveys</div>
            <div class="stat-card-value" style="color:#8b5cf6;"><?php echo $active_surveys; ?></div>
            <i class="fas fa-poll" style="position:absolute;right:1.25rem;top:1.25rem;font-size:2.2rem;opacity:0.1;color:#8b5cf6;"></i>
        </div>
    </div>

    <!-- Filter Row -->
    <form class="admin-filter-row" method="GET" action="dashboard.php">
        <div class="admin-filter-group">
            <label class="admin-filter-label">Type</label>
            <select name="type" class="admin-filter-input">
                <option value="">All Types</option>
                <option value="INQUIRY" <?php echo $type_filter==='INQUIRY'?'selected':''; ?>>Inquiry</option>
                <option value="SUGGESTION" <?php echo $type_filter==='SUGGESTION'?'selected':''; ?>>Suggestion</option>
                <option value="REQUEST" <?php echo $type_filter==='REQUEST'?'selected':''; ?>>Request</option>
            </select>
        </div>
        <div class="admin-filter-group">
            <label class="admin-filter-label">Branch</label>
            <select name="branch" class="admin-filter-input">
                <option value="">All Branches</option>
                <?php while($b=$branches->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($b['name']); ?>" <?php echo $branch_filter===$b['name']?'selected':''; ?>>
                        <?php echo htmlspecialchars($b['name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="admin-filter-group">
            <label class="admin-filter-label">Date From</label>
            <input type="date" name="dateFrom" class="admin-filter-input" value="<?php echo htmlspecialchars($date_from); ?>">
        </div>
        <div class="admin-filter-group">
            <label class="admin-filter-label">Date To</label>
            <input type="date" name="dateTo" class="admin-filter-input" value="<?php echo htmlspecialchars($date_to); ?>">
        </div>
        <button type="submit" class="btn" style="background:#64748b;color:white;border:none;padding:0.5rem 2rem;">Filter</button>
        <a href="dashboard.php" class="btn btn-outline" style="padding:0.5rem 1rem;">Clear</a>
    </form>

    <!-- Submissions Table -->
    <div class="admin-table-wrapper">
        <div class="admin-table-header">
            <i class="fas fa-layer-group"></i> Recent Submissions
        </div>
        <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Message / Subject</th>
                    <th>Requester</th>
                    <th>Branch</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Resolved</th>
                    <th>Resolved By</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($submissions->num_rows > 0): ?>
                    <?php while ($sub = $submissions->fetch_assoc()): ?>
                    <tr>
                        <td style="font-weight:bold;color:#64748b;">#<?php echo $sub['id']; ?></td>
                        <td style="font-weight:600;"><?php echo htmlspecialchars(mb_substr($sub['message'] ?? '', 0, 50)) . (strlen($sub['message'] ?? '') > 50 ? '...' : ''); ?></td>
                        <td>
                            <div><?php echo htmlspecialchars($sub['user_name']); ?></div>
                            <div style="font-size:0.7rem;color:#64748b;"><?php echo htmlspecialchars($sub['user_phone']); ?></div>
                        </td>
                        <td><span style="background:#94a3b8;color:white;padding:0.1rem 0.4rem;border-radius:0.2rem;font-size:0.7rem;"><?php echo htmlspecialchars($sub['user_branch']); ?></span></td>
                        <td>
                            <?php
                            $type_color = $sub['type']==='INQUIRY'?'#ef4444':($sub['type']==='REQUEST'?'#8b5cf6':'#eab308');
                            ?>
                            <span class="badge" style="background:<?php echo $type_color; ?>;"><?php echo $sub['type']; ?></span>
                        </td>
                        <td>
                            <span class="badge" style="background:<?php echo $sub['status']==='OPEN'?'#ef4444':'#10b981'; ?>;"><?php echo $sub['status'] ?? 'OPEN'; ?></span>
                        </td>
                        <td style="font-size:0.8rem;"><?php echo date('M d, Y H:i', strtotime($sub['created_at'])); ?></td>
                        <td style="font-size:0.8rem;"><?php echo $sub['resolved_at'] ? date('M d, Y H:i', strtotime($sub['resolved_at'])) : '-'; ?></td>
                        <td>
                            <?php if ($sub['resolved_by_name']): ?>
                                <span style="background:#dbeafe;color:#1e40af;padding:0.15rem 0.5rem;border-radius:0.25rem;font-size:0.75rem;font-weight:600;"><?php echo htmlspecialchars($sub['resolved_by_name']); ?></span>
                            <?php else: ?>
                                <span style="color:#94a3b8;font-size:0.8rem;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="display: flex; gap: 0.3rem;">
                            <a href="submissions.php?view=<?php echo $sub['id']; ?>" class="btn btn-outline" style="padding:0.2rem 0.6rem;font-size:0.8rem;border-radius:2rem;">View</a>
                            <form method="POST" style="margin:0;" onsubmit="return confirm('Mark this submission as SPAM?');">
                                <input type="hidden" name="mark_spam" value="<?php echo $sub['id']; ?>">
                                <button type="submit" class="btn btn-outline" style="padding:0.2rem 0.6rem;font-size:0.8rem;border-radius:2rem;color:#ef4444;border-color:#ef4444;">Spam</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10" style="text-align:center;padding:2rem;color:#94a3b8;">No submissions found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php include '../includes/admin_footer.php'; ?>

