<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('CITIZEN');

$error = '';
$success = '';

// Fetch dynamic options
$complaint_dropdown = [];
$res = $conn->query("SELECT label FROM dropdown_options WHERE type='COMPLAINT_DETAIL' ORDER BY label");
while($row = $res->fetch_assoc()) $complaint_dropdown[] = $row['label'];

$transaction_dropdown = [];
$res = $conn->query("SELECT label FROM dropdown_options WHERE type='TRANSACTION_TYPE' ORDER BY label");
while($row = $res->fetch_assoc()) $transaction_dropdown[] = $row['label'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_name = $_SESSION['name'];
    $user_phone = $_SESSION['phone'];
    $user_email = $_SESSION['email'];
    $user_branch = $_SESSION['branch'];

    $complaint_details = sanitize($_POST['complaint_details']);
    $upper_complaint = strtoupper($complaint_details);
    if (strpos($upper_complaint, 'PHYSICAL INFRASTRUCTURE') !== false || strpos($upper_complaint, 'OTHER') !== false) {
        $specific_detail = sanitize($_POST['complaint_details_specific'] ?? '');
        if ($specific_detail) {
            $complaint_details .= ' - ' . $specific_detail;
        }
    }


    $transaction_type = sanitize($_POST['transaction_type']);
    if (strpos(strtoupper($transaction_type), 'OTHER') !== false) {
        $specific_trans = sanitize($_POST['transaction_type_specific'] ?? '');
        if ($specific_trans) {
            $transaction_type .= ' - ' . $specific_trans;
        }
    }

    $description = sanitize($_POST['description']);
    $raised_previously = ($_POST['raised_previously'] ?? 'NO') === 'YES' ? 1 : 0;
    $previous_details = sanitize($_POST['previous_details'] ?? '');
    $desired_resolution = sanitize($_POST['desired_resolution']);

    $has_documents = 0;
    $document_path = null;

    if (isset($_FILES['supporting_document']) && $_FILES['supporting_document']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.\-_]/", "", basename($_FILES['supporting_document']['name']));
        $targetFilePath = $uploadDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

        $allowTypes = array('jpg', 'png', 'jpeg', 'pdf');
        if (in_array($fileType, $allowTypes)) {
            if (move_uploaded_file($_FILES['supporting_document']['tmp_name'], $targetFilePath)) {
                $has_documents = 1;
                $document_path = 'uploads/' . $fileName;
            } else {
                $error = "Sorry, there was an error uploading your file.";
            }
        } else {
            $error = "Sorry, only JPG, JPEG, PNG, & PDF files are allowed.";
        }
    }

    if (!$error) {
        $stmt = $conn->prepare("
            INSERT INTO complaints 
            (user_name, user_phone, user_email, user_branch, complaint_details, transaction_type, description, raised_previously, previous_details, desired_resolution, has_documents, document_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssssssissis",
            $user_name,
            $user_phone,
            $user_email,
            $user_branch,
            $complaint_details,
            $transaction_type,
            $description,
            $raised_previously,
            $previous_details,
            $desired_resolution,
            $has_documents,
            $document_path
        );

        if ($stmt->execute()) {
            header("Location: dashboard.php?success=complaint");
            exit();
        } else {
            $error = "Failed to submit complaint. Please try again.";
        }
    }
}

$page_title = "File a Complaint";
include '../includes/header.php';
include '../includes/citizen_sidebar.php';
?>

<!-- ═══════════════════════ CONFIDENTIALITY MODAL ═══════════════════════ -->
<div id="confidentiality-modal" style="
    position:fixed;inset:0;z-index:9999;
    background:rgba(15,23,42,0.82);
    display:flex;align-items:center;justify-content:center;
    padding:1rem;
    backdrop-filter:blur(6px);
