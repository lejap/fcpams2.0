<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('STAFF');

// Handle signed document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_signed'])) {
    $cid = (int)$_POST['complaint_id'];
    if (isset($_FILES['signed_doc']) && $_FILES['signed_doc']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/signed_docs/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['signed_doc']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','pdf'])) {
            $fname = 'signed_complaint_' . $cid . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['signed_doc']['tmp_name'], $uploadDir . $fname)) {
                $path = 'uploads/signed_docs/' . $fname;
                $stmt = $conn->prepare("UPDATE complaints SET signed_doc_path=? WHERE id=?");
                $stmt->bind_param("si", $path, $cid);
                $stmt->execute();
            }
        }
    }
    header("Location: closed.php?tab=complaints");
    exit();
}

// Active tab
$tab = isset($_GET['tab']) && $_GET['tab'] === 'complaints' ? 'complaints' : 'submissions';

// Filters
$date_from     = isset($_GET['dateFrom']) ? sanitize($_GET['dateFrom']) : '';
$date_to       = isset($_GET['dateTo'])   ? sanitize($_GET['dateTo'])   : '';
$type_filter   = isset($_GET['type'])     ? sanitize($_GET['type'])     : '';
$branch_filter = isset($_GET['branch'])   ? sanitize($_GET['branch'])   : '';

// --- Submissions (RESOLVED + CLOSED) ---
$sub_where = "WHERE status IN ('RESOLVED','CLOSED')";
if ($type_filter)   $sub_where .= " AND type='$type_filter'";
if ($branch_filter) $sub_where .= " AND user_branch='$branch_filter'";
if ($date_from)     $sub_where .= " AND DATE(created_at) >= '$date_from'";
if ($date_to)       $sub_where .= " AND DATE(created_at) <= '$date_to'";

$submissions      = $conn->query("SELECT *, (SELECT label FROM dropdown_options WHERE id = tickets.option_id) as option_label FROM tickets $sub_where ORDER BY resolved_at DESC");
$total_sub_res    = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE status='RESOLVED'")->fetch_assoc()['c'];
$total_sub_closed = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE status='CLOSED'")->fetch_assoc()['c'];

// --- Complaints (RESOLVED + CLOSED, assessed only) ---
$cmp_where = "WHERE status IN ('RESOLVED','CLOSED') AND complexity IS NOT NULL";
if ($date_from) $cmp_where .= " AND DATE(created_at) >= '$date_from'";
if ($date_to)   $cmp_where .= " AND DATE(created_at) <= '$date_to'";

$complaints       = $conn->query("SELECT * FROM complaints $cmp_where ORDER BY resolved_at DESC");
$total_cmp_res    = $conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='RESOLVED' AND complexity IS NOT NULL")->fetch_assoc()['c'];
$total_cmp_closed = $conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='CLOSED' AND complexity IS NOT NULL")->fetch_assoc()['c'];

$branches = $conn->query("SELECT * FROM branches ORDER BY name");

$page_title = "Closed Records";
include '../includes/staff_header.php';
include '../includes/staff_sidebar.php';
?>

