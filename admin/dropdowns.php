<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('ADMIN');

$msg = '';
if (isset($_GET['msg'])) {
    $msgs = ['added'=>'Option added successfully.','deleted'=>'Option deleted.'];
    $msg  = $msgs[$_GET['msg']] ?? '';
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $type  = sanitize($_POST['type']);
        $label = sanitize($_POST['label']);
        if (in_array($type, ['INQUIRY','SUGGESTION','REQUEST']) && $label) {
            $stmt = $conn->prepare("INSERT INTO dropdown_options (type, label) VALUES (?, ?)");
            $stmt->bind_param("ss", $type, $label);
            $stmt->execute();
        }
        header("Location: dropdowns.php?msg=added");
        exit();
    } elseif ($action === 'delete') {
        $oid = (int)$_POST['id'];
        $conn->query("DELETE FROM dropdown_options WHERE id=$oid");
        header("Location: dropdowns.php?msg=deleted");
        exit();
    }
}

// Fetch grouped
$all_opts = $conn->query("SELECT * FROM dropdown_options ORDER BY type, label");
$inquiry_opts = $suggestion_opts = $request_opts = [];
while ($o = $all_opts->fetch_assoc()) {
    if ($o['type'] === 'INQUIRY')    $inquiry_opts[]    = $o;
    elseif ($o['type'] === 'SUGGESTION') $suggestion_opts[] = $o;
    else                             $request_opts[]    = $o;
}

$page_title = "Manage Categories";
include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<div class="fade-in">
    <div style="margin-bottom:1.5rem;">
        <h1 style="font-size:1.75rem;color:#0e83b5;margin-bottom:0.25rem;">Manage Categories</h1>
        <p style="color:#64748b;font-size:0.9rem;">Control dropdown options citizens see when submitting inquiries, suggestions, or requests.</p>
    </div>

    <?php if ($msg): ?>
    <div style="background:rgba(16,185,129,0.1);border:1px solid #10b981;color:#059669;padding:1rem;border-radius:0.75rem;margin-bottom:1.5rem;font-weight:600;">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <!-- Add Option Card -->
    <div class="glass-card">
        <h3 style="margin-bottom:1rem;color:#1e293b;">Add New Option</h3>
        <form method="POST" style="display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap;">
            <input type="hidden" name="action" value="add">
            <div style="min-width:160px;">
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#64748b;margin-bottom:0.3rem;text-transform:uppercase;">Type</label>
                <select name="type" style="padding:0.6rem 0.9rem;border:1px solid #e2e8f0;border-radius:0.5rem;font-size:0.9rem;background:white;color:#1e293b;" required>
                    <option value="INQUIRY">Inquiry Category</option>
                    <option value="SUGGESTION">Suggestions/Concern</option>
                    <option value="REQUEST">Request Category</option>
                </select>
            </div>
            <div style="flex:1;min-width:200px;">
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#64748b;margin-bottom:0.3rem;text-transform:uppercase;">Label</label>
                <input type="text" name="label" required placeholder="e.g. Technical Support" style="width:100%;padding:0.6rem 0.9rem;border:1px solid #e2e8f0;border-radius:0.5rem;font-size:0.9rem;">
            </div>
            <button type="submit" class="btn btn-primary" style="white-space:nowrap;">
                <i class="fas fa-plus"></i> Add Option
            </button>
        </form>
    </div>

    <!-- Three Columns -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.25rem;">

        <!-- Inquiry -->
        <div class="glass-card" style="margin-bottom:0;">
            <h3 style="margin-bottom:1rem;color:#3b82f6;display:flex;align-items:center;gap:0.5rem;">
                <i class="fas fa-comment-dots"></i> Inquiry Categories
                <span style="background:#dbeafe;color:#1d4ed8;padding:0.1rem 0.5rem;border-radius:0.5rem;font-size:0.75rem;margin-left:auto;"><?php echo count($inquiry_opts); ?></span>
            </h3>
            <?php if (empty($inquiry_opts)): ?>
                <p style="color:#94a3b8;font-size:0.85rem;">No options yet.</p>
            <?php else: ?>
            <ul class="opt-list">
                <?php foreach ($inquiry_opts as $o): ?>
                <li>
                    <span class="opt-label"><?php echo htmlspecialchars($o['label']); ?></span>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this option?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $o['id']; ?>">
                        <button type="submit" style="background:transparent;color:#ef4444;border:none;cursor:pointer;font-size:0.8rem;padding:0.2rem 0.4rem;" title="Delete">
                            <i class="fas fa-times"></i>
                        </button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <!-- Suggestion -->
        <div class="glass-card" style="margin-bottom:0;">
            <h3 style="margin-bottom:1rem;color:#6366f1;display:flex;align-items:center;gap:0.5rem;">
                <i class="fas fa-lightbulb"></i> Suggestions/Concern
                <span style="background:#ede9fe;color:#5b21b6;padding:0.1rem 0.5rem;border-radius:0.5rem;font-size:0.75rem;margin-left:auto;"><?php echo count($suggestion_opts); ?></span>
            </h3>
            <?php if (empty($suggestion_opts)): ?>
                <p style="color:#94a3b8;font-size:0.85rem;">No options yet.</p>
            <?php else: ?>
            <ul class="opt-list">
                <?php foreach ($suggestion_opts as $o): ?>
                <li>
                    <span class="opt-label"><?php echo htmlspecialchars($o['label']); ?></span>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this option?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $o['id']; ?>">
                        <button type="submit" style="background:transparent;color:#ef4444;border:none;cursor:pointer;font-size:0.8rem;padding:0.2rem 0.4rem;" title="Delete">
                            <i class="fas fa-times"></i>
                        </button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <!-- Request -->
        <div class="glass-card" style="margin-bottom:0;">
            <h3 style="margin-bottom:1rem;color:#f59e0b;display:flex;align-items:center;gap:0.5rem;">
                <i class="fas fa-file-alt"></i> Request Categories
                <span style="background:#fef3c7;color:#92400e;padding:0.1rem 0.5rem;border-radius:0.5rem;font-size:0.75rem;margin-left:auto;"><?php echo count($request_opts); ?></span>
            </h3>
            <?php if (empty($request_opts)): ?>
                <p style="color:#94a3b8;font-size:0.85rem;">No options yet.</p>
            <?php else: ?>
            <ul class="opt-list">
                <?php foreach ($request_opts as $o): ?>
                <li>
                    <span class="opt-label"><?php echo htmlspecialchars($o['label']); ?></span>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this option?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $o['id']; ?>">
                        <button type="submit" style="background:transparent;color:#ef4444;border:none;cursor:pointer;font-size:0.8rem;padding:0.2rem 0.4rem;" title="Delete">
                            <i class="fas fa-times"></i>
                        </button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

    </div>
</div>
<?php include '../includes/admin_footer.php'; ?>

