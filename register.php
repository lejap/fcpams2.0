<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        // Count existing users to determine role (Node.js logic)
        $count_query = $conn->query("SELECT COUNT(*) as total FROM users");
        $row = $count_query->fetch_assoc();
        $total_admins = (int)$row['total'];
        
        $is_first = ($total_admins === 0);
        $final_role = $is_first ? 'ADMIN' : 'STAFF';
        $is_approved = $is_first ? 1 : 0;
        
        $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role, is_approved) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $name, $email, $password_hash, $final_role, $is_approved);
        
        if ($stmt->execute()) {
            $stmt->close();
            if ($is_first) {
                // First user = Admin, auto-approved, send directly to login
                header("Location: " . BASE_URL . "login.php?registered=admin");
            } else {
                header("Location: " . BASE_URL . "login.php?registered=pending");
            }
            exit();
        } else {
            $error = "Email already registered or registration failed.";
        }
        $stmt->close();
    }
}

$page_title = "Staff/Admin Registration";
include 'includes/header.php';
?>

<main style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 10; padding: 2rem 1rem;" class="fade-in">
    <div class="glass-card" style="width: 100%; max-width: 500px;">
        <h2 style="margin-bottom: 0.5rem; text-align: center;">Admin Registration</h2>
        <p style="text-align: center; margin-bottom: 2rem;">Create a new admin or staff account</p>

        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger-color); color: var(--danger-color); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; text-align: center;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success-color); color: var(--success-color); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; text-align: center;">
                <?php echo $success; ?>
            </div>
        <?php else: ?>

        <form action="register.php" method="POST">
            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" class="form-input" placeholder="John Doe" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-input" placeholder="admin@example.com" required>
            </div>

            <div class="form-group" style="position: relative;">
                <label class="form-label">Password *</label>
                <input type="password" name="password" id="reg-password" class="form-input" required style="padding-right: 40px;">
                <span onclick="togglePassword('reg-password', this)" style="position: absolute; right: 15px; top: 35px; cursor: pointer; color: #64748b;">
                    <i class="fas fa-eye"></i>
                </span>
            </div>
            <div class="form-group" style="position: relative;">
                <label class="form-label">Confirm Password *</label>
                <input type="password" name="confirm_password" id="reg-confirm" class="form-input" required style="padding-right: 40px;">
                <span onclick="togglePassword('reg-confirm', this)" style="position: absolute; right: 15px; top: 35px; cursor: pointer; color: #64748b;">
                    <i class="fas fa-eye"></i>
                </span>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 1rem;">
                Register
            </button>
        </form>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 1.5rem;">
            <a href="login.php" style="color: var(--text-secondary); text-decoration: none; font-size: 0.9rem;">Already have an account? Login here</a>
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