">
    <div style="
        background:#fff;border-radius:1.25rem;
        max-width:680px;width:100%;
        box-shadow:0 25px 60px rgba(0,0,0,0.4);
        display:flex;flex-direction:column;
        max-height:90vh;
        overflow:hidden;
    ">
        <!-- Modal Header -->
        <div style="background:linear-gradient(135deg,#0e83b5,#1e40af);padding:1.25rem 1.75rem;border-radius:1.25rem 1.25rem 0 0;">
            <div style="text-align:right;font-size:0.7rem;color:rgba(255,255,255,0.7);font-weight:700;letter-spacing:.05em;margin-bottom:.25rem;">ANNEX B</div>
            <h2 style="color:white;font-size:1.1rem;font-weight:800;margin:0 0 .15rem;">NAME OF CDARE</h2>
            <p style="color:rgba(255,255,255,0.9);font-size:.9rem;font-weight:600;margin:0;">Sample Confidentiality Clause</p>
        </div>

        <!-- Modal Body — scrollable -->
        <div id="modal-body" style="padding:1.5rem 1.75rem;overflow-y:auto;flex:1;line-height:1.75;color:#1e293b;font-size:.92rem;" onscroll="checkScroll()">
            <p style="text-align:justify;margin-bottom:1rem;">
                All information obtained, processed, and documented during the handling of financial consumer complaints and requests under the Financial Consumer Protection Assistance Management System (FCPAMS) shall be treated with strict confidentiality. The CDA-Regulated Entity (CDARE) shall ensure that the identity, personal information, and transaction details of the financial consumer are safeguarded and used solely for the purpose of resolving the complaint or request.
            </p>
            <p style="text-align:justify;margin-bottom:1rem;">
                Access to confidential information shall be limited to authorized personnel directly involved in the assessment, investigation, and resolution of the complaint. Any disclosure of information to third parties shall require the explicit consent of the financial consumer, except when disclosure is necessary to comply with legal or regulatory requirements.
            </p>
            <p style="text-align:justify;margin-bottom:1.5rem;">
                The CDARE shall adopt appropriate data privacy and security measures in accordance with the RA 10173 or the Data Privacy Act of 2012 and its implementing rules and regulations. Unauthorized disclosure, misuse, or mishandling of confidential information shall be subject to disciplinary action and applicable penalties under existing laws and cooperative policies.
            </p>
            <div id="scroll-hint" style="text-align:center;color:#94a3b8;font-size:.8rem;padding:.5rem 0;">
                <i class="fas fa-chevron-down"></i> Scroll down to read the full clause before proceeding
            </div>
        </div>

        <!-- Modal Footer -->
        <div style="padding:1.25rem 1.75rem;border-top:1px solid #e2e8f0;background:#f8fafc;border-radius:0 0 1.25rem 1.25rem;display:flex;gap:.75rem;justify-content:flex-end;flex-wrap:wrap;">
            <a href="dashboard.php"
               style="padding:.65rem 1.4rem;border:1.5px solid #e2e8f0;border-radius:.6rem;color:#64748b;font-weight:700;font-size:.88rem;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;">
                <i class="fas fa-times"></i> Decline
            </a>
            <button id="agree-btn" onclick="acceptConfidentiality()"
                style="padding:.65rem 1.6rem;background:linear-gradient(135deg,#10b981,#059669);color:white;border:none;border-radius:.6rem;font-weight:800;font-size:.88rem;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;opacity:.5;pointer-events:none;transition:opacity .3s;">
                <i class="fas fa-check-circle"></i> I Agree &amp; Proceed
            </button>
        </div>
    </div>
</div>

<script>
// Enable agree button once user has scrolled near the bottom
function checkScroll() {
    var body = document.getElementById('modal-body');
    var hint = document.getElementById('scroll-hint');
    var btn  = document.getElementById('agree-btn');
    if (body.scrollTop + body.clientHeight >= body.scrollHeight - 20) {
        btn.style.opacity = '1';
        btn.style.pointerEvents = 'auto';
        if (hint) hint.style.display = 'none';
    }
}
// Also enable if content is short enough that no scroll is needed
window.addEventListener('load', function() {
    var body = document.getElementById('modal-body');
    if (body.scrollHeight <= body.clientHeight + 20) {
        document.getElementById('agree-btn').style.opacity = '1';
        document.getElementById('agree-btn').style.pointerEvents = 'auto';
        var hint = document.getElementById('scroll-hint');
        if (hint) hint.style.display = 'none';
    }
});
function acceptConfidentiality() {
    document.getElementById('confidentiality-modal').style.display = 'none';
    document.getElementById('complaint-form-body').style.display = 'block';
}
</script>
<!-- ══════════════════════════════════════════════════════════════════════ -->

