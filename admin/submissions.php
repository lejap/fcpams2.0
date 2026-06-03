<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('ADMIN');

$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$current_user = $conn->query("SELECT * FROM users WHERE id = {$_SESSION['user_id']}")->fetch_assoc();

// Handle resolve action (ADMIN can also resolve)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve'])) {
    $tid = (int)$_POST['ticket_id'];
    $remark = sanitize($_POST['admin_remark']);
    $name = $_SESSION['name'];
    $stmt = $conn->prepare("UPDATE tickets SET status='RESOLVED', admin_remark=?, resolved_at=NOW(), resolved_by_name=? WHERE id=?");
    $stmt->bind_param("ssi", $remark, $name, $tid);
    $stmt->execute();
    header("Location: submissions.php");
    exit();
}

// Handle Mark as SPAM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_spam'])) {
    $tid = (int)$_POST['ticket_id'];
    $stmt = $conn->prepare("UPDATE tickets SET status='SPAM' WHERE id=?");
    $stmt->bind_param("i", $tid);
    $stmt->execute();
    header("Location: submissions.php");
    exit();
}

// Handle admin confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    if ($current_user['role'] !== 'ADMIN') die('Forbidden');
    $tid            = (int)$_POST['ticket_id'];
    $name           = $_SESSION['name'];
    $confirm_remark = sanitize($_POST['confirm_remark'] ?? '');
    $stmt = $conn->prepare("UPDATE tickets SET status='CLOSED', confirmed_at=NOW(), confirmed_by_name=?, confirm_remark=? WHERE id=?");
    $stmt->bind_param("ssi", $name, $confirm_remark, $tid);
    $stmt->execute();
    header("Location: submissions.php");
    exit();
}

// Fetch detail view
$detail = null;
if ($view_id > 0) {
    $detail = $conn->query("SELECT * FROM tickets WHERE id=$view_id")->fetch_assoc();
}

// Filters
$type_filter = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$branch_filter = isset($_GET['branch']) ? sanitize($_GET['branch']) : '';
$date_from = isset($_GET['dateFrom']) ? sanitize($_GET['dateFrom']) : '';
$date_to   = isset($_GET['dateTo'])   ? sanitize($_GET['dateTo'])   : '';

$where = "WHERE status != 'SPAM'";
if ($type_filter)   $where .= " AND type='$type_filter'";
if ($branch_filter) $where .= " AND user_branch='$branch_filter'";
if ($date_from)     $where .= " AND DATE(created_at) >= '$date_from'";
if ($date_to)       $where .= " AND DATE(created_at) <= '$date_to'";

$submissions = $conn->query("SELECT * FROM tickets $where ORDER BY created_at DESC");
$branches    = $conn->query("SELECT * FROM branches ORDER BY name");

