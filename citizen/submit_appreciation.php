<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('CITIZEN');

$error   = '';
$success = '';

// Fetch staff list (ADMIN + STAFF users, approved only)
$staff_list = [];
$res = $conn->query("SELECT name FROM users WHERE is_approved = 1 ORDER BY name ASC");
while ($row = $res->fetch_assoc()) {
    $staff_list[] = $row['name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_name      = sanitize($_POST['staff_name'] ?? '');
    $appreciation    = sanitize($_POST['appreciation'] ?? '');

    if (empty($staff_name)) {
        $error = 'Please select a staff name.';
    } elseif (empty($appreciation)) {
        $error = 'Please write your appreciation message.';
    } elseif (mb_strlen($appreciation) > 50) {
        $error = 'Appreciation message must not exceed 50 characters.';
    } else {
        $user_name   = $_SESSION['name'];
        $user_phone  = $_SESSION['phone'];
        $user_email  = $_SESSION['email'];
        $user_branch = $_SESSION['branch'];

        $stmt = $conn->prepare("
            INSERT INTO appreciations
            (user_name, user_phone, user_email, user_branch, staff_name, appreciation)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssss", $user_name, $user_phone, $user_email, $user_branch, $staff_name, $appreciation);

        if ($stmt->execute()) {
            header("Location: dashboard.php?success=appreciation");
            exit();
        } else {
            $error = "Failed to submit appreciation. Please try again.";
        }
        $stmt->close();
    }
}

$page_title = "Commendation / Appreciation";
include '../includes/header.php';
include '../includes/citizen_sidebar.php';
?>

<div class="fade-in">
    <div style="margin-bottom:1.5rem;" class="form-top-section">
        <a href="dashboard.php" class="btn btn-outline"
            style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.35rem 0.9rem;font-size:0.85rem;margin-bottom:1rem;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1 style="color:white;font-size:1.75rem;margin-bottom:0.25rem;">COMMENDATION / APPRECIATION</h1>
        <p style="color:rgba(255,255,255,0.8);font-size:0.9rem;">
            Recognize a staff member who made a difference in your experience.
        </p>
    </div>

    <?php if ($error): ?>
    <div style="background:rgba(239,68,68,0.12);border:1px solid #ef4444;color:#ef4444;padding:1rem;border-radius:0.5rem;margin-bottom:1.5rem;">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <div class="glass-card" style="max-width:600px;">
        <!-- Member info banner -->
        <div style="background:rgba(8,145,178,0.08);border:1px solid rgba(8,145,178,0.2);border-radius:0.75rem;padding:1rem 1.25rem;margin-bottom:1.75rem;">
            <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase;font-weight:700;margin-bottom:0.6rem;letter-spacing:0.05em;">
                <i class="fas fa-user"></i> Submitted by
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:1.5rem;">
                <div>
                    <div style="font-weight:700;color:#0f172a;font-size:0.95rem;"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
                    <div style="font-size:0.78rem;color:#64748b;"><?php echo htmlspecialchars($_SESSION['branch']); ?> Branch</div>
                </div>
                <div>
                    <div style="font-size:0.78rem;color:#64748b;"><?php echo htmlspecialchars($_SESSION['phone']); ?></div>
                    <div style="font-size:0.78rem;color:#64748b;"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
                </div>
            </div>
        </div>

        <form method="POST">
            <!-- Staff Name -->
            <div class="form-group">
                <label class="form-label">
                    STAFF NAME <span style="color:#ef4444;">*</span>
                </label>
                <select name="staff_name" class="form-select" required>
                    <option value="">— Select Staff Member —</option>
                    <?php foreach ($staff_list as $sname): ?>
                        <option value="<?php echo htmlspecialchars($sname); ?>"
                            <?php echo (($_POST['staff_name'] ?? '') === $sname) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sname); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($staff_list)): ?>
                    <p style="font-size:0.8rem;color:#94a3b8;margin-top:0.3rem;">No staff listed yet.</p>
                <?php endif; ?>
            </div>

            <!-- Appreciation Message -->
            <div class="form-group">
                <label class="form-label">
                    APPRECIATION <span style="color:#ef4444;">*</span>
                    <span style="font-size:0.75rem;font-weight:500;color:#64748b;">(max 50 characters)</span>
                </label>
                <textarea
                    name="appreciation"
                    class="form-textarea"
                    rows="3"
                    maxlength="50"
                    id="appreciation-field"
                    required
                    placeholder="e.g. Thank you for your outstanding service!"
                    oninput="updateCharCount(this)"
                ><?php echo htmlspecialchars($_POST['appreciation'] ?? ''); ?></textarea>
                <div style="display:flex;justify-content:flex-end;margin-top:0.35rem;">
                    <span id="char-count" style="font-size:0.78rem;color:#64748b;font-weight:600;">
                        <span id="char-num"><?php echo mb_strlen($_POST['appreciation'] ?? ''); ?></span>/50
                    </span>
                </div>
            </div>

            <!-- Submit -->
            <div style="background:rgba(8,145,178,0.06);border:1px solid rgba(8,145,178,0.18);border-radius:0.75rem;padding:0.85rem 1rem;margin-bottom:1.5rem;">
                <p style="font-size:0.84rem;color:#0e7490;margin:0;">
                    <i class="fas fa-star" style="color:#f59e0b;"></i>
                    <strong>Note:</strong> Your commendation will be reviewed by the cooperative and the mentioned staff member will be recognized accordingly.
                </p>
            </div>

            <button type="submit" class="btn"
                style="width:100%;padding:0.9rem;font-size:1rem;background:linear-gradient(135deg,#0891b2,#0e7490);color:white;border:none;">
                <i class="fas fa-paper-plane"></i> Submit Appreciation
            </button>
        </form>
    </div>
</div>

<script>
function updateCharCount(el) {
    var len = [...el.value].length; // proper Unicode counting
    if (len > 50) {
        el.value = [...el.value].slice(0, 50).join('');
        len = 50;
    }
    document.getElementById('char-num').textContent = len;
    document.getElementById('char-count').style.color = len >= 45 ? '#ef4444' : '#64748b';
}
</script>

</main>
</div>

<?php include '../includes/footer.php'; ?>
