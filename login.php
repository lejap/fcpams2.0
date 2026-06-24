<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id']) && $_SESSION['role'] !== 'CITIZEN') {
    if ($_SESSION['role'] === 'ADMIN') header("Location: admin/dashboard.php");
    elseif ($_SESSION['role'] === 'STAFF') header("Location: staff/dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, password_hash, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true); // Prevent Session Fixation
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'ADMIN') header("Location: admin/dashboard.php");
            elseif ($user['role'] === 'STAFF') header("Location: staff/dashboard.php");
            else header("Location: citizen/dashboard.php");
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "User not found.";
    }
    $stmt->close();
}

$page_title = "Admin Login";
include 'includes/header.php';
?>

<main style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 10; padding: 2rem 1rem;">
    <div class="glass-card animate-fade-in" style="width: 100%; max-width: 480px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); border: 1px solid rgba(255, 255, 255, 0.3);">
        <div class="text-center mb-8">
            <h1 style="font-size: 2rem; color: #1e293b; marginBottom: 0.25rem; letterSpacing: -0.02em;">FCPAMS Admin</h1>
            <p style="font-size: 0.9rem; color: #475569; font-weight: 500;">
                Financial Consumer Protection Assistance Management System
            </p>
        </div>
        
        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--error); color: var(--error); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['registered'])): ?>
            <?php if ($_GET['registered'] === 'admin'): ?>
            <div style="background:rgba(16,185,129,0.1);border:1px solid #10b981;color:#059669;padding:1rem;border-radius:8px;margin-bottom:1.5rem;font-weight:600;text-align:center;">
                <i class="fas fa-check-circle"></i> Admin account created! You may log in now.
            </div>
            <?php else: ?>
            <div style="background:rgba(245,158,11,0.1);border:1px solid #f59e0b;color:#92400e;padding:1rem;border-radius:8px;margin-bottom:1.5rem;font-weight:600;text-align:center;">
                <i class="fas fa-clock"></i> Registration successful! Your account is pending approval by an Admin.
            </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <form action="login.php" method="POST">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-input" required placeholder="admin@example.com" style="background: rgba(255,255,255,0.5);">
            </div>
            
            <div class="form-group" style="position: relative;">
                <label class="form-label">Password</label>
                <input type="password" name="password" id="login-password" class="form-input" required placeholder="••••••••" style="background: rgba(255,255,255,0.5); padding-right: 40px;">
                <span onclick="togglePassword('login-password', this)" style="position: absolute; right: 15px; top: 40px; cursor: pointer; color: #64748b;">
                    <i class="fas fa-eye"></i>
                </span>
            </div>
            
            <button type="submit" class="btn btn-primary mt-6" style="width: 100%; padding: 1rem; font-size: 1.1rem;">
                Login to Dashboard
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="register.php" style="background: none; border: none; color: #3b82f6; cursor: pointer; font-size: 0.95rem; font-weight: 600; text-decoration: none;">
                Need an account? Register
            </a>
        </div>

        <div class="mt-8 text-center pt-6" style="border-top: 1px solid rgba(0,0,0,0.1);">
            <a href="index.php" style="font-size: 0.9rem; color: #64748b; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem; justify-content: center; width: 100%;">
                <i class="fas fa-arrow-left" style="font-size: 12px;"></i> Back to User Portal
            </a>
        </div>
    </div>
</main>

<script>
function togglePassword(inputId, iconSpan) {
    const input = document.getElementById(inputId);
    const icon = iconSpan.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
