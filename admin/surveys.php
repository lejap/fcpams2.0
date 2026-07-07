<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('ADMIN');

$msg = '';
if (isset($_GET['msg'])) {
    $msgs = ['created'=>'Survey created successfully.','toggled'=>'Survey status updated.','deleted'=>'Survey deleted.','q_added'=>'Question added.','q_deleted'=>'Question deleted.','q_updated'=>'Question updated.','q_error'=>'Error: Could not save question. Check if the questions table type column includes MULTI_SELECT (ALTER TABLE needed).','title_updated'=>'Survey updated successfully.'];
    $msg  = $msgs[$_GET['msg']] ?? '';
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title       = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        if ($title) {
            $stmt = $conn->prepare("INSERT INTO surveys (title, description, is_active) VALUES (?, ?, 0)");
            $stmt->bind_param("ss", $title, $description);
            $stmt->execute();
        }
        header("Location: surveys.php?msg=created");
        exit();
    } elseif ($action === 'toggle') {
        $sid = (int)$_POST['id'];
        $conn->query("UPDATE surveys SET is_active = NOT is_active WHERE id=$sid");
        header("Location: surveys.php?msg=toggled");
        exit();
    } elseif ($action === 'delete') {
        $sid = (int)$_POST['id'];
        $conn->query("DELETE FROM surveys WHERE id=$sid");
        header("Location: surveys.php?msg=deleted");
        exit();
    } elseif ($action === 'add_question') {
        $sid      = (int)$_POST['survey_id'];
        $text     = sanitize($_POST['text']);
        $type     = sanitize($_POST['type']);
        $raw_opts = $_POST['options'] ?? '';
        if ($type === 'CHOICE' && $raw_opts !== '') {
            $parts   = array_filter(array_map('trim', explode(',', trim($raw_opts))));
            $options = implode(',', $parts);
        } elseif ($type === 'MULTI_SELECT' && $raw_opts !== '') {
            $max_sel = max(1, (int)($_POST['max_select'] ?? 2));
            $parts   = array_filter(array_map('trim', explode(',', trim($raw_opts))));
            $options = 'MAX:' . $max_sel . '|' . implode(',', $parts);
        } else {
            $options = '';
        }
        if ($sid && $text && in_array($type, ['TEXT','CHOICE','RATING','MULTI_SELECT'])) {
            $stmt = $conn->prepare("INSERT INTO questions (survey_id, text, type, options) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $sid, $text, $type, $options);
            if ($stmt->execute()) {
                header("Location: surveys.php?view=$sid&msg=q_added");
            } else {
                header("Location: surveys.php?view=$sid&msg=q_error");
            }
        } else {
            header("Location: surveys.php?view=$sid&msg=q_error");
        }
        exit();
    } elseif ($action === 'delete_question') {
        $qid = (int)$_POST['id'];
        $sid = (int)$_POST['survey_id'];
        $conn->query("DELETE FROM questions WHERE id=$qid");
        header("Location: surveys.php?view=$sid&msg=q_deleted");
        exit();
    } elseif ($action === 'edit_question') {
        $qid      = (int)$_POST['id'];
        $sid      = (int)$_POST['survey_id'];
        $text     = sanitize($_POST['text']);
        $type     = sanitize($_POST['type']);
        $raw_opts = $_POST['options'] ?? '';
        if ($type === 'CHOICE' && $raw_opts !== '') {
            $parts   = array_filter(array_map('trim', explode(',', trim($raw_opts))));
            $options = implode(',', $parts);
        } elseif ($type === 'MULTI_SELECT' && $raw_opts !== '') {
            $max_sel = max(1, (int)($_POST['max_select'] ?? 2));
            $parts   = array_filter(array_map('trim', explode(',', trim($raw_opts))));
            $options = 'MAX:' . $max_sel . '|' . implode(',', $parts);
        } else {
            $options = '';
        }
        if ($qid && $text && in_array($type, ['TEXT','CHOICE','RATING','MULTI_SELECT'])) {
            $stmt = $conn->prepare("UPDATE questions SET text=?, type=?, options=? WHERE id=?");
            $stmt->bind_param("sssi", $text, $type, $options, $qid);
            if ($stmt->execute()) {
                header("Location: surveys.php?view=$sid&msg=q_updated");
            } else {
                header("Location: surveys.php?view=$sid&msg=q_error");
            }
        } else {
            header("Location: surveys.php?view=$sid&msg=q_error");
        }
        exit();
    } elseif ($action === 'edit_survey') {
        $sid   = (int)$_POST['id'];
        $title = sanitize($_POST['title']);
        $desc  = sanitize($_POST['description']);
        if ($sid && $title) {
            $stmt = $conn->prepare("UPDATE surveys SET title=?, description=? WHERE id=?");
            $stmt->bind_param("ssi", $title, $desc, $sid);
            $stmt->execute();
        }
        // Return to detail view if we came from there, otherwise list
        $back = isset($_POST['from_view']) && (int)$_POST['from_view'] ? "surveys.php?view=$sid&msg=title_updated" : "surveys.php?msg=title_updated";
        header("Location: $back");
        exit();
    }
}

