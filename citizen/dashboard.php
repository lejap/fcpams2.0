<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('CITIZEN');

// Fetch citizen's tickets matched by name + phone (citizens have no user_id in tickets)
$stmt = $conn->prepare("SELECT * FROM tickets WHERE user_name = ? AND user_phone = ? ORDER BY created_at DESC");
$stmt->bind_param("ss", $_SESSION['name'], $_SESSION['phone']);
$stmt->execute();
$tickets = $stmt->get_result();

$total   = $tickets->num_rows;
$open    = 0; $resolved = 0; $closed = 0;
$rows    = [];
while ($r = $tickets->fetch_assoc()) {
    $rows[] = $r;
    if ($r['status'] === 'OPEN')     $open++;
    if ($r['status'] === 'RESOLVED') $resolved++;
    if ($r['status'] === 'CLOSED')   $closed++;
}

// Success flash
$success_type = $_GET['success'] ?? '';
$survey_ok    = isset($_GET['survey_success']);

$page_title = "My Dashboard";
include '../includes/header.php';
include '../includes/citizen_sidebar.php';
?>

<div class="fade-in">

    <?php if ($success_type === 'suggestion'): ?>
    <div class="glass-card" style="border-left:4px solid var(--success);margin-bottom:1.5rem;">
        <h3 style="color:var(--success);margin-bottom:0.4rem;">Thank you for your feedback!</h3>
        <p class="mb-0">Your comment or suggestion has been received and will help us improve our services for all members.</p>
    </div>
    <?php elseif ($success_type === 'inquiry'): ?>
    <div class="glass-card" style="border-left:4px solid var(--success);margin-bottom:1.5rem;">
        <h3 style="color:var(--success);margin-bottom:0.4rem;">Thank you for your feedback!</h3>
        <p class="mb-0">Your inquiry has been submitted. We will get back to you shortly.</p>
    </div>
    <?php elseif ($success_type === 'complaint'): ?>
    <div class="glass-card" style="border-left:4px solid var(--success);margin-bottom:1.5rem;">
        <h3 style="color:var(--success);margin-bottom:0.4rem;">Thank you for your message.</h3>
        <p class="mb-0">Your complaint has been received. A Cooperative personnel will review your concern and contact you within 48 hours on the cellphone number you provided.</p>
        <p style="margin-top:0.4rem;font-size:0.9rem;">We appreciate your patience and cooperation.</p>
    </div>
    <?php elseif ($success_type === 'request'): ?>
    <div class="glass-card" style="border-left:4px solid var(--success);margin-bottom:1.5rem;">
        <h3 style="color:var(--success);margin-bottom:0.4rem;">Thank you!</h3>
        <p class="mb-0">Your request for document/s has been successfully submitted. A confirmation will be sent to you once it has been processed within 2–3 working days.</p>
    </div>
    <?php elseif ($survey_ok): ?>
    <div class="glass-card" style="border-left:4px solid var(--success);margin-bottom:1.5rem;">
        <h3 style="color:var(--success);margin-bottom:0.4rem;">Thank you for taking the time to complete our survey!</h3>
        <p class="mb-0">Your responses are highly valued and will help us improve our products, services, and overall member experience. We appreciate your continued support and participation.</p>
    </div>
    <?php endif; ?>

    <!-- Welcome + Action Cards (Node.js style) -->
    <div style="margin-bottom:2.5rem;text-align:center;padding-top:1.5rem;">
        <h1 style="color:white;font-size:2rem;margin-bottom:0.25rem;">Welcome Back, <?php echo htmlspecialchars(explode(' ', $_SESSION['name'])[0]); ?>!</h1>
        <p style="color:rgba(255,255,255,0.75);font-size:0.95rem;margin:0;">What would you like to do today?</p>
    </div>

    <!-- 5-card action grid matching Node.js dashboard -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.25rem;margin-bottom:2.5rem;">

        <!-- Inquiry -->
        <a href="submit_ticket.php?type=INQUIRY" class="glass-card" style="display:flex;flex-direction:column;align-items:center;gap:0.85rem;padding:1.5rem;border-bottom:4px solid #3b82f6;text-decoration:none;">
            <i class="fas fa-comment-dots" style="font-size:2.5rem;color:#3b82f6;"></i>
            <div style="text-align:center;">
                <h3 style="color:#1e293b;font-size:1.1rem;margin-bottom:0.2rem;">Submit Inquiry</h3>
                <p style="font-size:0.82rem;color:#64748b;margin:0;">Have a question? We're here to help.</p>
            </div>
            <span class="btn btn-primary" style="width:100%;text-align:center;padding:0.55rem;font-size:0.9rem;margin-top:auto;">Go to Inquiry</span>
        </a>

        <!-- Suggestion -->
        <a href="submit_ticket.php?type=SUGGESTION" class="glass-card" style="display:flex;flex-direction:column;align-items:center;gap:0.85rem;padding:1.5rem;border-bottom:4px solid #6366f1;text-decoration:none;">
            <i class="fas fa-lightbulb" style="font-size:2.5rem;color:#6366f1;"></i>
            <div style="text-align:center;">
                <h3 style="color:#1e293b;font-size:1.1rem;margin-bottom:0.2rem;">Make Suggestion</h3>
                <p style="font-size:0.82rem;color:#64748b;margin:0;">Help us improve our services.</p>
            </div>
            <span class="btn btn-primary" style="width:100%;text-align:center;padding:0.55rem;font-size:0.9rem;margin-top:auto;background:linear-gradient(135deg,#6366f1,#8b5cf6);">Go to Suggestions</span>
        </a>

        <!-- Survey -->
        <a href="surveys.php" class="glass-card" style="display:flex;flex-direction:column;align-items:center;gap:0.85rem;padding:1.5rem;border-bottom:4px solid #10b981;text-decoration:none;">
            <i class="fas fa-poll" style="font-size:2.5rem;color:#10b981;"></i>
            <div style="text-align:center;">
                <h3 style="color:#1e293b;font-size:1.1rem;margin-bottom:0.2rem;">Take a Survey</h3>
                <p style="font-size:0.82rem;color:#64748b;margin:0;">Your feedback matters to us.</p>
            </div>
            <span class="btn btn-primary" style="width:100%;text-align:center;padding:0.55rem;font-size:0.9rem;margin-top:auto;background:linear-gradient(135deg,#10b981,#059669);">View Surveys</span>
        </a>

        <!-- Request -->
        <a href="submit_ticket.php?type=REQUEST" class="glass-card" style="display:flex;flex-direction:column;align-items:center;gap:0.85rem;padding:1.5rem;border-bottom:4px solid #f59e0b;text-decoration:none;">
            <i class="fas fa-file-alt" style="font-size:2.5rem;color:#f59e0b;"></i>
            <div style="text-align:center;">
                <h3 style="color:#1e293b;font-size:1.1rem;margin-bottom:0.2rem;">Submit Request</h3>
                <p style="font-size:0.82rem;color:#64748b;margin:0;">Need specific assistance?</p>
            </div>
            <span class="btn btn-primary" style="width:100%;text-align:center;padding:0.55rem;font-size:0.9rem;margin-top:auto;background:linear-gradient(135deg,#f59e0b,#d97706);">Go to Request</span>
        </a>

        <!-- Complaint -->
        <a href="submit_complaint.php" class="glass-card" style="display:flex;flex-direction:column;align-items:center;gap:0.85rem;padding:1.5rem;border-bottom:4px solid #f43f5e;border:2px solid rgba(244,63,94,0.2);border-bottom:4px solid #f43f5e;background:rgba(244,63,94,0.05);text-decoration:none;">
            <i class="fas fa-exclamation-triangle" style="font-size:2.5rem;color:#f43f5e;"></i>
            <div style="text-align:center;">
                <h3 style="color:#fb7185;font-size:1.1rem;margin-bottom:0.2rem;">File a Complaint</h3>
                <p style="font-size:0.82rem;color:#64748b;margin:0;">Report issues or staff concerns.</p>
            </div>
            <span class="btn" style="width:100%;text-align:center;padding:0.55rem;font-size:0.9rem;margin-top:auto;background:#f43f5e;color:white;border:none;">File Complaint</span>
        </a>

    </div>



</div>


</main>
</div>

<?php include '../includes/footer.php'; ?>
