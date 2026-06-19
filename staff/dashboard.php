<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('STAFF');

// Stat counts — OPEN only
$open_tickets    = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE status='OPEN'")->fetch_assoc()['c'];
$open_complaints = $conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='OPEN' AND complexity IS NOT NULL")->fetch_assoc()['c'];
$closed_total    = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE status IN ('RESOLVED','CLOSED')")->fetch_assoc()['c']
                 + $conn->query("SELECT COUNT(*) as c FROM complaints WHERE status IN ('RESOLVED','CLOSED') AND complexity IS NOT NULL")->fetch_assoc()['c'];

// Filters
$type_filter   = isset($_GET['type'])     ? sanitize($_GET['type'])     : '';
$branch_filter = isset($_GET['branch'])   ? sanitize($_GET['branch'])   : '';
$date_from     = isset($_GET['dateFrom']) ? sanitize($_GET['dateFrom']) : '';
$date_to       = isset($_GET['dateTo'])   ? sanitize($_GET['dateTo'])   : '';

// OPEN submissions only
$sub_where = "WHERE status = 'OPEN'";
if ($type_filter)   $sub_where .= " AND type = '$type_filter'";
if ($branch_filter) $sub_where .= " AND user_branch = '$branch_filter'";
if ($date_from)     $sub_where .= " AND DATE(created_at) >= '$date_from'";
if ($date_to)       $sub_where .= " AND DATE(created_at) <= '$date_to'";

// OPEN complaints only (assessed by admin)
$cmp_where = "WHERE status = 'OPEN' AND complexity IS NOT NULL";
if ($date_from) $cmp_where .= " AND DATE(created_at) >= '$date_from'";
if ($date_to)   $cmp_where .= " AND DATE(created_at) <= '$date_to'";

$submissions = $conn->query("SELECT *, (SELECT label FROM dropdown_options WHERE id = tickets.option_id) as option_label FROM tickets $sub_where ORDER BY created_at ASC");
$complaints  = $conn->query("SELECT * FROM complaints $cmp_where ORDER BY created_at ASC");
$branches    = $conn->query("SELECT * FROM branches ORDER BY name");

$page_title = "Staff Dashboard";
include '../includes/staff_header.php';
include '../includes/staff_sidebar.php';
?>