// View detail mode?
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;

if ($view_id) {
    // ---- DETAIL VIEW ----
    $survey = $conn->query("SELECT * FROM surveys WHERE id=$view_id")->fetch_assoc();
    if (!$survey) { header("Location: surveys.php"); exit(); }

    $questions = $conn->query("SELECT * FROM questions WHERE survey_id=$view_id ORDER BY id ASC");
    $responses = $conn->query("SELECT * FROM survey_responses WHERE survey_id=$view_id ORDER BY created_at DESC");

    $page_title = "Manage Survey: " . htmlspecialchars($survey['title']);
    include '../includes/admin_header.php';
    include '../includes/admin_sidebar.php';
    ?>

<div class="fade-in">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
            <div>
                <h1 style="font-size:1.6rem;color:#0e83b5;margin-bottom:0.2rem;"><?php echo htmlspecialchars($survey['title']); ?></h1>
                <p style="color:#64748b;font-size:0.9rem;margin:0;"><?php echo htmlspecialchars($survey['description'] ?? ''); ?></p>
            </div>
            <button type="button"
                onclick="openEditSurveyModal(<?php echo $survey['id']; ?>, <?php echo htmlspecialchars(json_encode($survey['title'])); ?>, <?php echo htmlspecialchars(json_encode($survey['description'] ?? '')); ?>, true)"
                title="Edit survey title"
                style="background:#e0f2fe;color:#0e83b5;border:none;border-radius:0.5rem;padding:0.35rem 0.7rem;cursor:pointer;font-size:0.85rem;font-weight:600;display:flex;align-items:center;gap:0.3rem;">
                <i class="fas fa-pencil-alt"></i> Edit Title
            </button>
        </div>
        <a href="surveys.php" class="btn btn-outline" style="padding:0.5rem 1.2rem;">
            <i class="fas fa-arrow-left"></i> Back to Surveys
        </a>
    </div>

    <?php if ($msg): ?>
    <div style="background:<?php echo str_contains($msg,'Error') ? 'rgba(239,68,68,0.1)' : 'rgba(16,185,129,0.1)'; ?>;border:1px solid <?php echo str_contains($msg,'Error') ? '#ef4444' : '#10b981'; ?>;color:<?php echo str_contains($msg,'Error') ? '#dc2626' : '#059669'; ?>;padding:0.9rem 1rem;border-radius:0.75rem;margin-bottom:1.5rem;font-weight:600;">
        <i class="fas <?php echo str_contains($msg,'Error') ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i> <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1.2fr;gap:1.5rem;">
        <!-- Left: Questions -->
        <div>
            <div class="glass-card" style="margin-bottom:1.5rem;">
                <h3 style="margin-bottom:1rem;color:#1e293b;">Add Question</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_question">
                    <input type="hidden" name="survey_id" value="<?php echo $view_id; ?>">
                    <div class="form-group">
                        <label class="form-label">Question Text</label>
                        <input type="text" name="text" class="form-input" required placeholder="What do you think about...?">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select" required id="qtype" onchange="toggleAddFields(this.value)">
                            <option value="TEXT">Text Input</option>
                            <option value="CHOICE">Single Choice (Radio)</option>
                            <option value="MULTI_SELECT">Multi-Select (Checkboxes)</option>
                            <option value="RATING">Rating (Strongly Agree scale)</option>
                        </select>
                    </div>
                    <div class="form-group" id="opts_row" style="display:none;">
                        <label class="form-label">Options <small style="color:#94a3b8;">(comma-separated)</small></label>
                        <input type="text" name="options" class="form-input" placeholder="Excellent, Good, Fair, Poor">
                        <p style="font-size:0.78rem;color:#94a3b8;margin-top:0.25rem;">Separate each choice with a comma.</p>
                    </div>
                    <div class="form-group" id="max_select_row" style="display:none;">
                        <label class="form-label">Max Selections Allowed <small style="color:#94a3b8;">(how many options citizen can pick)</small></label>
                        <input type="number" name="max_select" class="form-input" min="1" value="2" style="max-width:120px;">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">
                        <i class="fas fa-plus"></i> Add Question
                    </button>
                </form>
            </div>

            <div class="glass-card">
                <h3 style="margin-bottom:1rem;color:#1e293b;">
                    Questions 
                    <span style="background:#dbeafe;color:#1d4ed8;padding:0.1rem 0.5rem;border-radius:0.5rem;font-size:0.75rem;margin-left:0.5rem;"><?php echo $questions->num_rows; ?></span>
                </h3>
                <?php if ($questions->num_rows === 0): ?>
                    <p style="color:#94a3b8;">No questions yet. Add one above.</p>
                <?php else: ?>
                <ul style="list-style:none;padding:0;margin:0;">
                    <?php $qi = 1; while ($q = $questions->fetch_assoc()): ?>
                    <li style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:0.5rem;padding:0.75rem 1rem;margin-bottom:0.75rem;display:flex;justify-content:space-between;align-items:flex-start;gap:0.5rem;">
                        <div>
                            <div style="font-weight:600;color:#1e293b;"><?php echo $qi++; ?>. <?php echo htmlspecialchars(html_entity_decode($q['text'], ENT_QUOTES | ENT_HTML5)); ?></div>
                            <div style="font-size:0.78rem;color:#94a3b8;margin-top:0.2rem;">Type: <?php echo $q['type']; ?></div>
                            <?php if ($q['type']==='CHOICE' && $q['options']):
                                $opts = array_map('trim', explode(',', html_entity_decode($q['options'], ENT_QUOTES | ENT_HTML5)));
                            ?>
                            <div style="font-size:0.78rem;color:#64748b;">Options: <?php echo htmlspecialchars(implode(' · ', $opts)); ?></div>
                            <?php elseif ($q['type']==='MULTI_SELECT' && $q['options']):
                                $decoded_opts = html_entity_decode($q['options'], ENT_QUOTES | ENT_HTML5);
                                preg_match('/^MAX:(\d+)\|(.+)$/', $decoded_opts, $ms);
                                $ms_max  = $ms[1] ?? '?';
                                $ms_opts = isset($ms[2]) ? array_map('trim', explode(',', $ms[2])) : [];
                            ?>
                            <div style="font-size:0.78rem;color:#64748b;">Options: <?php echo htmlspecialchars(implode(' · ', $ms_opts)); ?></div>
                            <div style="font-size:0.78rem;color:#6366f1;font-weight:600;">Max pick: <?php echo $ms_max; ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:0.4rem;align-items:center;">
                            <button type="button"
                                onclick="openEditModal(<?php echo $q['id']; ?>, <?php echo htmlspecialchars(json_encode(html_entity_decode($q['text'], ENT_QUOTES | ENT_HTML5))); ?>, '<?php echo $q['type']; ?>', <?php echo htmlspecialchars(json_encode(html_entity_decode($q['options'] ?? '', ENT_QUOTES | ENT_HTML5))); ?>)"
                                style="background:transparent;color:#0e83b5;border:none;cursor:pointer;font-size:0.85rem;padding:0.1rem 0.3rem;" title="Edit" id="editbtn_<?php echo $q['id']; ?>">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                            <form method="POST" onsubmit="return confirm('Delete this question?');">
                                <input type="hidden" name="action" value="delete_question">
                                <input type="hidden" name="id" value="<?php echo $q['id']; ?>">
                                <input type="hidden" name="survey_id" value="<?php echo $view_id; ?>">
                                <button type="submit" style="background:transparent;color:#ef4444;border:none;cursor:pointer;font-size:0.85rem;padding:0.1rem 0.3rem;" title="Delete">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        </div>
                    </li>
                    <?php endwhile; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Responses -->
        <div>
            <div class="admin-table-wrapper">
                <div class="admin-table-header">
                    <i class="fas fa-users"></i> Responses (<?php echo $responses->num_rows; ?>)
                </div>
                <?php if ($responses->num_rows === 0): ?>
                    <div style="padding:2rem;text-align:center;color:#94a3b8;">No responses yet.</div>
                <?php else: ?>
                <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Respondent</th>
                            <th>Branch</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($res = $responses->fetch_assoc()): ?>
                    <tr>
                        <td style="font-weight:600;"><?php echo htmlspecialchars($res['user_name']); ?></td>
                        <td>
                            <span style="background:#e2e8f0;color:#334155;padding:0.1rem 0.4rem;border-radius:0.2rem;font-size:0.75rem;font-weight:700;">
                                <?php echo htmlspecialchars($res['user_branch'] ?? '—'); ?>
                            </span>
                        </td>
                        <td style="font-size:0.8rem;"><?php echo date('M d, Y', strtotime($res['created_at'])); ?></td>
                        <td>
                            <a href="survey_response.php?id=<?php echo $res['id']; ?>" class="btn btn-outline" style="padding:0.2rem 0.7rem;font-size:0.8rem;border-radius:2rem;">
                                View Answers
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<!-- Edit Question Modal -->
<div id="editQuestionModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:1rem;padding:2rem;width:100%;max-width:500px;margin:1rem;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
            <h3 style="margin:0;color:#1e293b;font-size:1.1rem;"><i class="fas fa-pencil-alt" style="color:#0e83b5;margin-right:0.5rem;"></i>Edit Question</h3>
            <button onclick="closeEditModal()" style="background:transparent;border:none;font-size:1.3rem;cursor:pointer;color:#94a3b8;line-height:1;">&#x2715;</button>
        </div>
        <form method="POST" id="editQuestionForm">
            <input type="hidden" name="action" value="edit_question">
            <input type="hidden" name="id" id="edit_q_id">
            <input type="hidden" name="survey_id" value="<?php echo $view_id; ?>">
            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label">Question Text</label>
                <input type="text" name="text" id="edit_q_text" class="form-input" required>
            </div>
            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label">Type</label>
                <select name="type" id="edit_q_type" class="form-select" required onchange="toggleEditOpts()">
                    <option value="TEXT">Text Input</option>
                    <option value="CHOICE">Single Choice (Radio)</option>
                    <option value="MULTI_SELECT">Multi-Select (Checkboxes)</option>
                    <option value="RATING">Rating (Strongly Agree scale)</option>
                </select>
            </div>
            <div class="form-group" id="edit_opts_row" style="display:none;margin-bottom:1rem;">
                <label class="form-label">Options <small style="color:#94a3b8;">(comma-separated)</small></label>
                <input type="text" name="options" id="edit_q_options" class="form-input" placeholder="Excellent, Good, Fair, Poor">
            </div>
            <div class="form-group" id="edit_max_select_row" style="display:none;margin-bottom:1rem;">
                <label class="form-label">Max Selections Allowed</label>
                <input type="number" name="max_select" id="edit_q_max_select" class="form-input" min="1" value="2" style="max-width:120px;">
            </div>
            <div style="display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1.5rem;">
                <button type="button" onclick="closeEditModal()" style="padding:0.5rem 1.2rem;border:1px solid #e2e8f0;border-radius:0.5rem;background:#f8fafc;color:#64748b;cursor:pointer;font-weight:600;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding:0.5rem 1.4rem;"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAddFields(val) {
    document.getElementById('opts_row').style.display = (val==='CHOICE'||val==='MULTI_SELECT') ? 'block' : 'none';
    document.getElementById('max_select_row').style.display = (val==='MULTI_SELECT') ? 'block' : 'none';
}
function openEditModal(id, text, type, options) {
    var maxSel = 2, cleanOpts = options;
    if (type === 'MULTI_SELECT') {
        var m = options.match(/^MAX:(\d+)\|(.*)$/);
        if (m) { maxSel = parseInt(m[1]); cleanOpts = m[2]; }
    }
    document.getElementById('edit_q_id').value         = id;
    document.getElementById('edit_q_text').value       = text;
    document.getElementById('edit_q_type').value       = type;
    document.getElementById('edit_q_options').value    = cleanOpts;
    document.getElementById('edit_q_max_select').value = maxSel;
    toggleEditOpts();
    var modal = document.getElementById('editQuestionModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeEditModal() {
    document.getElementById('editQuestionModal').style.display = 'none';
    document.body.style.overflow = '';
}
function toggleEditOpts() {
    var type = document.getElementById('edit_q_type').value;
    document.getElementById('edit_opts_row').style.display = (type==='CHOICE'||type==='MULTI_SELECT') ? 'block' : 'none';
    document.getElementById('edit_max_select_row').style.display = (type==='MULTI_SELECT') ? 'block' : 'none';
}
document.getElementById('editQuestionModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>

<?php include '../includes/admin_footer.php'; ?>
<?php
    exit();
}

// ---- LIST VIEW ----
$surveys = $conn->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM questions WHERE survey_id=s.id) AS q_count,
           (SELECT COUNT(*) FROM survey_responses WHERE survey_id=s.id) AS r_count
    FROM surveys s ORDER BY s.created_at DESC
");

$page_title = "Manage Surveys";
include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<div class="fade-in">
    <div style="margin-bottom:1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
        <div>
            <h1 style="font-size:1.75rem;color:#0e83b5;margin-bottom:0.25rem;">Manage Surveys</h1>
            <p style="color:#64748b;font-size:0.9rem;">Create and manage citizen satisfaction surveys.</p>
        </div>
        <a href="reports.php" class="btn btn-primary" style="padding:0.6rem 1.2rem; background: linear-gradient(135deg, #10b981, #059669); border: none; box-shadow: 0 4px 12px rgba(16,185,129,0.3);">
            <i class="fas fa-chart-pie"></i> Survey Reporting
        </a>
    </div>

    <?php if ($msg): ?>
    <div style="background:rgba(16,185,129,0.1);border:1px solid #10b981;color:#059669;padding:0.9rem 1rem;border-radius:0.75rem;margin-bottom:1.5rem;font-weight:600;">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <!-- Create Survey Card -->
    <div class="glass-card" style="margin-bottom:1.5rem;">
        <h3 style="margin-bottom:1rem;color:#1e293b;">Create New Survey</h3>
        <form method="POST" style="display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap;">
            <input type="hidden" name="action" value="create">
            <div style="flex:1;min-width:180px;">
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#64748b;margin-bottom:0.3rem;text-transform:uppercase;">Survey Title</label>
                <input type="text" name="title" required placeholder="e.g. Customer Satisfaction 2026" style="width:100%;padding:0.6rem 0.9rem;border:1px solid #e2e8f0;border-radius:0.5rem;font-size:0.9rem;">
            </div>
            <div style="flex:2;min-width:240px;">
                <label style="display:block;font-size:0.78rem;font-weight:600;color:#64748b;margin-bottom:0.3rem;text-transform:uppercase;">Description (Optional)</label>
                <input type="text" name="description" placeholder="Brief description..." style="width:100%;padding:0.6rem 0.9rem;border:1px solid #e2e8f0;border-radius:0.5rem;font-size:0.9rem;">
            </div>
            <button type="submit" class="btn btn-primary" style="white-space:nowrap;">
                <i class="fas fa-plus"></i> Create Survey
            </button>
        </form>
    </div>

    <!-- Surveys Table -->
    <div class="admin-table-wrapper">
        <div class="admin-table-header">
            <i class="fas fa-poll"></i> All Surveys (<?php echo $surveys->num_rows; ?>)
        </div>
        <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Questions</th>
                    <th>Responses</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($surveys->num_rows > 0): ?>
                <?php while ($s = $surveys->fetch_assoc()): ?>
                <tr>
                    <td style="font-weight:600;"><?php echo htmlspecialchars($s['title']); ?></td>
                    <td>
                        <span style="background:<?php echo $s['is_active'] ? '#dcfce7' : '#f1f5f9'; ?>;color:<?php echo $s['is_active'] ? '#166534' : '#64748b'; ?>;padding:0.2rem 0.6rem;border-radius:0.3rem;font-size:0.78rem;font-weight:700;">
                            <?php echo $s['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td><?php echo $s['q_count']; ?></td>
                    <td><?php echo $s['r_count']; ?></td>
                    <td style="font-size:0.8rem;color:#64748b;"><?php echo date('M d, Y', strtotime($s['created_at'])); ?></td>
                    <td>
                        <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                            <a href="surveys.php?view=<?php echo $s['id']; ?>" class="btn btn-outline" style="padding:0.25rem 0.65rem;font-size:0.8rem;">
                                <i class="fas fa-edit"></i> Manage
                            </a>
                            <button type="button"
                                onclick="openEditSurveyModal(<?php echo $s['id']; ?>, <?php echo htmlspecialchars(json_encode($s['title'])); ?>, <?php echo htmlspecialchars(json_encode($s['description'] ?? '')); ?>, false)"
                                style="background:#e0f2fe;color:#0e83b5;border:1px solid #bae6fd;border-radius:0.4rem;padding:0.25rem 0.65rem;font-size:0.8rem;cursor:pointer;font-weight:600;"
                                title="Edit survey title">
                                <i class="fas fa-pencil-alt"></i> Edit Title
                            </button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                <button type="submit" style="background:<?php echo $s['is_active']?'#f1f5f9':'#dcfce7'; ?>;color:<?php echo $s['is_active']?'#64748b':'#166534'; ?>;border:1px solid <?php echo $s['is_active']?'#e2e8f0':'#86efac'; ?>;border-radius:0.4rem;padding:0.25rem 0.65rem;font-size:0.8rem;cursor:pointer;font-weight:600;">
                                    <?php echo $s['is_active'] ? '⏸ Deactivate' : '▶ Activate'; ?>
                                </button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this survey and all its data?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                <button type="submit" style="background:transparent;color:#ef4444;border:1px solid #ef4444;border-radius:0.4rem;padding:0.25rem 0.65rem;font-size:0.8rem;cursor:pointer;font-weight:600;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center;padding:2rem;color:#94a3b8;">No surveys yet. Create one above.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- Edit Survey Modal -->
<div id="editSurveyModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:1rem;padding:2rem;width:100%;max-width:480px;margin:1rem;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
            <h3 style="margin:0;color:#1e293b;font-size:1.1rem;"><i class="fas fa-pencil-alt" style="color:#0e83b5;margin-right:0.5rem;"></i>Edit Survey</h3>
            <button onclick="closeEditSurveyModal()" style="background:transparent;border:none;font-size:1.3rem;cursor:pointer;color:#94a3b8;line-height:1;">&#x2715;</button>
        </div>
        <form method="POST" id="editSurveyForm">
            <input type="hidden" name="action" value="edit_survey">
            <input type="hidden" name="id" id="edit_sv_id">
            <input type="hidden" name="from_view" id="edit_sv_from_view" value="0">
            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label" style="font-size:0.78rem;font-weight:600;color:#64748b;text-transform:uppercase;">Survey Title <span style="color:#ef4444;">*</span></label>
                <input type="text" name="title" id="edit_sv_title" class="form-input" required placeholder="e.g. Customer Satisfaction 2026" style="width:100%;padding:0.65rem 0.9rem;border:1px solid #e2e8f0;border-radius:0.5rem;font-size:0.95rem;">
            </div>
            <div class="form-group" style="margin-bottom:1.5rem;">
                <label class="form-label" style="font-size:0.78rem;font-weight:600;color:#64748b;text-transform:uppercase;">Description <small style="color:#94a3b8;text-transform:none;">(optional)</small></label>
                <input type="text" name="description" id="edit_sv_desc" class="form-input" placeholder="Brief description..." style="width:100%;padding:0.65rem 0.9rem;border:1px solid #e2e8f0;border-radius:0.5rem;font-size:0.95rem;">
            </div>
            <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
                <button type="button" onclick="closeEditSurveyModal()" style="padding:0.5rem 1.2rem;border:1px solid #e2e8f0;border-radius:0.5rem;background:#f8fafc;color:#64748b;cursor:pointer;font-weight:600;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding:0.5rem 1.4rem;"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditSurveyModal(id, title, desc, fromView) {
    document.getElementById('edit_sv_id').value        = id;
    document.getElementById('edit_sv_title').value     = title;
    document.getElementById('edit_sv_desc').value      = desc || '';
    document.getElementById('edit_sv_from_view').value = fromView ? 1 : 0;
    var modal = document.getElementById('editSurveyModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    setTimeout(function(){ document.getElementById('edit_sv_title').focus(); }, 100);
}
function closeEditSurveyModal() {
    document.getElementById('editSurveyModal').style.display = 'none';
    document.body.style.overflow = '';
}
document.getElementById('editSurveyModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditSurveyModal();
});
</script>

<?php include '../includes/admin_footer.php'; ?>

