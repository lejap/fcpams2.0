<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('ADMIN');

$msg = '';
$msg_type = 'success';
if (isset($_GET['msg'])) {
    $msgs = [
        'added'   => 'Option added successfully.',
        'updated' => 'Option updated successfully.',
        'deleted' => 'Option deleted.',
        'failed'  => 'Error: Could not save option. Check if the dropdown_options table exists in your database.',
        'invalid' => 'Error: Please fill in both Type and Label fields.',
    ];
    $msg = $msgs[$_GET['msg']] ?? '';
    $msg_type = in_array($_GET['msg'], ['failed','invalid']) ? 'error' : 'success';
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $type  = trim($_POST['type'] ?? '');
        $label = trim($_POST['label'] ?? '');
        if (in_array($type, ['INQUIRY','SUGGESTION','REQUEST','COMPLAINT_DETAIL','TRANSACTION_TYPE']) && $label !== '') {
            $stmt = $conn->prepare("INSERT INTO dropdown_options (type, label) VALUES (?, ?)");
            $stmt->bind_param("ss", $type, $label);
            if ($stmt->execute()) {
                header("Location: dropdowns.php?msg=added");
            } else {
                header("Location: dropdowns.php?msg=failed");
            }
        } else {
            header("Location: dropdowns.php?msg=invalid");
        }
        exit();
    } elseif ($action === 'edit') {
        $oid = (int)$_POST['id'];
        $label = trim($_POST['label'] ?? '');
        if ($label !== '') {
            $stmt = $conn->prepare("UPDATE dropdown_options SET label=? WHERE id=?");
            $stmt->bind_param("si", $label, $oid);
            $stmt->execute();
            header("Location: dropdowns.php?msg=updated");
            exit();
        }
    } elseif ($action === 'delete') {
        $oid = (int)$_POST['id'];
        $conn->query("DELETE FROM dropdown_options WHERE id=$oid");
        header("Location: dropdowns.php?msg=deleted");
        exit();
    }
}

