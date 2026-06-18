<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('CITIZEN');

$survey_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$survey_id) { header("Location: surveys.php"); exit(); }

$survey = $conn->query("SELECT * FROM surveys WHERE id=$survey_id AND is_active=1")->fetch_assoc();
if (!$survey) { header("Location: surveys.php"); exit(); }

$questions = $conn->query("SELECT * FROM questions WHERE survey_id=$survey_id ORDER BY id ASC");
$questions_arr = [];
while ($q = $questions->fetch_assoc()) $questions_arr[] = $q;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create a survey response record
    $user_name   = $_SESSION['name'];
    $user_phone  = $_SESSION['phone'];
    $user_email  = $_SESSION['email'];
    $user_branch = $_SESSION['branch'];

    $stmt = $conn->prepare("INSERT INTO survey_responses (survey_id, user_name, user_phone, user_email, user_branch) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $survey_id, $user_name, $user_phone, $user_email, $user_branch);
    
    if ($stmt->execute()) {
        $response_id = $conn->insert_id;

        // Save each answer
        foreach ($questions_arr as $q) {
            if ($q['type'] === 'MULTI_SELECT') {
                $selected = $_POST['q_' . $q['id']] ?? [];
                $val = implode(', ', array_map('trim', (array)$selected));
            } else {
                $val = sanitize($_POST['q_' . $q['id']] ?? '');
            }
            $astmt = $conn->prepare("INSERT INTO answers (response_id, question_id, value) VALUES (?, ?, ?)");
            $astmt->bind_param("iis", $response_id, $q['id'], $val);
            $astmt->execute();
        }

        header("Location: dashboard.php?survey_success=1");
        exit();
    } else {
        $error = "Failed to submit your response. Please try again.";
    }
}

$page_title = "Take Survey: " . htmlspecialchars($survey['title']);
include '../includes/header.php';
include '../includes/citizen_sidebar.php';
?>

