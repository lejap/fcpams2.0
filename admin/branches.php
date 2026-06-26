<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('ADMIN');
validate_csrf();

$msg = '';
if (isset($_GET['msg'])) {
    $msgs = ['added'=>'Branch added successfully.','deleted'=>'Branch deleted.','updated'=>'Branch updated.'];
    $msg  = $msgs[$_GET['msg']] ?? '';
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name  = sanitize($_POST['name']);
        $is_ho = isset($_POST['is_ho']) ? 1 : 0;
        if ($name) {
            $stmt = $conn->prepare("INSERT INTO branches (name, is_ho) VALUES (?, ?)");
            $stmt->bind_param("si", $name, $is_ho);
            $stmt->execute();
        }
        header("Location: branches.php?msg=added");
        exit();
    } elseif ($action === 'toggle_ho') {
        $bid = (int)$_POST['id'];
        $conn->query("UPDATE branches SET is_ho = IF(is_ho, 0, 1) WHERE id=$bid");
        header("Location: branches.php?msg=updated");
        exit();
    } elseif ($action === 'delete') {
        $bid = (int)$_POST['id'];
        $conn->query("DELETE FROM branches WHERE id=$bid");
        header("Location: branches.php?msg=deleted");
        exit();
    }
}

$branches = $conn->query("SELECT *, IF(is_ho IS NULL, 0, is_ho) AS is_ho FROM branches ORDER BY is_ho ASC, name ASC");

$page_title = "Manage Branches";
include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<div class="fade-in">
    <div style="margin-bottom:1.5rem;">
        <h1 style="font-size:1.75rem;color:#0e83b5;margin-bottom:0.25rem;">Manage Branches</h1>
        <p style="color:#64748b;font-size:0.9rem;">Add or remove cooperative branches available system-wide.</p>
    </div>

    <?php if ($msg): ?>
    <div style="background:rgba(16,185,129,0.1);border:1px solid #10b981;color:#059669;padding:1rem;border-radius:0.75rem;margin-bottom:1.5rem;font-weight:600;">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <!-- Add Branch Card -->
    <div class="glass-card" style="margin-bottom:1.5rem;">
        <h3 style="margin-bottom:1rem;color:#1e293b;">Add New Branch</h3>
        <form method="POST" style="display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add">
            <div style="flex:1;min-width:200px;">
                <label style="display:block;font-size:0.8rem;font-weight:600;color:#64748b;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.05em;">Branch Name</label>
                <input type="text" name="name" class="form-input" required placeholder="e.g. Bansalan Branch" style="width:100%;padding:0.6rem 0.9rem;border:1px solid #e2e8f0;border-radius:0.5rem;font-size:0.9rem;">
            </div>
            <div style="display:flex;align-items:center;gap:0.4rem;white-space:nowrap;">
                <input type="checkbox" name="is_ho" id="add_is_ho" value="1" style="width:16px;height:16px;accent-color:#0e83b5;cursor:pointer;">
                <label for="add_is_ho" style="font-size:0.85rem;font-weight:600;color:#64748b;cursor:pointer;">HO Branch</label>
            </div>
            <button type="submit" class="btn btn-primary" style="white-space:nowrap;">
                <i class="fas fa-plus"></i> Add Branch
            </button>
        </form>
    </div>

    <!-- Branches List -->
    <div class="admin-table-wrapper">
        <div class="admin-table-header">
            <i class="fas fa-code-branch"></i> Existing Branches (<?php echo $branches->num_rows; ?>)
        </div>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Branch Name</th>
                    <th>Type</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($branches->num_rows > 0): ?>
                <?php while ($b = $branches->fetch_assoc()): ?>
                <tr>
                    <td style="color:#94a3b8;font-weight:700;"><?php echo $b['id']; ?></td>
                    <td style="font-weight:600;">
                        <i class="fas fa-map-marker-alt" style="color:<?php echo $b['is_ho'] ? '#7c3aed' : '#0e83b5'; ?>;margin-right:0.5rem;"></i>
                        <?php echo htmlspecialchars($b['name']); ?>
                    </td>
                    <td>
                        <?php if ($b['is_ho']): ?>
                            <span style="background:rgba(124,58,237,0.1);color:#7c3aed;border:1px solid rgba(124,58,237,0.3);border-radius:1rem;padding:0.2rem 0.65rem;font-size:0.75rem;font-weight:700;">
                                <i class="fas fa-building"></i> Head Office
                            </span>
                        <?php else: ?>
                            <span style="background:rgba(14,131,181,0.1);color:#0e83b5;border:1px solid rgba(14,131,181,0.25);border-radius:1rem;padding:0.2rem 0.65rem;font-size:0.75rem;font-weight:700;">
                                <i class="fas fa-code-branch"></i> Branch
                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.8rem;color:#64748b;"><?php echo date('M d, Y', strtotime($b['created_at'])); ?></td>
                    <td style="display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap;">
                        <form method="POST" style="display:inline;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle_ho">
                            <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                            <button type="submit" title="<?php echo $b['is_ho'] ? 'Mark as Branch' : 'Mark as HO'; ?>" style="background:<?php echo $b['is_ho'] ? 'rgba(124,58,237,0.1)' : 'rgba(14,131,181,0.08)'; ?>;color:<?php echo $b['is_ho'] ? '#7c3aed' : '#0e83b5'; ?>;border:1px solid <?php echo $b['is_ho'] ? 'rgba(124,58,237,0.3)' : 'rgba(14,131,181,0.25)'; ?>;border-radius:0.4rem;padding:0.25rem 0.65rem;font-size:0.8rem;cursor:pointer;font-weight:600;">
                                <i class="fas fa-exchange-alt"></i> <?php echo $b['is_ho'] ? 'Set as Branch' : 'Set as HO'; ?>
                            </button>
                        </form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete branch \'<?php echo htmlspecialchars(addslashes($b['name'])); ?>\'? Users assigned to this branch will be unassigned.');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                            <button type="submit" style="background:transparent;color:#ef4444;border:1px solid #ef4444;border-radius:0.4rem;padding:0.25rem 0.65rem;font-size:0.8rem;cursor:pointer;font-weight:600;">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" style="text-align:center;padding:2rem;color:#94a3b8;">No branches yet. Add one above.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/admin_footer.php'; ?>

