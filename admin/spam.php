<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('ADMIN');

// Handle Restore from SPAM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore'])) {
    $id = (int)$_POST['id'];
    $type = $_POST['spam_type'];
    if ($type === 'ticket') {
        $conn->query("UPDATE tickets SET status='OPEN' WHERE id=$id");
    } elseif ($type === 'complaint') {
        $conn->query("UPDATE complaints SET status='OPEN' WHERE id=$id");
    }
    header("Location: spam.php");
    exit();
}

// Fetch SPAM tickets
$spam_tickets = $conn->query("SELECT * FROM tickets WHERE status='SPAM' ORDER BY created_at DESC");

// Fetch SPAM complaints
$spam_complaints = $conn->query("SELECT * FROM complaints WHERE status='SPAM' ORDER BY created_at DESC");

$page_title = "Spam Submissions";
include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<div class="fade-in">
    <div style="margin-bottom: 1.5rem;">
        <h1 style="font-size: 1.75rem; color: #ef4444; margin-bottom: 0.25rem;">Spam Folder</h1>
        <p style="color: #64748b; font-size: 0.9rem;">Submissions and complaints marked as SPAM.</p>
    </div>

    <!-- SPAM Submissions Table -->
    <div class="admin-table-wrapper">
        <div class="admin-table-header" style="background: #3b82f6;">
            <i class="fas fa-layer-group"></i> Spam Submissions (<?php echo $spam_tickets->num_rows; ?>)
        </div>
        <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Message</th>
                    <th>Requester</th>
                    <th>Type</th>
                    <th>Date Marked</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($spam_tickets->num_rows > 0): ?>
                    <?php while ($sub = $spam_tickets->fetch_assoc()): ?>
                    <tr>
                        <td style="font-weight:bold;color:#64748b;">#<?php echo $sub['id']; ?></td>
                        <td style="font-weight:600;"><?php echo htmlspecialchars(mb_substr($sub['message'] ?? '', 0, 50)) . (strlen($sub['message'] ?? '') > 50 ? '...' : ''); ?></td>
                        <td>
                            <div><?php echo htmlspecialchars($sub['user_name']); ?></div>
                            <div style="font-size:0.7rem;color:#64748b;"><?php echo htmlspecialchars($sub['user_phone']); ?></div>
                        </td>
                        <td>
                            <?php $type_color = $sub['type']==='INQUIRY'?'#ef4444':($sub['type']==='REQUEST'?'#8b5cf6':'#eab308'); ?>
                            <span class="badge" style="background:<?php echo $type_color; ?>;"><?php echo $sub['type']; ?></span>
                        </td>
                        <td style="font-size:0.8rem;"><?php echo date('M d, Y', strtotime($sub['created_at'])); ?></td>
                        <td style="display: flex; gap: 0.3rem;">
                            <a href="submissions.php?view=<?php echo $sub['id']; ?>" class="btn btn-outline" style="padding:0.2rem 0.6rem;font-size:0.8rem;border-radius:2rem;">View</a>
                            <form method="POST" onsubmit="return confirm('Restore this submission back to OPEN?');" style="margin:0;">
                                <input type="hidden" name="id" value="<?php echo $sub['id']; ?>">
                                <input type="hidden" name="spam_type" value="ticket">
                                <button type="submit" name="restore" class="btn btn-outline" style="padding:0.2rem 0.6rem;font-size:0.8rem;border-radius:2rem; color: #10b981; border-color: #10b981;">
                                    Restore
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center;padding:2rem;color:#94a3b8;">No spam submissions found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- SPAM Complaints Table -->
    <div class="admin-table-wrapper">
        <div class="admin-table-header">
            <i class="fas fa-exclamation-triangle"></i> Spam Complaints (<?php echo $spam_complaints->num_rows; ?>)
        </div>
        <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Member</th>
                    <th>Complaint Type</th>
                    <th>Date Marked</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($spam_complaints->num_rows > 0): ?>
                    <?php while ($c = $spam_complaints->fetch_assoc()): ?>
                    <tr>
                        <td style="font-weight:bold;color:#64748b;">#<?php echo $c['id']; ?></td>
                        <td style="font-weight:600;"><?php echo htmlspecialchars($c['user_name']); ?></td>
                        <td><span style="background:#fee2e2;color:#dc2626;padding:0.2rem 0.5rem;border-radius:0.25rem;font-size:0.75rem;font-weight:bold;"><?php echo htmlspecialchars(mb_substr($c['complaint_details']??'',0,30)).(strlen($c['complaint_details']??'')>30?'...':''); ?></span></td>
                        <td style="font-size:0.8rem;"><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                        <td style="display: flex; gap: 0.3rem;">
                            <a href="complaints.php?view=<?php echo $c['id']; ?>" class="btn btn-outline" style="padding:0.2rem 0.6rem;font-size:0.8rem;border-radius:2rem;">View</a>
                            <form method="POST" onsubmit="return confirm('Restore this complaint back to OPEN?');" style="margin:0;">
                                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                <input type="hidden" name="spam_type" value="complaint">
                                <button type="submit" name="restore" class="btn btn-outline" style="padding:0.2rem 0.6rem;font-size:0.8rem;border-radius:2rem; color: #10b981; border-color: #10b981;">
                                    Restore
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center;padding:2rem;color:#94a3b8;">No spam complaints found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>

