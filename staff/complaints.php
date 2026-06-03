<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('STAFF');

$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;

// Handle resolve action by staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve'])) {
    $cid    = (int)$_POST['complaint_id'];
    $remark = sanitize($_POST['admin_remark']);
    $name   = $_SESSION['name'];
    $stmt = $conn->prepare("UPDATE complaints SET status='RESOLVED', admin_remark=?, resolved_at=NOW(), resolved_by_name=? WHERE id=? AND status='OPEN'");
    $stmt->bind_param("ssi", $remark, $name, $cid);
    $stmt->execute();
    header("Location: complaints.php?view=$cid&resolved=1");
    exit();
}

// Handle signed document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_signed'])) {
    $cid = (int)$_POST['complaint_id'];
    if (isset($_FILES['signed_doc']) && $_FILES['signed_doc']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/signed_docs/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $ext  = strtolower(pathinfo($_FILES['signed_doc']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','pdf'];
        if (in_array($ext, $allowed)) {
            $fname = 'signed_complaint_' . $cid . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['signed_doc']['tmp_name'], $uploadDir . $fname)) {
                $path = 'uploads/signed_docs/' . $fname;
                $stmt = $conn->prepare("UPDATE complaints SET signed_doc_path=? WHERE id=?");
                $stmt->bind_param("si", $path, $cid);
                $stmt->execute();
            }
        }
    }
    header("Location: complaints.php?view=$cid");
    exit();
}

// Date filter
$date_from = isset($_GET['dateFrom']) ? sanitize($_GET['dateFrom']) : '';
$date_to   = isset($_GET['dateTo'])   ? sanitize($_GET['dateTo'])   : '';
$where     = "WHERE status != 'SPAM' AND complexity IS NOT NULL";
if ($date_from) $where .= " AND DATE(created_at) >= '$date_from'";
if ($date_to)   $where .= " AND DATE(created_at) <= '$date_to'";

$total_complaints = $conn->query("SELECT COUNT(*) as c FROM complaints WHERE status != 'SPAM' AND complexity IS NOT NULL")->fetch_assoc()['c'];
$open_complaints  = $conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='OPEN' AND complexity IS NOT NULL")->fetch_assoc()['c'];
$complaints       = $conn->query("SELECT * FROM complaints $where ORDER BY created_at DESC");

$detail = null;
if ($view_id > 0) {
    $detail = $conn->query("SELECT * FROM complaints WHERE id=$view_id AND status != 'SPAM' AND complexity IS NOT NULL")->fetch_assoc();
    if (!$detail) { header("Location: complaints.php"); exit(); }
}

$page_title = "Complaints";
include '../includes/staff_header.php';
include '../includes/staff_sidebar.php';
?>