// Fetch grouped
$all_opts = $conn->query("SELECT * FROM dropdown_options ORDER BY type, label");
$inquiry_opts = $suggestion_opts = $request_opts = $complaint_opts = $transaction_opts = [];
while ($o = $all_opts->fetch_assoc()) {
    if ($o['type'] === 'INQUIRY')    $inquiry_opts[]    = $o;
    elseif ($o['type'] === 'SUGGESTION') $suggestion_opts[] = $o;
    elseif ($o['type'] === 'REQUEST')    $request_opts[]    = $o;
    elseif ($o['type'] === 'COMPLAINT_DETAIL') $complaint_opts[] = $o;
    elseif ($o['type'] === 'TRANSACTION_TYPE') $transaction_opts[] = $o;
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
    <div style="background:<?php echo $msg_type==='error' ? 'rgba(239,68,68,0.1)' : 'rgba(16,185,129,0.1)'; ?>;border:1px solid <?php echo $msg_type==='error' ? '#ef4444' : '#10b981'; ?>;color:<?php echo $msg_type==='error' ? '#dc2626' : '#059669'; ?>;padding:1rem;border-radius:0.75rem;margin-bottom:1.5rem;font-weight:600;">
        <i class="fas <?php echo $msg_type==='error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i> <?php echo htmlspecialchars($msg); ?>
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
                    <option value="COMPLAINT_DETAIL">Complaint Details</option>
                    <option value="TRANSACTION_TYPE">Transaction Type</option>
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

    <!-- Grid for all Categories -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.5rem;">

        <!-- Inquiry -->
        <div class="glass-card" style="margin-bottom:0;display:flex;flex-direction:column;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.25rem;">
                <h3 style="color:#3b82f6;display:flex;align-items:center;gap:0.6rem;font-size:1.15rem;margin:0;">
                    <i class="fas fa-comment-dots"></i> Inquiry Categories
                </h3>
                <span style="background:#dbeafe;color:#1d4ed8;padding:0.2rem 0.6rem;border-radius:0.5rem;font-size:0.75rem;font-weight:700;white-space:nowrap;"><?php echo count($inquiry_opts); ?></span>
            </div>
            <?php if (empty($inquiry_opts)): ?>
                <p style="color:#94a3b8;font-size:0.85rem;">No options yet.</p>
            <?php else: ?>
            <ul class="opt-list">
                <?php foreach ($inquiry_opts as $o): ?>
                <li>
                    <span class="opt-label"><?php echo htmlspecialchars($o['label']); ?></span>
                    <div style="display:flex;gap:0.4rem;align-items:center;">
                        <button onclick="editOption(<?php echo $o['id']; ?>, '<?php echo addslashes($o['label']); ?>')" style="background:transparent;color:#3b82f6;border:none;cursor:pointer;font-size:0.85rem;padding:0.25rem;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this option?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $o['id']; ?>">
                            <button type="submit" style="background:transparent;color:#ef4444;border:none;cursor:pointer;font-size:0.85rem;padding:0.25rem;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'" title="Delete">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <!-- Suggestion -->
        <div class="glass-card" style="margin-bottom:0;display:flex;flex-direction:column;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.25rem;">
                <h3 style="color:#6366f1;display:flex;align-items:center;gap:0.6rem;font-size:1.15rem;margin:0;">
                    <i class="fas fa-lightbulb"></i> Suggestions/Concern
                </h3>
                <span style="background:#ede9fe;color:#5b21b6;padding:0.2rem 0.6rem;border-radius:0.5rem;font-size:0.75rem;font-weight:700;white-space:nowrap;"><?php echo count($suggestion_opts); ?></span>
            </div>
            <?php if (empty($suggestion_opts)): ?>
                <p style="color:#94a3b8;font-size:0.85rem;">No options yet.</p>
            <?php else: ?>
            <ul class="opt-list">
                <?php foreach ($suggestion_opts as $o): ?>
                <li>
                    <span class="opt-label"><?php echo htmlspecialchars($o['label']); ?></span>
                    <div style="display:flex;gap:0.4rem;align-items:center;">
                        <button onclick="editOption(<?php echo $o['id']; ?>, '<?php echo addslashes($o['label']); ?>')" style="background:transparent;color:#3b82f6;border:none;cursor:pointer;font-size:0.85rem;padding:0.25rem;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this option?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $o['id']; ?>">
                            <button type="submit" style="background:transparent;color:#ef4444;border:none;cursor:pointer;font-size:0.85rem;padding:0.25rem;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'" title="Delete">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <!-- Request -->
        <div class="glass-card" style="margin-bottom:0;display:flex;flex-direction:column;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.25rem;">
                <h3 style="color:#f59e0b;display:flex;align-items:center;gap:0.6rem;font-size:1.15rem;margin:0;">
                    <i class="fas fa-file-alt"></i> Request Categories
                </h3>
                <span style="background:#fef3c7;color:#92400e;padding:0.2rem 0.6rem;border-radius:0.5rem;font-size:0.75rem;font-weight:700;white-space:nowrap;"><?php echo count($request_opts); ?></span>
            </div>
            <?php if (empty($request_opts)): ?>
                <p style="color:#94a3b8;font-size:0.85rem;">No options yet.</p>
            <?php else: ?>
            <ul class="opt-list">
                <?php foreach ($request_opts as $o): ?>
                <li>
                    <span class="opt-label"><?php echo htmlspecialchars($o['label']); ?></span>
                    <div style="display:flex;gap:0.4rem;align-items:center;">
                        <button onclick="editOption(<?php echo $o['id']; ?>, '<?php echo addslashes($o['label']); ?>')" style="background:transparent;color:#3b82f6;border:none;cursor:pointer;font-size:0.85rem;padding:0.25rem;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this option?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $o['id']; ?>">
                            <button type="submit" style="background:transparent;color:#ef4444;border:none;cursor:pointer;font-size:0.85rem;padding:0.25rem;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'" title="Delete">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <!-- Complaint Details -->
        <div class="glass-card" style="margin-bottom:0;display:flex;flex-direction:column;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.25rem;">
                <h3 style="color:#10b981;display:flex;align-items:center;gap:0.6rem;font-size:1.15rem;margin:0;">
                    <i class="fas fa-exclamation-circle"></i> Complaint Details
                </h3>
                <span style="background:#dcfce7;color:#15803d;padding:0.2rem 0.6rem;border-radius:0.5rem;font-size:0.75rem;font-weight:700;white-space:nowrap;"><?php echo count($complaint_opts); ?></span>
            </div>
            <?php if (empty($complaint_opts)): ?>
                <p style="color:#94a3b8;font-size:0.85rem;">No options yet.</p>
            <?php else: ?>
            <ul class="opt-list">
                <?php foreach ($complaint_opts as $o): ?>
                <li>
                    <span class="opt-label"><?php echo htmlspecialchars($o['label']); ?></span>
                    <div style="display:flex;gap:0.4rem;align-items:center;">
                        <button onclick="editOption(<?php echo $o['id']; ?>, '<?php echo addslashes($o['label']); ?>')" style="background:transparent;color:#3b82f6;border:none;cursor:pointer;font-size:0.85rem;padding:0.25rem;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this option?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $o['id']; ?>">
                            <button type="submit" style="background:transparent;color:#ef4444;border:none;cursor:pointer;font-size:0.85rem;padding:0.25rem;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'" title="Delete">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <!-- Transaction Type -->
        <div class="glass-card" style="margin-bottom:0;display:flex;flex-direction:column;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.25rem;">
                <h3 style="color:#8b5cf6;display:flex;align-items:center;gap:0.6rem;font-size:1.15rem;margin:0;">
                    <i class="fas fa-exchange-alt"></i> Transaction Type
                </h3>
                <span style="background:#f3e8ff;color:#6b21a8;padding:0.2rem 0.6rem;border-radius:0.5rem;font-size:0.75rem;font-weight:700;white-space:nowrap;"><?php echo count($transaction_opts); ?></span>
            </div>
            <?php if (empty($transaction_opts)): ?>
                <p style="color:#94a3b8;font-size:0.85rem;">No options yet.</p>
            <?php else: ?>
            <ul class="opt-list">
                <?php foreach ($transaction_opts as $o): ?>
                <li>
                    <span class="opt-label"><?php echo htmlspecialchars($o['label']); ?></span>
                    <div style="display:flex;gap:0.4rem;align-items:center;">
                        <button onclick="editOption(<?php echo $o['id']; ?>, '<?php echo addslashes($o['label']); ?>')" style="background:transparent;color:#3b82f6;border:none;cursor:pointer;font-size:0.85rem;padding:0.25rem;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this option?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $o['id']; ?>">
                            <button type="submit" style="background:transparent;color:#ef4444;border:none;cursor:pointer;font-size:0.85rem;padding:0.25rem;transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'" title="Delete">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Modal Placeholder (Simplified for this UI) -->
<script>
function editOption(id, label) {
    const newLabel = prompt("Edit label:", label);
    if (newLabel && newLabel !== label) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="${id}">
            <input type="hidden" name="label" value="${newLabel}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<style>
.opt-list { list-style:none; padding:0; margin:0; flex:1; }
.opt-list li { 
    display:flex; 
    justify-content:space-between; 
    align-items:center; 
    padding:0.6rem 0.75rem; 
    border-bottom:1px solid #f1f5f9; 
    transition:background 0.2s;
}
.opt-list li:hover { background:rgba(14,131,181,0.03); }
.opt-list li:last-child { border-bottom:none; }
.opt-label { font-size:0.9rem; color:#334155; font-weight:500; }
</style>
<?php include '../includes/admin_footer.php'; ?>

