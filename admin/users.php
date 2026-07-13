<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('ADMIN');
validate_csrf();

$current_user = $conn->query("SELECT * FROM users WHERE id={$_SESSION['user_id']}")->fetch_assoc();
$msg = '';
if (isset($_GET['msg'])) {
    $msgs = ['approved'=>'User approved successfully.','deleted'=>'User deleted.','role'=>'Role updated.','branch'=>'Branch assignment updated.'];
    $msg  = $msgs[$_GET['msg']] ?? '';
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $uid    = (int)($_POST['id'] ?? 0);

    if ($action === 'approve' && $uid) {
        $conn->query("UPDATE users SET is_approved=TRUE WHERE id=$uid");
        header("Location: users.php?msg=approved"); exit();
    } elseif ($action === 'delete' && $uid && $uid !== $_SESSION['user_id']) {
        $conn->query("DELETE FROM users WHERE id=$uid");
        header("Location: users.php?msg=deleted"); exit();
    } elseif ($action === 'role' && $uid) {
        $new_role = sanitize($_POST['role']);
        if (in_array($new_role, ['ADMIN','STAFF']) && $uid !== $_SESSION['user_id']) {
            $conn->query("UPDATE users SET role='$new_role' WHERE id=$uid");
        }
        header("Location: users.php?msg=role"); exit();
    } elseif ($action === 'branch' && $uid) {
        $new_branch = $_POST['branch_id'];
        if ($new_branch === '' || $new_branch === null) {
            $conn->query("UPDATE users SET branch_id=NULL WHERE id=$uid");
        } else {
            $new_branch = (int)$new_branch;
            $stmt = $conn->prepare("UPDATE users SET branch_id=? WHERE id=?");
            $stmt->bind_param("ii", $new_branch, $uid);
            $stmt->execute();
        }
        header("Location: users.php?msg=branch"); exit();
    }
}