<?php if ($detail): ?>
<!-- Detail View -->
<div class="fade-in">
    <a href="complaints.php" style="color:#64748b;text-decoration:none;font-size:0.9rem;"><i class="fas fa-arrow-left"></i> Back to Complaints</a>
    <h1 style="font-size:1.75rem;color:#ef4444;margin:0.5rem 0 1.5rem;">CRN-<?php echo $detail['id']; ?></h1>

    <?php if (isset($_GET['resolved'])): ?>
    <div style="background:rgba(16,185,129,0.1);border:1px solid #10b981;color:#065f46;padding:1rem;border-radius:0.5rem;margin-bottom:1.5rem;">
        <i class="fas fa-check-circle"></i> Complaint resolved successfully.
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1.5fr;gap:1.5rem;">
        <div>
            <div class="glass-card" style="margin-bottom:1rem;">
                <h4 style="margin-bottom:1rem;color:#ef4444;">Member Info</h4>
                <?php foreach(['Name'=>$detail['user_name'],'Phone'=>$detail['user_phone'],'Email'=>$detail['user_email'],'Branch'=>$detail['user_branch']] as $l=>$v): ?>
                <div style="margin-bottom:.75rem;">
                    <div style="font-size:.72rem;color:#94a3b8;font-weight:700;text-transform:uppercase;"><?php echo $l; ?></div>
                    <div style="color:#1e293b;font-weight:500;"><?php echo htmlspecialchars($v??'-'); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="glass-card">
                <h4 style="margin-bottom:1rem;">Complaint Details</h4>
                <?php foreach([
                    'Type'        => $detail['complaint_details'],
                    'Transaction' => $detail['transaction_type'],
                    'Status'      => $detail['status'],
                    'Complexity'  => $detail['complexity'] ?? 'Unassessed',
                    'Filed'       => date('M d, Y H:i', strtotime($detail['created_at'])),
                    'Resolved'    => $detail['resolved_at'] ? date('M d, Y H:i', strtotime($detail['resolved_at'])) : '-',
                    'Resolved By' => $detail['resolved_by_name'] ?? '-',
                ] as $l=>$v): ?>
                <div style="margin-bottom:.75rem;">
                    <div style="font-size:.72rem;color:#94a3b8;font-weight:700;text-transform:uppercase;"><?php echo $l; ?></div>
                    <div style="color:#1e293b;font-weight:500;"><?php echo htmlspecialchars($v??'-'); ?></div>
                </div>
                <?php endforeach; ?>
                <?php if ($detail['has_documents'] && $detail['document_path']): ?>
                <div style="margin-top:1.25rem;">
                    <div style="font-size:.72rem;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:0.5rem;">Supporting Document</div>
                    <a href="../<?php echo htmlspecialchars($detail['document_path']); ?>" target="_blank" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.4rem 0.8rem;font-size:0.85rem;"><i class="fas fa-file-download"></i> View Attachment</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <div class="glass-card" style="margin-bottom:1rem;">
                <h4 style="margin-bottom:.75rem;">Description</h4>
                <p style="line-height:1.7;"><?php echo nl2br(htmlspecialchars($detail['description'])); ?></p>
            </div>
            <?php if ($detail['desired_resolution']): ?>
            <div class="glass-card" style="margin-bottom:1rem;">
                <h4 style="margin-bottom:.75rem;">Desired Resolution</h4>
                <p><?php echo nl2br(htmlspecialchars($detail['desired_resolution'])); ?></p>
            </div>
            <?php endif; ?>
            <?php if ($detail['admin_remark']): ?>
            <div class="glass-card" style="margin-bottom:1rem;border-left:4px solid #10b981;">
                <h4 style="margin-bottom:.75rem;color:#10b981;">Resolution Remark</h4>
                <p><?php echo nl2br(htmlspecialchars($detail['admin_remark'])); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($detail['status'] === 'OPEN' && !empty($detail['complexity'])): ?>
            <div class="glass-card">
                <h4 style="margin-bottom:1rem;">Resolve Complaint</h4>
                <form method="POST">
                    <input type="hidden" name="complaint_id" value="<?php echo $detail['id']; ?>">
                    <div class="form-group">
                        <label class="form-label">Resolution Remark <span style="color:#ef4444;">*</span></label>
                        <textarea name="admin_remark" class="form-input" rows="5"
                                  placeholder="Describe how the complaint was resolved..."
                                  style="resize:vertical;" required></textarea>
                    </div>
                    <div style="background:rgba(245,158,11,0.07);border:1px solid rgba(245,158,11,0.25);padding:1rem;border-radius:0.75rem;margin-bottom:1.5rem;">
                        <p style="font-size:0.85rem;color:#92400e;margin:0;">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> Once submitted, this complaint will be marked as <strong>RESOLVED</strong>.
                        </p>
                    </div>
                    <button type="submit" name="resolve" class="btn btn-primary" style="width:100%;justify-content:center;">
                        <i class="fas fa-check-circle"></i> Mark as Resolved
                    </button>
                </form>
            </div>
            <?php elseif ($detail['status'] === 'RESOLVED'): ?>
            <div class="glass-card" style="border-left:4px solid #10b981;">
                <h5 style="color:#047857;margin-bottom:0.5rem;"><i class="fas fa-check-circle"></i> Resolved by <?php echo htmlspecialchars($detail['resolved_by_name'] ?? 'Staff'); ?></h5>
                <p style="font-size:0.85rem;color:#065f46;margin-bottom:0;">On <?php echo $detail['resolved_at'] ? date('M d, Y H:i', strtotime($detail['resolved_at'])) : '-'; ?></p>
            </div>
            <?php elseif ($detail['status'] === 'CLOSED'): ?>
            <div class="glass-card" style="border-left:4px solid #10b981;">
                <h5 style="color:#047857;margin-bottom:0.5rem;"><i class="fas fa-lock"></i> Closed &amp; Confirmed by <?php echo htmlspecialchars($detail['confirmed_by_name'] ?? 'Admin'); ?></h5>
                <p style="font-size:0.85rem;color:#065f46;margin-bottom:1.25rem;">On <?php echo $detail['confirmed_at'] ? date('M d, Y H:i', strtotime($detail['confirmed_at'])) : '-'; ?></p>

                <!-- Generate button -->
                <a href="<?php echo BASE_URL; ?>admin/complaint_resolution_doc.php?id=<?php echo $detail['id']; ?>" target="_blank"
                   style="display:flex;align-items:center;justify-content:center;gap:0.6rem;width:100%;padding:0.75rem;border-radius:0.6rem;background:linear-gradient(135deg,#0e83b5,#3b82f6);color:white;font-weight:700;font-size:0.9rem;text-decoration:none;margin-bottom:1rem;">
                    <i class="fas fa-file-alt"></i> Generate Resolution Document
                </a>

                <!-- Signed doc status -->
                <?php if (!empty($detail['signed_doc_path'])): ?>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:0.6rem;padding:0.85rem;margin-bottom:0.75rem;">
                    <div style="font-size:0.8rem;font-weight:700;color:#15803d;margin-bottom:0.5rem;"><i class="fas fa-check-circle"></i> Signed Document Uploaded</div>
                    <a href="<?php echo BASE_URL; ?><?php echo htmlspecialchars($detail['signed_doc_path']); ?>" target="_blank"
                       style="display:inline-flex;align-items:center;gap:0.4rem;font-size:0.85rem;color:#0e83b5;font-weight:600;text-decoration:none;">
                        <i class="fas fa-eye"></i> View Signed Document
                    </a>
                </div>
                <?php endif; ?>

                <!-- Upload form -->
                <form method="POST" enctype="multipart/form-data" style="margin-top:0.25rem;">
                    <input type="hidden" name="complaint_id" value="<?php echo $detail['id']; ?>">
                    <label style="font-size:0.8rem;font-weight:700;color:#374151;display:block;margin-bottom:0.4rem;">
                        <i class="fas fa-upload"></i>
                        <?php echo !empty($detail['signed_doc_path']) ? 'Replace Signed Document' : 'Upload Signed Resolution Document'; ?>
                    </label>
                    <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                        <input type="file" name="signed_doc" accept=".jpg,.jpeg,.png,.pdf"
                               style="flex:1;font-size:0.82rem;padding:0.45rem;border:1px solid #d1d5db;border-radius:0.5rem;background:#f9fafb;" required>
                        <button type="submit" name="upload_signed" class="btn btn-primary"
                                style="padding:0.5rem 1rem;font-size:0.82rem;white-space:nowrap;background:#10b981;border-color:#10b981;">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    </div>
                    <p style="font-size:0.72rem;color:#94a3b8;margin-top:0.3rem;">Accepted: JPG, PNG, PDF</p>
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
        <h1 style="font-size:1.75rem;color:#ef4444;">Complaints</h1>
    </div>

    <div class="stat-card-row">
        <div class="stat-card">
            <div class="stat-card-title">Total Complaints</div>
            <div class="stat-card-value" style="color:#ef4444;"><?php echo $total_complaints; ?></div>
            <i class="fas fa-exclamation-triangle" style="position:absolute;right:1.25rem;top:1.25rem;font-size:2.2rem;opacity:.1;color:#ef4444;"></i>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">Open Complaints</div>
            <div class="stat-card-value" style="color:#eab308;"><?php echo $open_complaints; ?></div>
            <i class="fas fa-clock" style="position:absolute;right:1.25rem;top:1.25rem;font-size:2.2rem;opacity:.1;color:#eab308;"></i>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">Resolved</div>
            <div class="stat-card-value" style="color:#10b981;"><?php echo $total_complaints - $open_complaints; ?></div>
            <i class="fas fa-check-circle" style="position:absolute;right:1.25rem;top:1.25rem;font-size:2.2rem;opacity:.1;color:#10b981;"></i>
        </div>
    </div>

    <form class="admin-filter-row" method="GET" action="complaints.php">
        <div class="admin-filter-group">
            <label class="admin-filter-label">Date From</label>
            <input type="date" name="dateFrom" class="admin-filter-input" value="<?php echo htmlspecialchars($date_from); ?>">
        </div>
        <div class="admin-filter-group">
            <label class="admin-filter-label">Date To</label>
            <input type="date" name="dateTo" class="admin-filter-input" value="<?php echo htmlspecialchars($date_to); ?>">
        </div>
        <button type="submit" class="btn" style="background:#64748b;color:white;border:none;padding:.5rem 2rem;">Filter</button>
        <a href="complaints.php" class="btn btn-outline" style="padding:.5rem 1rem;">Clear</a>
    </form>

    <div class="admin-table-wrapper">
        <div class="admin-table-header"><i class="fas fa-exclamation-triangle"></i> Recent Complaints</div>
        <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr><th>ID</th><th>Member</th><th>Branch</th><th>Complaint Type</th><th>Complexity</th><th>Date Filed</th><th>Date Resolved</th><th>Resolved By</th><th>Status</th><th>Action</th></tr>
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
                        <?php $cx=$c['complexity']; echo '<span style="background:'.($cx==='COMPLEX'?'#fee2e2':($cx==='SIMPLE'?'#dcfce7':'#f1f5f9')).';color:'.($cx==='COMPLEX'?'#dc2626':($cx==='SIMPLE'?'#16a34a':'#64748b')).';padding:.2rem .5rem;border-radius:.25rem;font-size:.75rem;font-weight:bold;">'.($cx??'Unassessed').'</span>'; ?>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                    <td><?php echo $c['resolved_at'] ? date('M d, Y', strtotime($c['resolved_at'])) : '-'; ?></td>
                    <td><?php echo $c['resolved_by_name'] ? '<span style="background:#dbeafe;color:#1e40af;padding:.15rem .5rem;border-radius:.25rem;font-size:.75rem;font-weight:600;">'.htmlspecialchars($c['resolved_by_name']).'</span>' : '-'; ?></td>
                    <td><span class="badge" style="background:<?php echo $c['status']==='OPEN'?'#eab308':($c['status']==='CLOSED'?'#8b5cf6':'#10b981'); ?>;"><?php echo $c['status']; ?></span></td>
                    <td>
                        <a href="complaints.php?view=<?php echo $c['id']; ?>" class="btn btn-outline" style="padding:.25rem .5rem;font-size:.8rem;color:#b91c1c;border-color:#b91c1c;">Review</a>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php else: ?>
                <tr><td colspan="10" style="text-align:center;padding:2rem;color:#94a3b8;">No complaints filed yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php endif; ?>
<?php include '../includes/admin_footer.php'; ?>
