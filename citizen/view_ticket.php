<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('CITIZEN');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: dashboard.php"); exit(); }

// Fetch ticket belonging to this citizen (match by name+phone)
$stmt = $conn->prepare("SELECT * FROM tickets WHERE id=? AND user_name=? AND user_phone=?");
$stmt->bind_param("iss", $id, $_SESSION['name'], $_SESSION['phone']);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    header("Location: dashboard.php");
    exit();
}

$opt_label = '';
if ($ticket['option_id']) {
    $or = $conn->query("SELECT label FROM dropdown_options WHERE id={$ticket['option_id']}")->fetch_assoc();
    $opt_label = $or['label'] ?? '';
}

$page_title = "Ticket #" . $ticket['ref_no'];
include '../includes/header.php';
include '../includes/citizen_sidebar.php';
?>

<style>
.detail-label{font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:700;margin-bottom:0.25rem;letter-spacing:0.05em;}
.detail-value{color:#1e293b;font-size:0.95rem;}
</style>

<div class="fade-in">
    <div style="margin-bottom:1.5rem;">
        <a href="dashboard.php" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.35rem 0.9rem;font-size:0.85rem;margin-bottom:1rem;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1>Ticket Details</h1>
        <p style="color:rgba(255,255,255,0.7);">Reference: <strong><?php echo htmlspecialchars($ticket['ref_no']); ?></strong></p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;max-width:900px;">
        
        <!-- Left: Ticket Info -->
        <div class="glass-card">
            <h3 style="margin-bottom:1.25rem;color:#0e83b5;">Submission Info</h3>
            <div style="display:flex;flex-direction:column;gap:1rem;">
                <div>
                    <div class="detail-label">Reference No</div>
                    <div class="detail-value" style="font-weight:700;"><?php echo htmlspecialchars($ticket['ref_no']); ?></div>
                </div>
                <div>
                    <div class="detail-label">Type</div>
                    <?php
                    $type_colors = ['INQUIRY'=>'#3b82f6','SUGGESTION'=>'#8b5cf6','REQUEST'=>'#f59e0b'];
                    $tc = $type_colors[$ticket['type']] ?? '#64748b';
                    ?>
                    <span style="background:<?php echo $tc; ?>20;color:<?php echo $tc; ?>;padding:0.25rem 0.7rem;border-radius:0.4rem;font-size:0.85rem;font-weight:700;">
                        <?php echo htmlspecialchars($ticket['type']); ?>
                    </span>
                </div>
                <div>
                    <div class="detail-label">Status</div>
                    <?php $sc = $ticket['status']==='OPEN'?'#ef4444':($ticket['status']==='RESOLVED'?'#10b981':'#64748b'); ?>
                    <span style="background:<?php echo $sc; ?>20;color:<?php echo $sc; ?>;padding:0.25rem 0.7rem;border-radius:0.4rem;font-size:0.85rem;font-weight:700;">
                        <?php echo htmlspecialchars($ticket['status']); ?>
                    </span>
                </div>
                <?php if ($opt_label): ?>
                <div>
                    <div class="detail-label">Category</div>
                    <div class="detail-value"><?php echo htmlspecialchars($opt_label); ?></div>
                </div>
                <?php endif; ?>
                <div>
                    <div class="detail-label">Date Submitted</div>
                    <div class="detail-value"><?php echo date('F d, Y \a\t g:i A', strtotime($ticket['created_at'])); ?></div>
                </div>
                <?php if ($ticket['resolved_at']): ?>
                <div>
                    <div class="detail-label">Date Resolved</div>
                    <div class="detail-value" style="color:#10b981;font-weight:600;"><?php echo date('F d, Y \a\t g:i A', strtotime($ticket['resolved_at'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Message & Resolution -->
        <div style="display:flex;flex-direction:column;gap:1.25rem;">
            <div class="glass-card">
                <h3 style="margin-bottom:1rem;color:#0e83b5;">Your Message</h3>
                <p style="color:#1e293b;line-height:1.7;white-space:pre-wrap;"><?php echo htmlspecialchars($ticket['message']); ?></p>
            </div>

            <?php if ($ticket['admin_remark']): ?>
            <div class="glass-card" style="border-left:4px solid #10b981;">
                <h3 style="margin-bottom:1rem;color:#10b981;">
                    <i class="fas fa-check-circle"></i> Resolution / Admin Remarks
                </h3>
                <p style="color:#1e293b;line-height:1.7;white-space:pre-wrap;"><?php echo htmlspecialchars($ticket['admin_remark']); ?></p>
                <?php if ($ticket['resolved_by_name']): ?>
                <p style="font-size:0.8rem;color:#64748b;margin-top:0.75rem;">
                    Resolved by: <strong><?php echo htmlspecialchars($ticket['resolved_by_name']); ?></strong>
                </p>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="glass-card" style="border-left:4px solid #f59e0b;text-align:center;padding:2rem;">
                <i class="fas fa-clock" style="font-size:2rem;color:#f59e0b;margin-bottom:0.75rem;display:block;"></i>
                <p style="color:#92400e;font-weight:600;margin:0;">Awaiting review by our team.</p>
                <p style="color:#92400e;font-size:0.85rem;margin:0.3rem 0 0;">We'll get back to you as soon as possible.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>


</main>
</div>

<?php include '../includes/footer.php'; ?>
