<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

// Handle guest login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['citizen_login'])) {
    session_regenerate_id(true); // Prevent Session Fixation
    $_SESSION['user_id'] = 'GUEST_' . time();
    $_SESSION['name'] = sanitize($_POST['fullName']);
    $_SESSION['phone'] = sanitize($_POST['phone']);
    $_SESSION['email'] = sanitize($_POST['email']);
    $_SESSION['branch'] = sanitize($_POST['branch']);
    $_SESSION['role'] = 'CITIZEN';
    
    header("Location: citizen/dashboard.php");
    exit();
}

// Fetch branches for the dropdown (regular branches first, then HO branches)
// Falls back gracefully if is_ho column hasn't been migrated yet on the server
$branches_regular = [];
$branches_ho = [];

try {
    $result = $conn->query("SELECT name, IF(is_ho IS NULL, 0, is_ho) AS is_ho FROM branches ORDER BY is_ho ASC, name ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['is_ho']) {
                $branches_ho[] = $row['name'];
            } else {
                $branches_regular[] = $row['name'];
            }
        }
    } else {
        throw new Exception("Query returned false");
    }
} catch (Throwable $e) {
    // Fallback: is_ho column not yet added — show all branches alphabetically as regular
    $branches_regular = [];
    $branches_ho = [];
    try {
        $fallback = $conn->query("SELECT name FROM branches ORDER BY name ASC");
        if ($fallback) {
            while ($row = $fallback->fetch_assoc()) {
                $branches_regular[] = $row['name'];
            }
        }
    } catch (Throwable $e2) {
        // Safe fallback to prevent any 500 error
    }
}

$page_title = "Welcome to FCPAMS";
include 'includes/header.php';
?>

<main style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 10; padding: 2rem 1rem;">
    <div class="glass-card animate-fade-in" style="width: 100%; max-width: 480px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);">
        <div class="text-center mb-10">
            <h1 style="font-size: 2.2rem; color: #1e293b; margin-bottom: 0.5rem; letter-spacing: -0.03em;">Welcome to FCPAMS</h1>
            <p style="font-size: 0.95rem; color: #475569; font-weight: 500;">
                Financial Consumer Protection Assistance Management System
            </p>
        </div>
        
        <form action="index.php" method="POST">
            <input type="hidden" name="citizen_login" value="1">
            
            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="fullName" class="form-input" required placeholder="John Doe">
            </div>
            
            <div class="form-group">
                <label class="form-label">Phone Number *</label>
                <input type="text" name="phone" class="form-input" required pattern="\d{11}" title="Must be exactly 11 digits" placeholder="09123456789">
            </div>
            
            <div class="form-group">
                <label class="form-label">Email Address (Optional)</label>
                <input type="email" name="email" class="form-input" placeholder="john@example.com">
            </div>
            
            <div class="form-group mb-8">
                <label class="form-label">Branch *</label>
                <select name="branch" class="form-select" required>
                    <option value="">Select a branch</option>
                    <?php if (!empty($branches_regular)): ?>
                        <optgroup label="── Branches ──">
                            <?php foreach ($branches_regular as $branch): ?>
                                <option value="<?php echo htmlspecialchars($branch); ?>"><?php echo htmlspecialchars($branch); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                    <?php if (!empty($branches_ho)): ?>
                        <optgroup label="── Head Office ──">
                            <?php foreach ($branches_ho as $branch): ?>
                                <option value="<?php echo htmlspecialchars($branch); ?>"><?php echo htmlspecialchars($branch); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
                <?php if (empty($branches_regular) && empty($branches_ho)): ?>
                    <p style="font-size: 0.8rem; color: #64748b; mt-6">No branches available. Admin must add branches.</p>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1.1rem;">
                Continue to Dashboard
            </button>
        </form>

        <div class="mt-10 text-center" style="border-top: 1px solid rgba(0,0,0,0.1); paddingTop: 1.5rem;">
            <a href="login.php" style="font-size: 0.9rem; color: #3b82f6; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-lock" style="font-size: 14px;"></i> Admin Portal
            </a>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
