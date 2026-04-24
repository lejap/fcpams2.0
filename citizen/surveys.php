<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('CITIZEN');

// Fetch active surveys with question count
$surveys = $conn->query("
    SELECT s.*, (SELECT COUNT(*) FROM questions WHERE survey_id=s.id) as q_count
    FROM surveys s
    WHERE s.is_active = 1
    ORDER BY s.created_at DESC
");

$page_title = "Available Surveys";
include '../includes/header.php';
include '../includes/citizen_sidebar.php';
?>

<div class="fade-in">
    <div class="flex justify-between align-center" style="margin-bottom:2rem;">
        <div>
            <h1>Active Surveys</h1>
            <p>Your feedback helps us improve our services.</p>
        </div>
    </div>

    <?php if ($surveys->num_rows === 0): ?>
        <div class="glass-card" style="text-align:center;padding:3rem;">
            <i class="fas fa-poll" style="font-size:3rem;color:#cbd5e1;margin-bottom:1rem;display:block;"></i>
            <p style="color:var(--text-secondary);">There are currently no active surveys. Please check back later.</p>
        </div>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem;">
            <?php while ($s = $surveys->fetch_assoc()): ?>
            <div class="glass-card" style="display:flex;flex-direction:column;gap:0.75rem;border-top:4px solid #10b981;">
                <div>
                    <h3 style="color:#1e293b;margin-bottom:0.25rem;"><?php echo htmlspecialchars($s['title']); ?></h3>
                    <?php if ($s['description']): ?>
                    <p style="font-size:0.85rem;color:#64748b;margin-bottom:0;"><?php echo htmlspecialchars($s['description']); ?></p>
                    <?php endif; ?>
                </div>
                <p style="font-size:0.85rem;color:#64748b;margin:0;">
                    <i class="fas fa-question-circle"></i> <?php echo $s['q_count']; ?> Question<?php echo $s['q_count'] != 1 ? 's' : ''; ?>
                </p>
                <a href="take_survey.php?id=<?php echo $s['id']; ?>" class="btn btn-primary" style="text-align:center;margin-top:auto;background:linear-gradient(135deg,#10b981,#059669);">
                    Take Survey
                </a>
            </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>


</main>
</div>

<?php include '../includes/footer.php'; ?>