$page_title = "Submissions";
include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<?php if ($detail): ?>
<!-- Detail View -->
<div class="fade-in">
    <div style="margin-bottom:1.5rem;">
        <a href="submissions.php" style="color:#64748b;text-decoration:none;font-size:0.9rem;"><i class="fas fa-arrow-left"></i> Back to Submissions</a>
        <h1 style="font-size:1.75rem;color:#0e83b5;margin-top:0.5rem;">Submission #<?php echo $detail['id']; ?></h1>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1.5fr;gap:1.5rem;">
        <div class="glass-card">
            <h4 style="margin-bottom:1rem;color:#3b82f6;">Submission Info</h4>
            <?php foreach ([
                'Ref No'   => $detail['ref_no'],
                'Name'     => $detail['user_name'],
                'Phone'    => $detail['user_phone'],
                'Email'    => $detail['user_email'],
                'Branch'   => $detail['user_branch'],
                'Type'     => $detail['type'],
                'Status'   => $detail['status'],
                'Filed'    => date('M d, Y H:i', strtotime($detail['created_at'])),
                'Resolved' => $detail['resolved_at'] ? date('M d, Y H:i', strtotime($detail['resolved_at'])) : '-',
                'Resolved By' => $detail['resolved_by_name'] ?? '-',
                'Confirmed' => $detail['confirmed_at'] ? date('M d, Y H:i', strtotime($detail['confirmed_at'])) : '-',
                'Confirmed By' => $detail['confirmed_by_name'] ?? '-',
            ] as $label => $value): ?>
            <div style="margin-bottom:0.75rem;">
                <div style="font-size:0.72rem;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;"><?php echo $label; ?></div>
                <div style="font-weight:500;color:#1e293b;"><?php echo htmlspecialchars($value); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div>
            <div class="glass-card" style="margin-bottom:1rem;">
                <h4 style="margin-bottom:0.75rem;">Message</h4>
                <p style="color:#334155;line-height:1.7;"><?php echo nl2br(htmlspecialchars($detail['message'])); ?></p>
            </div>
            <?php if ($detail['admin_remark']): ?>
            <div class="glass-card" style="margin-bottom:1rem;border-left:4px solid #10b981;">
                <h4 style="margin-bottom:0.75rem;color:#10b981;">Admin Remark</h4>
                <p><?php echo nl2br(htmlspecialchars($detail['admin_remark'])); ?></p>
            </div>
            <?php endif; ?>
            <?php if (!empty($detail['confirm_remark'])): ?>
            <div class="glass-card" style="margin-bottom:1rem;border-left:4px solid #8b5cf6;">
                <h4 style="margin-bottom:0.75rem;color:#8b5cf6;"><i class="fas fa-clipboard-check"></i> Resolution of Complaint (Admin)</h4>
                <p><?php echo nl2br(htmlspecialchars($detail['confirm_remark'])); ?></p>
            </div>
            <?php endif; ?>
            <?php if ($detail['status'] === 'OPEN'): ?>
            <div class="glass-card">
                <h4 style="margin-bottom:1rem;">Resolve This Submission</h4>
                <form method="POST">
                    <input type="hidden" name="ticket_id" value="<?php echo $detail['id']; ?>">
                    <div class="form-group">
                        <label class="form-label">Admin Remark</label>
                        <textarea name="admin_remark" class="form-input" rows="4" placeholder="Describe the resolution..." required></textarea>
                    </div>
                    <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                        <button type="submit" name="resolve" class="btn btn-primary" style="flex: 1; justify-content:center;">
                            Mark as Resolved
                        </button>
                    </div>
                </form>
                <form method="POST" style="margin-top: 0.5rem;" onsubmit="return confirm('Are you sure you want to mark this submission as SPAM? It will be removed from the main list.');">
                    <input type="hidden" name="ticket_id" value="<?php echo $detail['id']; ?>">
                    <button type="submit" name="mark_spam" class="btn btn-outline" style="width: 100%; justify-content:center; color: #ef4444; border-color: #ef4444;">
                        <i class="fas fa-ban"></i> Mark as Spam
                    </button>
                </form>
            </div>
            <?php elseif ($detail['status'] === 'RESOLVED' && $current_user['role'] === 'ADMIN'): ?>
            <div class="glass-card" style="border-left:4px solid #8b5cf6;">
                <h4 style="margin-bottom:1rem;color:#8b5cf6;">Confirm Resolution (Admin)</h4>
                <p style="font-size:0.85rem;color:#64748b;margin-bottom:1rem;">As the Admin, you can confirm this resolution to officially CLOSE the submission. Please provide your resolution remarks below.</p>
                <form method="POST">
                    <input type="hidden" name="ticket_id" value="<?php echo $detail['id']; ?>">
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label class="form-label" style="font-weight:700;color:#8b5cf6;">Resolution of Complaint <span style="color:#ef4444;">*</span></label>
                        <textarea name="confirm_remark" class="form-input" rows="4"
                                  placeholder="Describe the resolution of this submission..."
                                  required
                                  style="border-color:#8b5cf6;resize:vertical;"></textarea>
                    </div>
                    <button type="submit" name="confirm" class="btn btn-primary" style="background:#8b5cf6;border:none;width:100%;justify-content:center;">
                        <i class="fas fa-check-circle"></i> Confirm & Close
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php else: ?>
<!-- List View -->
<div class="fade-in">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
        <h1 style="font-size:1.75rem;color:#0e83b5;">All Submissions</h1>
    </div>

    <form class="admin-filter-row" method="GET" action="submissions.php">
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
                <option value="<?php echo htmlspecialchars($b['name']); ?>" <?php echo $branch_filter===$b['name']?'selected':''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
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
        <a href="submissions.php" class="btn btn-outline" style="padding:0.5rem 1rem;">Clear</a>
    </form>

    <div class="admin-table-wrapper">
        <div class="admin-table-header"><i class="fas fa-layer-group"></i> Recent Submissions</div>
        <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th><th>Message</th><th>Requester</th><th>Branch</th><th>Type</th><th>Status</th><th>Created</th><th>Resolved</th><th>Resolved By</th><th>Action</th>
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
                        <td><?php $tc=$sub['type']==='INQUIRY'?'#ef4444':($sub['type']==='REQUEST'?'#8b5cf6':'#eab308'); ?><span class="badge" style="background:<?php echo $tc;?>"><?php echo $sub['type']; ?></span></td>
                        <td><span class="badge" style="background:<?php echo $sub['status']==='OPEN'?'#ef4444':'#10b981'; ?>;"><?php echo $sub['status']??'OPEN'; ?></span></td>
                        <td style="font-size:0.8rem;"><?php echo date('M d, Y H:i', strtotime($sub['created_at'])); ?></td>
                        <td style="font-size:0.8rem;"><?php echo $sub['resolved_at'] ? date('M d, Y H:i', strtotime($sub['resolved_at'])) : '-'; ?></td>
                        <td><?php echo $sub['resolved_by_name'] ? '<span style="background:#dbeafe;color:#1e40af;padding:.15rem .5rem;border-radius:.25rem;font-size:.75rem;font-weight:600;">'.htmlspecialchars($sub['resolved_by_name']).'</span>' : '<span style="color:#94a3b8;font-size:.8rem;">-</span>'; ?></td>
                        <td><a href="submissions.php?view=<?php echo $sub['id']; ?>" class="btn btn-outline" style="padding:.2rem .8rem;font-size:.8rem;border-radius:2rem;">View</a></td>
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
<?php endif; ?>
<?php include '../includes/admin_footer.php'; ?>