<div class="fade-in py-2 flex justify-center">
    <div class="glass-card" style="width:100%;max-width:680px;">
        <!-- Header -->
        <div style="margin-bottom:2rem;border-bottom:1px solid rgba(0,0,0,0.08);padding-bottom:1.25rem;">
            <a href="surveys.php" style="color:#64748b;text-decoration:none;font-size:0.85rem;display:inline-block;margin-bottom:0.75rem;">
                <i class="fas fa-arrow-left"></i> Back to Surveys
            </a>
            <h2 style="color:#1e293b;margin-bottom:0.25rem;"><?php echo htmlspecialchars($survey['title']); ?></h2>
            <?php if ($survey['description']): ?>
            <p style="color:#64748b;font-size:0.9rem;margin:0;"><?php echo htmlspecialchars($survey['description']); ?></p>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
        <div style="background:rgba(239,68,68,0.1);border:1px solid #ef4444;color:#ef4444;padding:1rem;border-radius:0.5rem;margin-bottom:1.5rem;">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($questions_arr)): ?>
            <p style="text-align:center;color:#94a3b8;padding:2rem 0;">This survey has no questions yet.</p>
        <?php else: ?>
        <form method="POST">
            <?php foreach ($questions_arr as $i => $q): ?>
            <div class="form-group" style="background:rgba(0,0,0,0.02);border:1px solid rgba(0,0,0,0.06);border-radius:0.75rem;padding:1.25rem;margin-bottom:1rem;">
                <label class="form-label" style="font-weight:700;color:#1e293b;margin-bottom:0.5rem;display:block;">
                    <?php echo ($i+1); ?>. <?php echo htmlspecialchars($q['text']); ?>
                    <span style="color:#ef4444;">*</span>
                </label>

                <?php if ($q['type'] === 'TEXT'): ?>
                    <textarea name="q_<?php echo $q['id']; ?>" class="form-textarea" rows="3" required placeholder="Type your answer..."></textarea>

                <?php elseif ($q['type'] === 'RATING'): ?>
                    <?php
                    // Twemoji codepoints for: 😄 🙂 😐 😕 😞 (5 → 1, best first)
                    $smiley_cp = ['5'=>'1f604','4'=>'1f642','3'=>'1f610','2'=>'1f615','1'=>'1f61e'];
                    $labels    = ['5'=>'Strongly Agree','4'=>'Agree','3'=>'Neutral','2'=>'Disagree','1'=>'Strongly Disagree'];
                    $scores    = ['5'=>'(5)','4'=>'(4)','3'=>'(3)','2'=>'(2)','1'=>'(1)'];
                    $tw_base   = 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/';
                    ?>
                    <div style="display:flex;gap:1rem;margin-top:0.75rem;flex-wrap:wrap;justify-content:center;">
                        <?php foreach($smiley_cp as $r=>$cp): ?>
                        <label style="display:flex;flex-direction:column;align-items:center;cursor:pointer;gap:0.35rem;min-width:80px;">
                            <input type="radio" name="q_<?php echo $q['id']; ?>" value="<?php echo $r; ?>" required
                                   id="r_<?php echo $q['id'].'_'.$r; ?>"
                                   style="display:none;"
                                   onchange="updateSmileys(<?php echo $q['id']; ?>, <?php echo $r; ?>)">
                            <img src="<?php echo $tw_base.$cp.'.svg'; ?>"
                                 class="smiley-btn"
                                 id="smiley_<?php echo $q['id'].'_'.$r; ?>"
                                 onclick="selectSmiley(<?php echo $q['id']; ?>, <?php echo $r; ?>)"
                                 width="48" height="48"
                                 style="opacity:0.32;cursor:pointer;transition:opacity 0.18s,transform 0.18s,filter 0.18s;filter:grayscale(30%);display:block;"
                                 alt="<?php echo $labels[$r]; ?>">
                            <span style="font-size:0.7rem;color:#334155;font-weight:700;text-align:center;line-height:1.2;"><?php echo $labels[$r]; ?></span>
                            <span style="font-size:0.65rem;color:#94a3b8;font-weight:600;"><?php echo $scores[$r]; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($q['type'] === 'CHOICE'): ?>
                    <?php $opts = array_filter(array_map('trim', explode(',', $q['options'] ?? ''))); ?>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;margin-top:0.5rem;">
                        <?php foreach ($opts as $opt): ?>
                        <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;padding:0.4rem 0.5rem;border-radius:0.4rem;transition:background 0.15s;" onmouseover="this.style.background='rgba(0,0,0,0.04)'" onmouseout="this.style.background='transparent'">
                            <input type="radio" name="q_<?php echo $q['id']; ?>" value="<?php echo htmlspecialchars($opt); ?>" required>
                            <span style="color:#1e293b;"><?php echo htmlspecialchars($opt); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($q['type'] === 'MULTI_SELECT'): ?>
                    <?php
                    preg_match('/^MAX:(\d+)\|(.+)$/', $q['options'] ?? '', $ms);
                    $ms_max  = isset($ms[1]) ? (int)$ms[1] : 2;
                    $ms_opts = isset($ms[2]) ? array_filter(array_map('trim', explode(',', $ms[2]))) : [];
                    ?>
                    <p style="font-size:0.82rem;color:#6366f1;font-weight:600;margin:0.4rem 0 0.6rem;">
                        <i class="fas fa-info-circle"></i> Select up to <strong><?php echo $ms_max; ?></strong> option(s)
                    </p>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;margin-top:0.25rem;">
                        <?php foreach ($ms_opts as $opt): ?>
                        <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;padding:0.4rem 0.5rem;border-radius:0.4rem;transition:background 0.15s;" onmouseover="this.style.background='rgba(0,0,0,0.04)'" onmouseout="this.style.background='transparent'">
                            <input type="checkbox"
                                   name="q_<?php echo $q['id']; ?>[]"
                                   value="<?php echo htmlspecialchars($opt); ?>"
                                   class="mschk-<?php echo $q['id']; ?>"
                                   onchange="enforceMax(<?php echo $q['id']; ?>, <?php echo $ms_max; ?>)">
                            <span style="color:#1e293b;"><?php echo htmlspecialchars($opt); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-primary" style="width:100%;padding:0.9rem;font-size:1rem;margin-top:0.5rem;">
                <i class="fas fa-paper-plane"></i> Submit Survey
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
function selectSmiley(qId, val) {
    document.getElementById('r_' + qId + '_' + val).checked = true;
    updateSmileys(qId, val);
}
function updateSmileys(qId, val) {
    for (var i = 1; i <= 5; i++) {
        var el = document.getElementById('smiley_' + qId + '_' + i);
        if (el) {
            el.style.opacity   = (i == val) ? '1'      : '0.28';
            el.style.transform = (i == val) ? 'scale(1.28)' : 'scale(1)';
            el.style.filter    = (i == val) ? 'grayscale(0%) drop-shadow(0 2px 6px rgba(0,0,0,0.18))' : 'grayscale(30%)';
        }
    }
}
function enforceMax(qId, maxSel) {
    var boxes   = document.querySelectorAll('.mschk-' + qId);
    var checked = document.querySelectorAll('.mschk-' + qId + ':checked');
    boxes.forEach(function(b) {
        b.disabled = (!b.checked && checked.length >= maxSel);
    });
}
</script>


</main>
</div>

<?php include '../includes/footer.php'; ?>
