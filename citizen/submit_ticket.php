<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('CITIZEN');

$error = '';
$success = '';

$type = isset($_GET['type']) ? strtoupper(sanitize($_GET['type'])) : 'INQUIRY';
if (!in_array($type, ['INQUIRY', 'SUGGESTION', 'REQUEST'])) {
    $type = 'INQUIRY';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_type = sanitize($_POST['type']);
    $option_id = isset($_POST['option_id']) && !empty($_POST['option_id']) ? intval($_POST['option_id']) : null;
    $message = sanitize($_POST['message']);
    
    $ref_no = generate_ref_no($post_type);
    
    $stmt = $conn->prepare("INSERT INTO tickets (ref_no, user_name, user_phone, user_email, user_branch, type, option_id, message) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $user_name = $_SESSION['name'];
    $user_phone = $_SESSION['phone'];
    $user_email = $_SESSION['email'];
    $user_branch = $_SESSION['branch'];
    
    $stmt->bind_param("ssssssis", $ref_no, $user_name, $user_phone, $user_email, $user_branch, $post_type, $option_id, $message);
    
    if ($stmt->execute()) {
        $stmt->close();
        // PRG: redirect to prevent re-submission on refresh
        $type_lower = strtolower($post_type);
        header("Location: " . BASE_URL . "citizen/dashboard.php?success=" . urlencode($type_lower));
        exit();
    } else {
        $error = "Failed to submit ticket. Please try again.";
        $stmt->close();
    }
}

// Fetch categories for dropdown based on type
$options = [];
$stmt = $conn->prepare("SELECT id, label FROM dropdown_options WHERE type = ? ORDER BY label ASC");
$stmt->bind_param("s", $type);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $options[] = $row;
}
$stmt->close();

$page_title = "Submit " . ucfirst(strtolower($type));
include '../includes/header.php';
include '../includes/citizen_sidebar.php';
?>

<div class="py-2 flex justify-center fade-in form-top-section">
    <div class="glass-card" style="width: 100%; max-width: 600px;">
        <div class="mb-6 flex items-center justify-between">
            <h2>Submit <?php echo $type === 'INQUIRY' ? 'an Inquiry' : ($type === 'SUGGESTION' ? 'a Suggestion' : 'a Request'); ?></h2>
        </div>
        
        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--error); color: var(--error); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form action="submit_ticket.php?type=<?php echo urlencode($type); ?>" method="POST">
            <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
            
            <div class="form-group">
                <label class="form-label">Category</label>
                <select name="option_id" class="form-select" required>
                    <option value="">Select a category</option>
                    <?php foreach ($options as $opt): ?>
                        <option value="<?php echo $opt['id']; ?>"><?php echo htmlspecialchars($opt['label']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($options)): ?>
                    <p style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.25rem;">No categories available.</p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Your Message</label>
                <textarea 
                    name="message" 
                    class="form-textarea" 
                    rows="6"
                    required 
                    placeholder="Please describe your <?php echo strtolower($type); ?> in detail..."
                ></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                Submit <?php echo ucfirst(strtolower($type)); ?>
            </button>
        </form>
    </div>
</div>


</main>
</div>

<?php include '../includes/footer.php'; ?>