<div class="fade-in">
    <div style="margin-bottom:1.5rem;">
        <a href="dashboard.php" class="btn btn-outline"
            style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.35rem 0.9rem;font-size:0.85rem;margin-bottom:1rem;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1 style="color:white;font-size:1.75rem;margin-bottom:0.25rem;">MEMBER COMPLAINT FORM</h1>
        <p style="color:rgba(255,255,255,0.8);font-size:0.9rem;">Please provide detailed information regarding your
            complaint so we can assist you appropriately.</p>
    </div>


    <div id="complaint-form-body" style="display:<?php echo $error ? 'block' : 'none'; ?>">

    <?php if ($error): ?>
        <div
            style="background:rgba(239,68,68,0.1);border:1px solid #ef4444;color:#ef4444;padding:1rem;border-radius:0.5rem;margin-bottom:1.5rem;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="glass-card" style="max-width:780px;">
        <form method="POST" enctype="multipart/form-data">
            <!-- Section 1: Complainant Info (auto-filled, display only) -->
            <div
                style="background:rgba(14,131,181,0.07);border:1px solid rgba(14,131,181,0.2);border-radius:0.75rem;padding:1.25rem;margin-bottom:1.5rem;">
                <h4
                    style="color:#0e83b5;margin-bottom:1rem;font-size:0.95rem;text-transform:uppercase;letter-spacing:0.05em;">
                    Complainant Information</h4>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
                    <div>
                        <div
                            style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:700;margin-bottom:0.2rem;">
                            Name</div>
                        <div style="font-weight:600;color:#1e293b;"><?php echo htmlspecialchars($_SESSION['name']); ?>
                        </div>
                    </div>
                    <div>
                        <div
                            style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:700;margin-bottom:0.2rem;">
                            Phone</div>
                        <div style="color:#1e293b;"><?php echo htmlspecialchars($_SESSION['phone']); ?></div>
                    </div>
                    <div>
                        <div
                            style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:700;margin-bottom:0.2rem;">
                            Branch</div>
                        <div style="color:#1e293b;"><?php echo htmlspecialchars($_SESSION['branch']); ?></div>
                    </div>
                    <div>
                        <div
                            style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:700;margin-bottom:0.2rem;">
                            Email</div>
                        <div style="color:#1e293b;font-size:0.9rem;"><?php echo htmlspecialchars($_SESSION['email']); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Complaint Details -->
            <div class="form-group">
                <label class="form-label">COMPLAINT DETAILS <span style="color:#ef4444;">*</span></label>
                <select name="complaint_details" class="form-select" required
                    onchange="const val=this.value.toUpperCase(); document.getElementById('complaint_specific_row').style.display=(val.includes('PHYSICAL INFRASTRUCTURE')||val.includes('OTHER'))?'block':'none'">
                    <option value="">Select from below</option>
                    <?php foreach ($complaint_dropdown as $label): ?>
                        <option value="<?php echo htmlspecialchars($label); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="complaint_specific_row" style="display:none;" class="form-group">
                <input type="text" name="complaint_details_specific" class="form-input"
                    placeholder="Please write specific details here">
            </div>

            <div class="form-group">
                <label class="form-label">TRANSACTION TYPE <span style="color:#ef4444;">*</span></label>
                <select name="transaction_type" class="form-select" required
                    onchange="document.getElementById('transaction_specific_row').style.display=(this.value.toUpperCase().includes('OTHER'))?'block':'none'">
                    <option value="">Select from below</option>
                    <?php foreach ($transaction_dropdown as $label): ?>
                        <option value="<?php echo htmlspecialchars($label); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="transaction_specific_row" style="display:none;" class="form-group">
                <input type="text" name="transaction_type_specific" class="form-input"
                    placeholder="Please write specific transaction here">
            </div>

            <div class="form-group">
                <label class="form-label">Description of Complaint: <span style="color:#ef4444;">*</span></label>
                <p style="font-size:0.8rem;color:#64748b;margin-bottom:0.5rem;">(Please provide a detailed description
                    of the issue. Include any relevant dates, names, or events.)</p>
                <textarea name="description" class="form-textarea" rows="5" required></textarea>
            </div>

            <!-- Raised Previously -->
            <div class="form-group">
                <label class="form-label">Have you raised this complaint previously? <span
                        style="color:#ef4444;">*</span></label>
                <div style="display:flex;gap:1.5rem;margin-top:0.5rem;">
                    <label
                        style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;color:#1e293b;font-weight:500;">
                        <input type="radio" name="raised_previously" value="YES" required
                            onchange="document.getElementById('prev_details_row').style.display='block'"
                            style="width:18px;height:18px;accent-color:#0e83b5;cursor:pointer;">
                        YES
                    </label>
                    <label
                        style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;color:#1e293b;font-weight:500;">
                        <input type="radio" name="raised_previously" value="NO" required
                            onchange="document.getElementById('prev_details_row').style.display='none'"
                            style="width:18px;height:18px;accent-color:#0e83b5;cursor:pointer;">
                        NO
                    </label>
                </div>
            </div>

            <div id="prev_details_row" style="display:none;" class="form-group">
                <label class="form-label">If yes, when and whom?</label>
                <input type="text" name="previous_details" class="form-input"
                    placeholder="e.g. Jan 10, 2026 to Mr. Smith">
            </div>

            <div class="form-group">
                <label class="form-label">Desired Resolution: <span style="color:#ef4444;">*</span></label>
                <p style="font-size:0.8rem;color:#64748b;margin-bottom:0.5rem;">(What would you like to see happen to
                    resolve this issue?)</p>
                <textarea name="desired_resolution" class="form-textarea" rows="3" required></textarea>
            </div>

            <!-- Supporting Documents -->
            <div class="form-group">
                <label class="form-label" style="text-transform:uppercase;">Supporting Documents:</label>
                <p style="font-size:0.8rem;color:#64748b;margin-bottom:0.5rem;">(Please attach any documents or evidence
                    related to your complaint, if applicable. Max 5MB, Images or PDF)</p>
                <div style="margin-top:0.5rem;margin-bottom:0.5rem;">
                    <label
                        style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;color:#1e293b;font-weight:500;">
                        <input type="checkbox" id="has_documents_cb"
                            onchange="document.getElementById('document_upload_row').style.display=this.checked?'block':'none'"
                            style="width:18px;height:18px;accent-color:#0e83b5;cursor:pointer;">
                        ATTACHED (Check to upload)
                    </label>
                </div>
            </div>

            <div id="document_upload_row" style="display:none;" class="form-group">
                <input type="file" name="supporting_document" class="form-input" accept=".pdf,image/*"
                    style="padding:0.6rem;">
            </div>

            <!-- Warning notice -->
            <div
                style="background:rgba(244,63,94,0.06);border:1px solid rgba(244,63,94,0.2);border-radius:0.75rem;padding:1rem;margin-bottom:1.5rem;">
                <p style="font-size:0.85rem;color:#be123c;margin:0;">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note:</strong> A Cooperative personnel will review your concern and contact you within
                    <strong>48 hours</strong> on the cellphone number you provided. We appreciate your patience and
                    cooperation.
                </p>
            </div>

            <button type="submit" class="btn"
                style="width:100%;padding:0.9rem;font-size:1rem;background:linear-gradient(135deg,#f43f5e,#be123c);color:white;border:none;">
                <i class="fas fa-paper-plane"></i> Submit Complaint
            </button>
        </form>
    </div>
    </div><!-- /#complaint-form-body -->
</div>


</main>
</div>

<?php include '../includes/footer.php'; ?>