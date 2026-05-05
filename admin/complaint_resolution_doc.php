<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('STAFF');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: complaints.php"); exit(); }

$stmt = $conn->prepare("SELECT * FROM complaints WHERE id = ? AND status = 'CLOSED'");
$stmt->bind_param("i", $id);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();

if (!$c) {
    echo "<p style='padding:2rem;color:red;font-family:Arial;'>Complaint not found or not yet confirmed/closed by admin.</p>";
    exit();
}

$day        = $c['confirmed_at'] ? date('j',    strtotime($c['confirmed_at'])) : '___';
$month      = $c['confirmed_at'] ? date('F',    strtotime($c['confirmed_at'])) : '___________';
$year       = $c['confirmed_at'] ? date('y',    strtotime($c['confirmed_at'])) : '__';
$filed_date = date('F j, Y', strtotime($c['created_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Resolution Document – #<?php echo $c['id']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Times New Roman', Times, serif;
            background: #d1d5db;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem 1rem 3rem;
        }

        /* ── Toolbar (hidden on print) ── */
        .toolbar {
            width: 100%;
            max-width: 750px;
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1rem;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        .toolbar button, .toolbar a {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.55rem 1.3rem;
            border-radius: 0.5rem;
            font-size: 0.88rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            border: none;
            font-family: Arial, sans-serif;
            transition: opacity 0.2s;
        }
        .toolbar button:hover, .toolbar a:hover { opacity: 0.85; }
        .btn-back     { background:#f1f5f9; color:#475569; border:1px solid #cbd5e1 !important; }
        .btn-print    { background:#0e83b5; color:white; }
        .btn-download { background:#10b981; color:white; }

        /* ── Outer wrapper ── */
        .doc-wrapper {
            width: 100%;
            max-width: 750px;
            background: white;
            box-shadow: 0 6px 32px rgba(0,0,0,0.18);
        }

        /* ── Header image – full width, no padding ── */
        .doc-header-img {
            width: 100%;
            display: block;
            line-height: 0;
            padding: 1rem 1.25rem 0.75rem;
        }
        .doc-header-img img {
            width: 100%;
            display: block;
        }

        /* ── Content area with padding ── */
        .doc-content {
            padding: 1.5rem 2rem 2rem;
        }

        /* ── Inner border box ── */
        .doc-border {
            border: 1.5px solid #333;
            padding: 1.25rem 1.5rem 2rem;
            min-height: 820px;
            display: flex;
            flex-direction: column;
        }

        /* ── Title bar inside border ── */
        .doc-title-bar {
            text-align: center;
            font-weight: bold;
            font-size: 0.9rem;
            border-bottom: 1.5px solid #333;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }

        .org-name {
            text-align: center;
            font-weight: bold;
            font-size: 1rem;
            margin-bottom: 0.1rem;
        }
        .doc-subtitle {
            text-align: center;
            font-weight: bold;
            font-style: italic;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        /* ── Body text ── */
        .doc-body {
            font-size: 0.9rem;
            line-height: 1.85;
            text-align: justify;
            flex: 1;
        }

        /* Pre-filled data — BLACK + BOLD */
        .filled {
            font-weight: bold;
            color: #000;
            border-bottom: 1px solid #333;
            display: inline-block;
            min-width: 160px;
            text-align: center;
            padding: 0 3px;
            vertical-align: bottom;
            line-height: 1.4;
        }
        /* Blank underline (unsigned fields) */
        .blank {
            display: inline-block;
            border-bottom: 1px solid #333;
            min-width: 160px;
            vertical-align: bottom;
        }
        .blank-sm  { min-width: 45px; }
        .blank-md  { min-width: 110px; }
        .blank-lg  { min-width: 220px; }

        .affirmation-list {
            margin: 0.5rem 0 1.25rem 1.25rem;
        }
        .affirmation-list li {
            margin-bottom: 0.5rem;
            text-align: justify;
        }

        /* Signature section */
        .sign-section {
            margin-top: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        .sign-row {
            font-weight: bold;
            font-size: 0.9rem;
        }
        .sign-row .sign-line {
            display: inline-block;
            border-bottom: 1px solid #333;
            min-width: 260px;
            margin-left: 0.25rem;
            vertical-align: bottom;
            font-weight: bold;
            color: #000;
            padding: 0 3px;
            line-height: 1.4;
        }
        .sign-row .sign-blank {
            display: inline-block;
            border-bottom: 1px solid #333;
            min-width: 260px;
            margin-left: 0.25rem;
            vertical-align: bottom;
        }

        /* Doc footer */
        .doc-footer {
            text-align: right;
            font-size: 0.78rem;
            color: #ef4444;
            font-weight: bold;
            font-style: italic;
            margin-top: auto;
            padding-top: 1.5rem;
        }

        /* ── Print styles ── */
        @media print {
            body { background: white; padding: 0; }
            .toolbar { display: none !important; }
            .doc-wrapper {
                box-shadow: none;
                max-width: 100%;
            }
            .doc-border { border: 1.5px solid #000; }
            @page { margin: 0; size: A4; }
        }
    </style>
</head>
<body>

<!-- Toolbar -->
<div class="toolbar">
    <a href="javascript:history.back()" class="btn-back">
        <i class="fas fa-arrow-left"></i> Back
    </a>
    <button class="btn-print" onclick="window.print()">
        <i class="fas fa-print"></i> Print
    </button>
    <button class="btn-download" onclick="window.print()">
        <i class="fas fa-file-download"></i> Download PDF
    </button>
</div>

<!-- Document wrapper -->
<div class="doc-wrapper">

    <!-- Header image — OUTSIDE the border, full width -->
    <div class="doc-header-img">
        <img src="<?php echo BASE_URL; ?>images/bcheader.png" alt="Bansalan Cooperative Header">
    </div>

    <!-- Content area -->
    <div class="doc-content">
        <div class="doc-border">

            <!-- Title bar -->
            <div class="doc-title-bar">
                Acceptance and Settlement of Complaint Resolution
            </div>

            <div class="org-name">BANSALAN COOPERATIVE</div>
            <div class="doc-subtitle">Acceptance and Settlement of Complaint Resolution</div>

            <!-- Body -->
            <div class="doc-body">
                <p style="margin-bottom:1rem;">
                    I, <span class="filled"><?php echo htmlspecialchars($c['user_name']); ?></span>,
                    a member of Bansalan Cooperative, hereby acknowledge
                    that I have received, reviewed, and understood the findings and resolution issued by the
                    Cooperative in response to my complaint dated
                    <span class="filled"><?php echo $filed_date; ?></span>.
                </p>

                <p style="margin-bottom:0.4rem;">I affirm that:</p>
                <ol class="affirmation-list">
                    <li>The resolution has been explained to me in full, including any actions to be taken by both the Cooperative and myself.</li>
                    <li>I accept the resolution as a fair and satisfactory settlement of my complaint.</li>
                    <li>I agree that upon signing this document, the matter shall be considered fully settled and closed, with no further claims or demands arising from the same issue.</li>
                    <li>I sign this statement voluntarily and without any form of coercion.</li>
                </ol>

                <p style="margin-bottom:1.5rem;">
                    Signed this <span class="filled blank-sm"><?php echo $day; ?></span>
                    day of <span class="filled blank-md"><?php echo $month; ?></span>,
                    20 <span class="filled blank-sm"><?php echo $year; ?></span>
                    at <span class="filled blank-lg"><?php echo htmlspecialchars($c['user_branch']); ?></span>.
                </p>

                <!-- Signature section -->
                <div class="sign-section">
                    <div class="sign-row">
                        Member's Name &amp; Signature:
                        <span class="sign-line"><?php echo htmlspecialchars($c['user_name']); ?></span>
                    </div>
                    <div class="sign-row">
                        Witness/Coop Representative:
                        <span class="sign-line"><?php echo htmlspecialchars($c['confirmed_by_name'] ?? ''); ?> /</span>
                    </div>
                    <div class="sign-row">
                        Position:
                        <span class="sign-blank blank-lg"></span>
                    </div>
                </div>
            </div>

        </div><!-- /.doc-border -->
    </div><!-- /.doc-content -->

</div><!-- /.doc-wrapper -->

</body>
</html>
