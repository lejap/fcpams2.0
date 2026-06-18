<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('ADMIN');

$res_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$res_id) { header("Location: surveys.php"); exit(); }

// Fetch response with answers
$response = $conn->query("SELECT * FROM survey_responses WHERE id=$res_id")->fetch_assoc();
if (!$response) { header("Location: surveys.php"); exit(); }

$survey   = $conn->query("SELECT * FROM surveys WHERE id={$response['survey_id']}")->fetch_assoc();
$answers  = $conn->query("
    SELECT a.*, q.text AS question, q.type AS q_type
    FROM answers a
    JOIN questions q ON a.question_id = q.id
    WHERE a.response_id = $res_id
    ORDER BY q.id ASC
");

$page_title = "Survey Response";
include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<div class="fade-in">
    <div style="margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
        <a href="surveys.php?view=<?php echo $response['survey_id']; ?>" class="btn btn-outline" style="padding:0.4rem 1rem;">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <div>
            <h1 style="font-size:1.5rem;color:#0e83b5;margin:0;">Survey Response</h1>
            <p style="color:#64748b;font-size:0.85rem;margin:0;">Survey: <?php echo htmlspecialchars($survey['title'] ?? ''); ?></p>
        </div>
    </div>

    <!-- Respondent Info -->
    <div class="glass-card">
        <h3 style="margin-bottom:1rem;color:#1e293b;">Respondent Information</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;">
            <div>
                <div style="font-size:0.75rem;color:#94a3b8;text-transform:uppercase;font-weight:700;">Name</div>
                <div style="font-weight:600;color:#1e293b;"><?php echo htmlspecialchars($response['user_name']); ?></div>
            </div>
            <div>
                <div style="font-size:0.75rem;color:#94a3b8;text-transform:uppercase;font-weight:700;">Phone</div>
                <div style="color:#1e293b;"><?php echo htmlspecialchars($response['user_phone'] ?? '—'); ?></div>
            </div>
            <div>
                <div style="font-size:0.75rem;color:#94a3b8;text-transform:uppercase;font-weight:700;">Branch</div>
                <div style="color:#1e293b;"><?php echo htmlspecialchars($response['user_branch'] ?? '—'); ?></div>
            </div>
            <div>
                <div style="font-size:0.75rem;color:#94a3b8;text-transform:uppercase;font-weight:700;">Submitted</div>
                <div style="color:#1e293b;"><?php echo date('M d, Y H:i', strtotime($response['created_at'])); ?></div>
            </div>
        </div>
    </div>

    <!-- Answers -->
    <div class="glass-card">
        <h3 style="margin-bottom:1.25rem;color:#1e293b;">Answers</h3>
        <?php if ($answers->num_rows === 0): ?>
            <p style="color:#94a3b8;">No answers found for this response.</p>
        <?php else: ?>
        <?php $i = 1; while ($a = $answers->fetch_assoc()): ?>
        <div style="padding:1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:0.5rem;margin-bottom:0.75rem;">
            <div style="font-size:0.78rem;color:#94a3b8;text-transform:uppercase;font-weight:700;margin-bottom:0.3rem;">Question <?php echo $i++; ?></div>
            <div style="font-weight:600;color:#1e293b;margin-bottom:0.5rem;"><?php echo htmlspecialchars($a['question']); ?></div>
            <div style="color:#334155;padding:0.4rem 0.75rem;background:white;border-radius:0.4rem;border:1px solid #e2e8f0;">
                <?php if ($a['q_type'] === 'RATING'): ?>
                    <?php $r = (int)$a['value']; ?>
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                        <span style="color:<?php echo $s <= $r ? '#f59e0b' : '#d1d5db'; ?>;font-size:1.2rem;">★</span>
                    <?php endfor; ?>
                    <span style="margin-left:0.5rem;color:#64748b;font-size:0.85rem;">(<?php echo $r; ?>/5)</span>
                <?php elseif ($a['q_type'] === 'MULTI_SELECT'): ?>
                    <?php $picks = array_filter(array_map('trim', explode(',', $a['value']))); ?>
                    <?php if ($picks): ?>
                        <?php foreach ($picks as $pick): ?>
                        <span style="display:inline-block;background:#ede9fe;color:#5b21b6;padding:0.2rem 0.65rem;border-radius:2rem;font-size:0.82rem;font-weight:600;margin:0.15rem 0.15rem 0.15rem 0;">
                            <i class="fas fa-check" style="font-size:0.7rem;"></i> <?php echo htmlspecialchars($pick); ?>
                        </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span style="color:#94a3b8;font-style:italic;">No selection</span>
                    <?php endif; ?>
                <?php else: ?>
                    <?php echo htmlspecialchars($a['value']); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>
<?php include '../includes/admin_footer.php'; ?>

