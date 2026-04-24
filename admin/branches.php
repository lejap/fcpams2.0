<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('ADMIN');

$msg = '';
if (isset($_GET['msg'])) {
    $msgs = ['added'=>'Branch added successfully.','deleted'=>'Branch deleted.'];
    $msg  = $msgs[$_GET['msg']] ?? '';
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = sanitize($_POST['name']);
        if ($name) {
            $stmt = $conn->prepare("INSERT INTO branches (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            $stmt->execute();
        }
        header("Location: branches.php?msg=added");
        exit();
    } elseif ($action === 'delete') {
        $bid = (int)$_POST['id'];
        $conn->query("DELETE FROM branches WHERE id=$bid");
        header("Location: branches.php?msg=deleted");
        exit();
    }
}

$branches = $conn->query("SELECT * FROM branches ORDER BY name");

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
            <input type="hidden" name="action" value="add">
            <div style="flex:1;min-width:200px;">
                <label style="display:block;font-size:0.8rem;font-weight:600;color:#64748b;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.05em;">Branch Name</label>
                <input type="text" name="name" class="form-input" required placeholder="e.g. Bansalan Branch" style="width:100%;padding:0.6rem 0.9rem;border:1px solid #e2e8f0;border-radius:0.5rem;font-size:0.9rem;">
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
                        <i class="fas fa-map-marker-alt" style="color:#0e83b5;margin-right:0.5rem;"></i>
                        <?php echo htmlspecialchars($b['name']); ?>
                    </td>
                    <td style="font-size:0.8rem;color:#64748b;"><?php echo date('M d, Y', strtotime($b['created_at'])); ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete branch \'<?php echo htmlspecialchars(addslashes($b['name'])); ?>\'? Users assigned to this branch will be unassigned.');">
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

