<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('STAFF');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: dashboard.php"); exit(); }

// Fetch ticket — no JOIN needed, tickets are self-contained
$stmt = $conn->prepare("SELECT * FROM tickets WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $note        = sanitize($_POST['note']);
    $staff_name  = $_SESSION['name'];

    // Update ticket: store remark, resolved_by_name, resolved_at, and set status RESOLVED
    $stmt = $conn->prepare("
        UPDATE tickets
        SET status = 'RESOLVED',
            admin_remark = ?,
            resolved_by_name = ?,
            resolved_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->bind_param("ssi", $note, $staff_name, $id);
    
    if ($stmt->execute()) {
        header("Location: dashboard.php?success=1");
        exit();
    } else {
        $error = "Failed to save resolution. Please try again.";
    }
}

// Fetch category label if exists
$opt_label = '';
if ($ticket['option_id']) {
    $or = $conn->query("SELECT label FROM dropdown_options WHERE id={$ticket['option_id']}")->fetch_assoc();
    $opt_label = $or['label'] ?? '';
}

$page_title = "Resolve Ticket";
include '../includes/header.php';
?>

<style>
.detail-label{font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:700;margin-bottom:0.25rem;letter-spacing:0.05em;}
.detail-value{color:#1e293b;font-size:0.92rem;}
</style>

<div style="max-width:960px;margin:0 auto;" class="fade-in">
    <div style="margin-bottom:2rem;">
        <a href="dashboard.php" style="color:rgba(255,255,255,0.7);text-decoration:none;font-size:0.9rem;display:inline-flex;align-items:center;gap:0.4rem;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1 style="margin-top:0.75rem;color:white;">Ticket Resolution</h1>
        <p style="color:rgba(255,255,255,0.7);">Providing a solution for <strong><?php echo htmlspecialchars($ticket['ref_no']); ?></strong></p>
    </div>

    <?php if ($error): ?>
    <div style="background:rgba(239,68,68,0.1);border:1px solid #ef4444;color:#ef4444;padding:1rem;border-radius:0.5rem;margin-bottom:1.5rem;">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1.5fr;gap:1.5rem;">
        <!-- Ticket Details -->
        <div class="glass-card" style="height:fit-content;">
            <h4 style="margin-bottom:1.25rem;color:#0e83b5;">Ticket Info</h4>
            <div style="display:flex;flex-direction:column;gap:1rem;">
                <div>
                    <div class="detail-label">Ref No</div>
                    <div class="detail-value" style="font-weight:700;"><?php echo htmlspecialchars($ticket['ref_no']); ?></div>
                </div>
                <div>
                    <div class="detail-label">Requester</div>
                    <div class="detail-value" style="font-weight:600;"><?php echo htmlspecialchars($ticket['user_name']); ?></div>
                    <div style="font-size:0.78rem;color:#94a3b8;"><?php echo htmlspecialchars($ticket['user_phone']); ?></div>
                </div>
                <div>
                    <div class="detail-label">Branch</div>
                    <div class="detail-value"><?php echo htmlspecialchars($ticket['user_branch']); ?></div>
                </div>
                <div>
                    <div class="detail-label">Type</div>
                    <?php
                    $tc = ['INQUIRY'=>'#3b82f6','SUGGESTION'=>'#8b5cf6','REQUEST'=>'#f59e0b'];
                    $color = $tc[$ticket['type']] ?? '#64748b';
                    ?>
                    <span style="background:<?php echo $color; ?>20;color:<?php echo $color; ?>;padding:0.2rem 0.6rem;border-radius:0.3rem;font-size:0.82rem;font-weight:700;">
                        <?php echo htmlspecialchars($ticket['type']); ?>
                    </span>
                </div>
                <?php if ($opt_label): ?>
                <div>
                    <div class="detail-label">Category</div>
                    <div class="detail-value"><?php echo htmlspecialchars($opt_label); ?></div>
                </div>
                <?php endif; ?>
                <div>
                    <div class="detail-label">Status</div>
                    <span style="background:#fef9c3;color:#92400e;padding:0.2rem 0.6rem;border-radius:0.3rem;font-size:0.82rem;font-weight:700;">
                        <?php echo htmlspecialchars($ticket['status']); ?>
                    </span>
                </div>
                <div>
                    <div class="detail-label">Submitted</div>
                    <div class="detail-value"><?php echo date('M d, Y H:i', strtotime($ticket['created_at'])); ?></div>
                </div>
            </div>
            <hr style="margin:1.25rem 0;border:none;border-top:1px solid rgba(0,0,0,0.08);">
            <div>
                <div class="detail-label" style="margin-bottom:0.5rem;">Message</div>
                <p style="color:#1e293b;line-height:1.6;font-size:0.9rem;white-space:pre-wrap;"><?php echo htmlspecialchars($ticket['message']); ?></p>
            </div>
        </div>

        <!-- Resolution Form -->
        <div class="glass-card">
            <h4 style="margin-bottom:1.25rem;color:#0e83b5;">Resolution Details</h4>
            <form action="resolve_ticket.php?id=<?php echo $id; ?>" method="POST">
                <div class="form-group">
                    <label class="form-label">Resolution / Admin Remark <span style="color:#ef4444;">*</span></label>
                    <textarea name="note" class="form-input" rows="9" required
                              placeholder="Explain the actions taken or provide a response to this submission..."
                              style="resize:vertical;"></textarea>
                </div>
                <div style="background:rgba(245,158,11,0.07);border:1px solid rgba(245,158,11,0.25);padding:1rem;border-radius:0.75rem;margin-bottom:1.5rem;">
                    <p style="font-size:0.85rem;color:#92400e;margin:0;">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> Once submitted, this ticket will be marked as <strong>RESOLVED</strong>. The administrator will review it for final confirmation.
                    </p>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;padding:0.9rem;font-size:1rem;justify-content:center;">
                    <i class="fas fa-check-circle"></i> Submit Resolution
                </button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