<style>
.tab-btn { padding:0.6rem 1.5rem;border-radius:0.6rem;font-weight:700;font-size:0.88rem;border:2px solid transparent;cursor:pointer;display:inline-flex;align-items:center;gap:0.5rem;text-decoration:none;transition:all 0.2s; }
.tab-btn.active-sub  { background:#dbeafe;color:#1e40af;border-color:#93c5fd; }
.tab-btn.active-cmp  { background:#fee2e2;color:#991b1b;border-color:#fca5a5; }
.tab-btn.inactive    { background:#f1f5f9;color:#64748b;border-color:#e2e8f0; }
.tab-btn.inactive:hover { background:#e2e8f0; }
.status-resolved { background:#fef9c3;color:#92400e;padding:.15rem .45rem;border-radius:.3rem;font-size:.72rem;font-weight:700; }
.status-closed   { background:#ede9fe;color:#5b21b6;padding:.15rem .45rem;border-radius:.3rem;font-size:.72rem;font-weight:700; }
</style>

<div class="fade-in">
    <!-- Header -->
    <div style="margin-bottom:1.5rem;">
        <a href="dashboard.php" style="color:#64748b;text-decoration:none;font-size:0.88rem;display:inline-flex;align-items:center;gap:0.4rem;margin-bottom:0.5rem;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1 style="font-size:1.75rem;color:#7c3aed;margin-bottom:0.25rem;"><i class="fas fa-archive"></i> Closed Records</h1>
        <p style="font-size:0.88rem;color:#64748b;">Submissions and complaints you have resolved — awaiting admin confirmation.</p>
    </div>

    <!-- Stat Cards -->
    <div class="stat-card-row">
        <div class="stat-card">
            <div class="stat-card-title">Resolved Submissions</div>
            <div class="stat-card-value" style="color:#eab308;"><?php echo $total_sub_res; ?></div>
            <i class="fas fa-clock" style="position:absolute;right:1.25rem;top:1.25rem;font-size:2.2rem;opacity:0.1;color:#eab308;"></i>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">Closed Submissions</div>
            <div class="stat-card-value" style="color:#8b5cf6;"><?php echo $total_sub_closed; ?></div>
            <i class="fas fa-archive" style="position:absolute;right:1.25rem;top:1.25rem;font-size:2.2rem;opacity:0.1;color:#8b5cf6;"></i>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">Resolved Complaints</div>
            <div class="stat-card-value" style="color:#f97316;"><?php echo $total_cmp_res; ?></div>
            <i class="fas fa-exclamation-circle" style="position:absolute;right:1.25rem;top:1.25rem;font-size:2.2rem;opacity:0.1;color:#f97316;"></i>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">Closed Complaints</div>
            <div class="stat-card-value" style="color:#10b981;"><?php echo $total_cmp_closed; ?></div>
            <i class="fas fa-check-double" style="position:absolute;right:1.25rem;top:1.25rem;font-size:2.2rem;opacity:0.1;color:#10b981;"></i>
        </div>
    </div>

    <!-- Tabs -->
    <div style="display:flex;gap:0.75rem;margin-bottom:1.25rem;flex-wrap:wrap;">
        <a href="closed.php?tab=submissions<?php echo ($date_from?'&dateFrom='.$date_from:'').($date_to?'&dateTo='.$date_to:''); ?>"
           class="tab-btn <?php echo $tab==='submissions'?'active-sub':'inactive'; ?>">
            <i class="fas fa-layer-group"></i> Submissions
            <span style="background:<?php echo $tab==='submissions'?'#3b82f6':'#94a3b8'; ?>;color:white;border-radius:9px;min-width:16px;padding:0 5px;height:16px;font-size:0.62rem;font-weight:800;display:inline-flex;align-items:center;justify-content:center;">
                <?php echo $total_sub_res + $total_sub_closed; ?>
            </span>
        </a>
        <a href="closed.php?tab=complaints<?php echo ($date_from?'&dateFrom='.$date_from:'').($date_to?'&dateTo='.$date_to:''); ?>"
           class="tab-btn <?php echo $tab==='complaints'?'active-cmp':'inactive'; ?>">
            <i class="fas fa-exclamation-triangle"></i> Complaints
            <span style="background:<?php echo $tab==='complaints'?'#ef4444':'#94a3b8'; ?>;color:white;border-radius:9px;min-width:16px;padding:0 5px;height:16px;font-size:0.62rem;font-weight:800;display:inline-flex;align-items:center;justify-content:center;">
                <?php echo $total_cmp_res + $total_cmp_closed; ?>
            </span>
        </a>
    </div>

    <!-- Filter Row -->
    <form class="admin-filter-row" method="GET" action="closed.php">
        <input type="hidden" name="tab" value="<?php echo $tab; ?>">
        <?php if ($tab === 'submissions'): ?>
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
                <option value="<?php echo htmlspecialchars($b['name']); ?>" <?php echo $branch_filter===$b['name']?'selected':''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="admin-filter-group">
            <label class="admin-filter-label">Date From</label>
            <input type="date" name="dateFrom" class="admin-filter-input" value="<?php echo htmlspecialchars($date_from); ?>">
        </div>
        <div class="admin-filter-group">
            <label class="admin-filter-label">Date To</label>
            <input type="date" name="dateTo" class="admin-filter-input" value="<?php echo htmlspecialchars($date_to); ?>">
        </div>
        <button type="submit" class="btn" style="background:#64748b;color:white;border:none;padding:0.5rem 2rem;">Filter</button>
        <a href="closed.php?tab=<?php echo $tab; ?>" class="btn btn-outline" style="padding:0.5rem 1rem;">Clear</a>
    </form>

    <?php if ($tab === 'submissions'): ?>
    <!-- ===== SUBMISSIONS TABLE ===== -->
    <div class="admin-table-wrapper">
        <div class="admin-table-header" style="background:linear-gradient(90deg,#3b82f6,#8b5cf6);">
            <i class="fas fa-layer-group"></i> Resolved &amp; Closed Submissions
            <span style="margin-left:auto;background:rgba(255,255,255,0.25);border-radius:9px;padding:0.1rem 0.55rem;font-size:0.75rem;"><?php echo $total_sub_res + $total_sub_closed; ?></span>
        </div>
        <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Message</th>
                    <th>Requester</th>
                    <th>Branch</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Resolved</th>
                    <th>Resolved By</th>
                    <th>Confirmed By</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($submissions->num_rows > 0): ?>
                <?php while ($sub = $submissions->fetch_assoc()): ?>
                <tr>
                    <td style="font-weight:bold;color:#64748b;">#<?php echo $sub['id']; ?></td>
                    <td style="font-weight:600;"><?php echo htmlspecialchars(mb_substr($sub['message']??'',0,45)).(strlen($sub['message']??'')>45?'...':''); ?></td>
                    <td>
                        <div><?php echo htmlspecialchars($sub['user_name']); ?></div>
                        <div style="font-size:0.7rem;color:#64748b;"><?php echo htmlspecialchars($sub['user_phone']); ?></div>
                    </td>
                    <td><span style="background:#94a3b8;color:white;padding:0.1rem 0.4rem;border-radius:0.2rem;font-size:0.7rem;"><?php echo htmlspecialchars($sub['user_branch']); ?></span></td>
                    <td>
                        <?php $tc=$sub['type']==='INQUIRY'?'#ef4444':($sub['type']==='REQUEST'?'#8b5cf6':'#eab308'); ?>
                        <span class="badge" style="background:<?php echo $tc; ?>;"><?php echo $sub['type']; ?></span>
                        <?php if (!empty($sub['option_label'])): ?>
                            <div style="font-size:0.72rem;color:#64748b;margin-top:0.25rem;font-weight:600;"><?php echo htmlspecialchars($sub['option_label']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($sub['status']==='CLOSED'): ?>
                            <span class="status-closed">CLOSED</span>
                        <?php else: ?>
                            <span class="status-resolved">RESOLVED</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.8rem;"><?php echo $sub['resolved_at'] ? date('M d, Y H:i', strtotime($sub['resolved_at'])) : '-'; ?></td>
                    <td><?php echo $sub['resolved_by_name'] ? '<span style="background:#dbeafe;color:#1e40af;padding:.15rem .5rem;border-radius:.25rem;font-size:.75rem;font-weight:600;">'.htmlspecialchars($sub['resolved_by_name']).'</span>' : '<span style="color:#94a3b8;">-</span>'; ?></td>
                    <td><?php echo $sub['confirmed_by_name'] ? '<span style="background:#ede9fe;color:#5b21b6;padding:.15rem .5rem;border-radius:.25rem;font-size:.75rem;font-weight:600;">'.htmlspecialchars($sub['confirmed_by_name']).'</span>' : '<span style="color:#94a3b8;font-size:.8rem;">Pending Admin</span>'; ?></td>
                    <td>
                        <a href="submissions.php?view=<?php echo $sub['id']; ?>" class="btn btn-outline" style="padding:.2rem .6rem;font-size:.8rem;border-radius:2rem;">View</a>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php else: ?>
                <tr><td colspan="10" style="text-align:center;padding:2rem;color:#94a3b8;">No resolved or closed submissions found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <?php else: ?>
    <!-- ===== COMPLAINTS TABLE ===== -->
    <div class="admin-table-wrapper">
        <div class="admin-table-header" style="background:linear-gradient(90deg,#dc2626,#7c3aed);">
            <i class="fas fa-exclamation-triangle"></i> Resolved &amp; Closed Complaints
            <span style="margin-left:auto;background:rgba(255,255,255,0.25);border-radius:9px;padding:0.1rem 0.55rem;font-size:0.75rem;"><?php echo $total_cmp_res + $total_cmp_closed; ?></span>
        </div>
        <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>CRN</th>
                    <th>Member</th>
                    <th>Branch</th>
                    <th>Complaint Type</th>
                    <th>Complexity</th>
                    <th>Status</th>
                    <th>Date Resolved</th>
                    <th>Aging (Days)</th>
                    <th>Resolved By</th>
                    <th>Resolution of Complaint</th>
                    <th>Confirmed By</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($complaints->num_rows > 0): ?>
                <?php while ($c = $complaints->fetch_assoc()): ?>
                <tr>
                    <td style="font-weight:700;color:#ef4444;">CRN-<?php echo $c['id']; ?></td>
                    <td style="font-weight:600;"><?php echo htmlspecialchars($c['user_name']); ?></td>
                    <td><?php echo htmlspecialchars($c['user_branch']); ?></td>
                    <td><span style="background:#fee2e2;color:#dc2626;padding:.2rem .5rem;border-radius:.25rem;font-size:.75rem;font-weight:bold;"><?php echo htmlspecialchars(mb_substr($c['complaint_details']??'',0,20)).(strlen($c['complaint_details']??'')>20?'...':''); ?></span></td>
                    <td>
                        <?php $cx=$c['complexity'];
                        $cxbg=$cx==='COMPLEX'?'#fee2e2':($cx==='SIMPLE'?'#dcfce7':'#f1f5f9');
                        $cxcol=$cx==='COMPLEX'?'#dc2626':($cx==='SIMPLE'?'#16a34a':'#64748b'); ?>
                        <span style="background:<?php echo $cxbg; ?>;color:<?php echo $cxcol; ?>;padding:.2rem .5rem;border-radius:.25rem;font-size:.75rem;font-weight:bold;"><?php echo $cx ?? 'Unassessed'; ?></span>
                    </td>
                    <td>
                        <?php if ($c['status']==='CLOSED'): ?>
                            <span class="status-closed">CLOSED</span>
                        <?php else: ?>
                            <span class="status-resolved">RESOLVED</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.8rem;"><?php echo $c['resolved_at'] ? date('M d, Y', strtotime($c['resolved_at'])) : '-'; ?></td>
                    <td style="text-align:center;">
                        <?php
                        if ($c['resolved_at'] && $c['created_at']) {
                            $filed = new DateTime($c['created_at']);
                            $res   = new DateTime($c['resolved_at']);
                            $days  = max(1, (int)$filed->diff($res)->days);
                            $color = $days <= 3 ? '#10b981' : ($days <= 7 ? '#eab308' : '#ef4444');
                            echo '<span style="background:'.$color.'22;color:'.$color.';padding:.15rem .5rem;border-radius:.25rem;font-size:.75rem;font-weight:700;">'.$days.'d</span>';
                        } else {
                            echo '<span style="color:#94a3b8;">—</span>';
                        }
                        ?>
                    </td>
                    <td><?php echo $c['resolved_by_name'] ? '<span style="background:#dbeafe;color:#1e40af;padding:.15rem .5rem;border-radius:.25rem;font-size:.75rem;font-weight:600;">'.htmlspecialchars($c['resolved_by_name']).'</span>' : '-'; ?></td>
                    <td style="font-size:.78rem;max-width:180px;color:#334155;"><?php echo !empty($c['confirm_remark']) ? nl2br(htmlspecialchars(mb_substr($c['confirm_remark'],0,80).(strlen($c['confirm_remark'])>80?'...':''))) : '<span style="color:#94a3b8;font-size:.8rem;">—</span>'; ?></td>
                    <td><?php echo $c['confirmed_by_name'] ? '<span style="background:#ede9fe;color:#5b21b6;padding:.15rem .5rem;border-radius:.25rem;font-size:.75rem;font-weight:600;">'.htmlspecialchars($c['confirmed_by_name']).'</span>' : '<span style="color:#94a3b8;font-size:.8rem;">Pending Admin</span>'; ?></td>
                    <td style="display:flex;gap:0.3rem;flex-wrap:wrap;">
                        <a href="complaints.php?view=<?php echo $c['id']; ?>" class="btn btn-outline" style="padding:.2rem .6rem;font-size:.8rem;border-radius:2rem;color:#b91c1c;border-color:#b91c1c;">View</a>
                        <?php if ($c['status']==='CLOSED'): ?>
                        <a href="<?php echo BASE_URL; ?>admin/complaint_resolution_doc.php?id=<?php echo $c['id']; ?>" target="_blank" class="btn btn-outline" style="padding:.2rem .6rem;font-size:.8rem;border-radius:2rem;color:#0e83b5;border-color:#0e83b5;">
                            <i class="fas fa-file-alt"></i> Generate
                        </a>
                        <?php if (!empty($c['signed_doc_path'])): ?>
                        <a href="<?php echo BASE_URL; ?><?php echo htmlspecialchars($c['signed_doc_path']); ?>" target="_blank"
                           class="btn btn-outline" style="padding:.2rem .6rem;font-size:.8rem;border-radius:2rem;color:#15803d;border-color:#15803d;">
                            <i class="fas fa-eye"></i> Signed Doc
                        </a>
                        <?php else: ?>
                        <form method="POST" enctype="multipart/form-data" style="margin:0;display:inline-flex;gap:0.25rem;align-items:center;">
                            <input type="hidden" name="complaint_id" value="<?php echo $c['id']; ?>">
                            <input type="file" name="signed_doc" accept=".jpg,.jpeg,.png,.pdf"
                                   style="font-size:.72rem;max-width:110px;" required title="Upload signed resolution document">
                            <button type="submit" name="upload_signed" class="btn btn-outline"
                                    style="padding:.2rem .5rem;font-size:.8rem;border-radius:2rem;color:#15803d;border-color:#15803d;white-space:nowrap;">
                                <i class="fas fa-upload"></i> Upload
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php endif; ?>
                    </td>

                </tr>
                <?php endwhile; ?>
                <?php else: ?>
                <tr><td colspan="12" style="text-align:center;padding:2rem;color:#94a3b8;">No resolved or closed complaints found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/admin_footer.php'; ?>