// Fetch all users with branch info
$users = $conn->query("SELECT u.*, b.name AS branch_name, COALESCE(b.is_ho, 0) AS is_ho
                        FROM users u
                        LEFT JOIN branches b ON u.branch_id = b.id
                        ORDER BY u.is_approved ASC, u.id DESC");

// Fetch branches for dropdown
$branches = $conn->query("SELECT id, name, is_ho FROM branches ORDER BY is_ho DESC, name ASC");
$branch_list = [];
while ($br = $branches->fetch_assoc()) {
    $branch_list[] = $br;
}

$page_title = "Manage Users";
include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<div class="fade-in">
    <div style="margin-bottom:1.5rem;">
        <h1 style="font-size:1.75rem;color:#0e83b5;margin-bottom:0.25rem;">Manage Users</h1>
        <p style="color:#64748b;font-size:0.9rem;">Approve, manage roles, assign branches, or remove Staff/Admin accounts.</p>
    </div>

    <?php if ($msg): ?>
    <div style="background:rgba(16,185,129,0.1);border:1px solid #10b981;color:#059669;padding:1rem;border-radius:0.75rem;margin-bottom:1.5rem;font-weight:600;">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <div class="admin-table-wrapper">
        <div class="admin-table-header">
            <i class="fas fa-users"></i> Admin &amp; Staff Accounts
        </div>
        <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Branch</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($users->num_rows > 0): ?>
                <?php while ($u = $users->fetch_assoc()): ?>
                <tr>
                    <td style="font-weight:700;color:#64748b;">#<?php echo $u['id']; ?></td>
                    <td style="font-weight:600;"><?php echo htmlspecialchars($u['name']); ?></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td>
                        <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                        <form method="POST" style="display:inline-flex;gap:0.4rem;align-items:center;"
                              onsubmit="return confirmRoleChange(this)">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id"     value="<?php echo $u['id']; ?>">
                            <input type="hidden" name="action" value="role">
                            <input type="hidden" name="user_name_label" value="<?php echo htmlspecialchars($u['name']); ?>">
                            <select name="role"
                                    style="padding:0.25rem 0.5rem;border:1px solid #cbd5e1;border-radius:0.4rem;font-size:0.8rem;background:white;color:#1e293b;font-weight:600;cursor:pointer;">
                                <option value="STAFF" <?php echo $u['role']==='STAFF'?'selected':''; ?>>STAFF</option>
                                <option value="ADMIN" <?php echo $u['role']==='ADMIN'?'selected':''; ?>>ADMIN</option>
                            </select>
                            <button type="submit"
                                    style="background:#0e83b5;color:white;border:none;border-radius:0.4rem;padding:0.25rem 0.65rem;font-size:0.78rem;cursor:pointer;font-weight:700;white-space:nowrap;">
                                <i class="fas fa-save"></i> Save
                            </button>
                        </form>
                        <?php else: ?>
                            <span style="background:#dbeafe;color:#1d4ed8;padding:0.2rem 0.6rem;border-radius:0.3rem;font-size:0.78rem;font-weight:700;"><?php echo $u['role']; ?> (You)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" style="display:inline-flex;gap:0.4rem;align-items:center;"
                              onsubmit="return confirmBranchChange(this)">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id"     value="<?php echo $u['id']; ?>">
                            <input type="hidden" name="action" value="branch">
                            <input type="hidden" name="user_name_label" value="<?php echo htmlspecialchars($u['name']); ?>">
                            <select name="branch_id"
                                    style="padding:0.25rem 0.5rem;border:1px solid #cbd5e1;border-radius:0.4rem;font-size:0.78rem;background:white;color:#1e293b;font-weight:600;cursor:pointer;max-width:160px;">
                                <option value="">-- None --</option>
                                <?php foreach ($branch_list as $br): ?>
                                <option value="<?php echo $br['id']; ?>" <?php echo ((int)$u['branch_id'] === (int)$br['id'])?'selected':''; ?>>
                                    <?php echo htmlspecialchars($br['name']); ?><?php echo $br['is_ho'] ? ' (HO)' : ''; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit"
                                    style="background:#7c3aed;color:white;border:none;border-radius:0.4rem;padding:0.25rem 0.65rem;font-size:0.78rem;cursor:pointer;font-weight:700;white-space:nowrap;">
                                <i class="fas fa-map-marker-alt"></i> Assign
                            </button>
                        </form>
                        <?php if (!empty($u['branch_name'])): ?>
                        <div style="margin-top:0.25rem;">
                            <?php if ($u['is_ho']): ?>
                            <span style="background:rgba(124,58,237,0.1);color:#7c3aed;border:1px solid rgba(124,58,237,0.3);border-radius:1rem;padding:0.15rem 0.5rem;font-size:0.7rem;font-weight:700;">
                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($u['branch_name']); ?>
                            </span>
                            <?php else: ?>
                            <span style="background:rgba(14,131,181,0.1);color:#0e83b5;border:1px solid rgba(14,131,181,0.25);border-radius:1rem;padding:0.15rem 0.5rem;font-size:0.7rem;font-weight:700;">
                                <i class="fas fa-code-branch"></i> <?php echo htmlspecialchars($u['branch_name']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div style="margin-top:0.25rem;">
                            <span style="color:#94a3b8;font-size:0.72rem;font-style:italic;">No branch assigned</span>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['is_approved']): ?>
                            <span style="color:#10b981;font-weight:700;">✓ Approved</span>
                        <?php else: ?>
                            <span style="color:#eab308;font-weight:700;">⏳ Pending</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.8rem;color:#64748b;"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                    <td>
                        <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                            <?php if (!$u['is_approved']): ?>
                            <form method="POST" style="display:inline;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" style="background:#10b981;color:white;border:none;border-radius:0.4rem;padding:0.25rem 0.65rem;font-size:0.8rem;cursor:pointer;font-weight:600;">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this user? This cannot be undone.');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" style="background:transparent;color:#ef4444;border:1px solid #ef4444;border-radius:0.4rem;padding:0.25rem 0.65rem;font-size:0.8rem;cursor:pointer;font-weight:600;">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8" style="text-align:center;padding:2rem;color:#94a3b8;">No users found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<script>
function confirmRoleChange(form) {
    var select   = form.querySelector('select[name="role"]');
    var newRole  = select.value;
    var userName = form.querySelector('input[name="user_name_label"]').value;
    return confirm('Change role of "' + userName + '" to ' + newRole + '?\n\nThis will immediately update their access level.');
}
function confirmBranchChange(form) {
    var select     = form.querySelector('select[name="branch_id"]');
    var branchName = select.options[select.selectedIndex].text.trim();
    var userName   = form.querySelector('input[name="user_name_label"]').value;
    if (select.value === '') {
        return confirm('Remove branch assignment from "' + userName + '"?');
    }
    return confirm('Assign "' + userName + '" to branch "' + branchName + '"?');
}
</script>
<?php include '../includes/admin_footer.php'; ?>