<div class="fade-in">
    <div style="margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.75rem;color:#0e83b5;margin-bottom:0.25rem;">Staff Dashboard</h1>
            <p style="color:#64748b;font-size:0.88rem;">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong>. Items below require your attention.</p>
        </div>
        <a href="closed.php" style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.5rem 1.25rem;border-radius:0.75rem;background:rgba(139,92,246,0.08);border:1px solid rgba(139,92,246,0.25);color:#7c3aed;font-weight:600;font-size:0.88rem;text-decoration:none;">
            <i class="fas fa-archive"></i> View Closed Records
            <span style="background:#8b5cf6;color:white;border-radius:9px;min-width:18px;height:18px;font-size:0.65rem;font-weight:800;display:inline-flex;align-items:center;justify-content:center;padding:0 4px;"><?php echo $closed_total; ?></span>
        </a>
    </div>

    <!-- Stat Cards -->
    <div class="stat-card-row">
        <div class="stat-card">
            <div class="stat-card-title">Open Submissions</div>
            <div class="stat-card-value" style="color:#ef4444;"><?php echo $open_tickets; ?></div>
            <i class="fas fa-inbox" style="position:absolute;right:1.25rem;top:1.25rem;font-size:2.2rem;opacity:0.1;color:#ef4444;"></i>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">Open Complaints</div>
            <div class="stat-card-value" style="color:#eab308;"><?php echo $open_complaints; ?></div>
            <i class="fas fa-exclamation-triangle" style="position:absolute;right:1.25rem;top:1.25rem;font-size:2.2rem;opacity:0.1;color:#eab308;"></i>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">Closed Records</div>
            <div class="stat-card-value" style="color:#8b5cf6;"><?php echo $closed_total; ?></div>
            <i class="fas fa-archive" style="position:absolute;right:1.25rem;top:1.25rem;font-size:2.2rem;opacity:0.1;color:#8b5cf6;"></i>
        </div>
    </div>

    <!-- Filter Row -->
    <form class="admin-filter-row" method="GET" action="dashboard.php">
        <div class="admin-filter-group">
            <label class="admin-filter-label">Type</label>
            <select name="type" class="admin-filter-input">
                <option value="">All Types</option>
                <option value="INQUIRY"    <?php echo $type_filter==='INQUIRY'?'selected':''; ?>>Inquiry</option>
                <option value="SUGGESTION" <?php echo $type_filter==='SUGGESTION'?'selected':''; ?>>Suggestion</option>
                <option value="REQUEST"    <?php echo $type_filter==='REQUEST'?'selected':''; ?>>Request</option>
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

    <!-- Open Submissions Table -->
    <div class="admin-table-wrapper">
        <div class="admin-table-header" style="background:linear-gradient(90deg,#ef4444,#f97316);">
            <i class="fas fa-inbox"></i> Open Submissions
            <span style="margin-left:auto;background:rgba(255,255,255,0.25);border-radius:9px;padding:0.1rem 0.55rem;font-size:0.75rem;"><?php echo $open_tickets; ?></span>
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
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($submissions->num_rows > 0): ?>
                    <?php while ($sub = $submissions->fetch_assoc()): ?>
                    <tr>
                        <td style="font-weight:bold;color:#64748b;">#<?php echo $sub['id']; ?></td>
                        <td style="font-weight:600;"><?php echo htmlspecialchars(mb_substr($sub['message'] ?? '', 0, 55)) . (strlen($sub['message'] ?? '') > 55 ? '...' : ''); ?></td>
                        <td>
                            <div><?php echo htmlspecialchars($sub['user_name']); ?></div>
                            <div style="font-size:0.7rem;color:#64748b;"><?php echo htmlspecialchars($sub['user_phone']); ?></div>
                        </td>
                        <td><span style="background:#94a3b8;color:white;padding:0.1rem 0.4rem;border-radius:0.2rem;font-size:0.7rem;"><?php echo htmlspecialchars($sub['user_branch']); ?></span></td>
                        <td>
                            <?php $tc = $sub['type']==='INQUIRY'?'#ef4444':($sub['type']==='REQUEST'?'#8b5cf6':'#eab308'); ?>
                            <span class="badge" style="background:<?php echo $tc; ?>;"><?php echo $sub['type']; ?></span>
                            <?php if (!empty($sub['option_label'])): ?>
                                <div style="font-size:0.72rem;color:#64748b;margin-top:0.25rem;font-weight:600;"><?php echo htmlspecialchars($sub['option_label']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.8rem;"><?php echo date('M d, Y H:i', strtotime($sub['created_at'])); ?></td>
                        <td>
                            <a href="submissions.php?view=<?php echo $sub['id']; ?>" class="btn btn-outline" style="padding:0.2rem 0.6rem;font-size:0.8rem;border-radius:2rem;">View</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center;padding:2.5rem;color:#94a3b8;">
                        <i class="fas fa-check-circle" style="font-size:1.8rem;display:block;margin-bottom:0.5rem;color:#10b981;"></i>
                        No open submissions — all clear!
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Open Complaints Table -->
    <div class="admin-table-wrapper" style="margin-top:1.5rem;">
        <div class="admin-table-header" style="background:linear-gradient(90deg,#dc2626,#b91c1c);">
            <i class="fas fa-exclamation-triangle"></i> Open Complaints
            <span style="margin-left:auto;background:rgba(255,255,255,0.25);border-radius:9px;padding:0.1rem 0.55rem;font-size:0.75rem;"><?php echo $open_complaints; ?></span>
        </div>
        <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Member</th>
                    <th>Branch</th>
                    <th>Complaint Type</th>
                    <th>Complexity</th>
                    <th>Date Filed</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($complaints->num_rows > 0): ?>
                <?php while ($c = $complaints->fetch_assoc()): ?>
                <tr>
                    <td style="font-weight:700;color:#ef4444;">CRN-<?php echo $c['id']; ?></td>
                    <td style="font-weight:600;">
                        <div><?php echo htmlspecialchars($c['user_name']); ?></div>
                        <div style="font-size:0.7rem;color:#64748b;"><?php echo htmlspecialchars($c['user_phone']); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($c['user_branch']); ?></td>
                    <td><span style="background:#fee2e2;color:#dc2626;padding:.2rem .5rem;border-radius:.25rem;font-size:.75rem;font-weight:bold;"><?php echo htmlspecialchars(mb_substr($c['complaint_details']??'',0,22)).(strlen($c['complaint_details']??'')>22?'...':''); ?></span></td>
                    <td>
                        <?php $cx=$c['complexity'];
                        $cxbg  = $cx==='COMPLEX'?'#fee2e2':($cx==='SIMPLE'?'#dcfce7':'#f1f5f9');
                        $cxcol = $cx==='COMPLEX'?'#dc2626':($cx==='SIMPLE'?'#16a34a':'#64748b'); ?>
                        <span style="background:<?php echo $cxbg; ?>;color:<?php echo $cxcol; ?>;padding:.2rem .5rem;border-radius:.25rem;font-size:.75rem;font-weight:bold;"><?php echo $cx ?? 'Unassessed'; ?></span>
                    </td>
                    <td style="font-size:0.8rem;"><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                    <td>
                        <a href="complaints.php?view=<?php echo $c['id']; ?>" class="btn btn-outline" style="padding:.2rem .6rem;font-size:.8rem;border-radius:2rem;color:#b91c1c;border-color:#b91c1c;">Review</a>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php else: ?>
                <tr><td colspan="7" style="text-align:center;padding:2.5rem;color:#94a3b8;">
                    <i class="fas fa-check-circle" style="font-size:1.8rem;display:block;margin-bottom:0.5rem;color:#10b981;"></i>
                    No open complaints — all clear!
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php include '../includes/admin_footer.php'; ?>
