<?php
require_once '../config/db.php';
require_once '../includes/functions.php';
auth_guard('ADMIN');

// ── Parameters ────────────────────────────────────────────────────────────────
$survey_id = isset($_GET['survey']) ? (int)$_GET['survey'] : 0;
$f_branch  = isset($_GET['branch']) ? sanitize($_GET['branch']) : '';
$f_from    = isset($_GET['from'])   ? sanitize($_GET['from'])   : '';
$f_to      = isset($_GET['to'])     ? sanitize($_GET['to'])     : '';

if (!$survey_id) {
    die('Please select a specific survey to export.');
}

// ── Fetch survey meta ─────────────────────────────────────────────────────────
$survey = $conn->query("SELECT * FROM surveys WHERE id=$survey_id")->fetch_assoc();
if (!$survey) die('Survey not found.');

// ── Fetch questions ───────────────────────────────────────────────────────────
$qs_res = $conn->query("SELECT * FROM questions WHERE survey_id=$survey_id ORDER BY id ASC");
$questions = [];
while ($q = $qs_res->fetch_assoc()) $questions[] = $q;

// ── Response filter ───────────────────────────────────────────────────────────
$rWhere = "WHERE r.survey_id=$survey_id";
if ($f_branch) $rWhere .= " AND r.user_branch='" . mysqli_real_escape_string($conn, $f_branch) . "'";
if ($f_from)   $rWhere .= " AND DATE(r.created_at)>='$f_from'";
if ($f_to)     $rWhere .= " AND DATE(r.created_at)<='$f_to'";

$responses_res = $conn->query(
    "SELECT r.id, r.user_name, r.user_phone, r.user_email, r.user_branch, r.created_at
     FROM survey_responses r $rWhere ORDER BY r.created_at DESC"
);
$responses = [];
while ($r = $responses_res->fetch_assoc()) $responses[] = $r;
$response_count = count($responses);

// ── Per-question stats ────────────────────────────────────────────────────────
$question_stats = [];
foreach ($questions as $q) {
    $qid = $q['id'];
    $aW  = "WHERE a.question_id=$qid";
    if ($f_branch) $aW .= " AND r.user_branch='" . mysqli_real_escape_string($conn, $f_branch) . "'";
    if ($f_from)   $aW .= " AND DATE(r.created_at)>='$f_from'";
    if ($f_to)     $aW .= " AND DATE(r.created_at)<='$f_to'";

    $ans_res = $conn->query("SELECT a.value FROM answers a JOIN survey_responses r ON a.response_id=r.id $aW");
    $s = ['question' => $q, 'csat' => null, 'dist' => [], 'texts' => [],
          'agree' => 0, 'total' => 0, 'avg_rating' => null];

    if ($q['type'] === 'RATING') {
        $d = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $sum = 0;
        while ($a = $ans_res->fetch_assoc()) {
            $v = (int)$a['value'];
            if ($v >= 1 && $v <= 5) { $d[$v]++; $sum += $v; }
        }
        $agree = $d[4] + $d[5];
        $tot   = array_sum($d);
        $s['csat']       = $tot > 0 ? round($agree / $tot * 100, 2) : null;
        $s['avg_rating'] = $tot > 0 ? round($sum / $tot, 2) : null;
        $s['dist']  = $d;
        $s['agree'] = $agree;
        $s['total'] = $tot;
    } elseif (in_array($q['type'], ['CHOICE', 'MULTI_SELECT'])) {
        $d = [];
        while ($a = $ans_res->fetch_assoc()) {
            foreach (explode(',', $a['value']) as $v) {
                $v = trim($v);
                if ($v !== '') $d[$v] = ($d[$v] ?? 0) + 1;
            }
        }
        arsort($d);
        $s['dist']  = $d;
        $s['total'] = array_sum($d);
    } else {
        while ($a = $ans_res->fetch_assoc()) {
            if (trim($a['value']) !== '') $s['texts'][] = $a['value'];
        }
        $s['total'] = count($s['texts']);
    }
    $question_stats[] = $s;
}

// ── Overall CSAT ──────────────────────────────────────────────────────────────
$ta = 0; $tr = 0;
foreach ($question_stats as $st) {
    if ($st['question']['type'] === 'RATING') {
        $ta += $st['agree'];
        $tr += $st['total'];
    }
}
$overall_csat = $tr > 0 ? round($ta / $tr * 100, 2) : null;

// ── Raw response detail (all answers per respondent) ─────────────────────────
$raw_rows = [];
foreach ($responses as $resp) {
    $rid  = $resp['id'];
    $row  = [
        'id'         => $rid,
        'name'       => $resp['user_name'],
        'phone'      => $resp['user_phone'] ?? '',
        'email'      => $resp['user_email'] ?? '',
        'branch'     => $resp['user_branch'] ?? '',
        'submitted'  => date('Y-m-d H:i', strtotime($resp['created_at'])),
        'answers'    => [],
    ];
    foreach ($questions as $q) {
        $ans = $conn->query(
            "SELECT value FROM answers WHERE response_id=$rid AND question_id={$q['id']} LIMIT 1"
        )->fetch_assoc();
        $row['answers'][$q['id']] = $ans['value'] ?? '';
    }
    $raw_rows[] = $row;
}

// ══════════════════════════════════════════════════════════════════════════════
// XLSX BUILDER (pure PHP / Open XML — no Composer)
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Escape text for XML cell value (shared-string-free inline approach).
 */
function xesc(string $v): string {
    return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/**
 * Column index (0-based) → Excel letter (A, B, ..., Z, AA, ...)
 */
function colLetter(int $idx): string {
    $r = '';
    $idx++;
    while ($idx > 0) {
        $idx--;
        $r = chr(65 + ($idx % 26)) . $r;
        $idx = intdiv($idx, 26);
    }
    return $r;
}

/**
 * Build one <row> of inline-string + number cells.
 * $rowNum is 1-based Excel row index.
 * $cells = [ [type, value], ... ]  type: 's' = string, 'n' = number, 'h' = header
 */
function buildRow(int $rowNum, array $cells): string {
    $xml = "<row r=\"$rowNum\">";
    foreach ($cells as $ci => [$type, $val]) {
        $ref = colLetter($ci) . $rowNum;
        if ($type === 'n') {
            $xml .= "<c r=\"$ref\"><v>" . xesc((string)$val) . "</v></c>";
        } else {
            $sIdx = $type === 'h' ? ' s="1"' : '';
            $xml .= "<c r=\"$ref\" t=\"inlineStr\"$sIdx><is><t>" . xesc((string)$val) . "</t></is></c>";
        }
    }
    $xml .= "</row>";
    return $xml;
}

// ── Sheet 1: Summary / Statistics ────────────────────────────────────────────
function buildSummarySheet(array $survey, array $question_stats, int $response_count, $overall_csat): string {
    $rows = '';
    $r = 1;

    // Title
    $rows .= buildRow($r++, [['s', 'FCPAMS — Survey Statistical Report']]);
    $rows .= buildRow($r++, [['s', 'Survey: ' . $survey['title']]]);
    $rows .= buildRow($r++, [['s', 'Generated: ' . date('F d, Y \a\t h:i A')]]);
    $rows .= buildRow($r++, [['s', 'Total Responses: ' . $response_count]]);
    $csat_lbl = $overall_csat !== null ? $overall_csat . '%' : 'N/A';
    $rows .= buildRow($r++, [['s', 'Overall CSAT Score: ' . $csat_lbl]]);
    $rows .= buildRow($r++, []);
    $rows .= buildRow($r++, [['h', 'CSAT Score Tiers'], ['h', 'Meaning']]);
    $rows .= buildRow($r++, [['s', '85% - 100%'], ['s', 'Excellent — Exceeding expectations']]);
    $rows .= buildRow($r++, [['s', '70% - 84%'],  ['s', 'Good — Room for improvement']]);
    $rows .= buildRow($r++, [['s', 'Below 70%'],  ['s', 'Needs Improvement — Immediate action needed']]);
    $rows .= buildRow($r++, []);

    // Per question
    $qnum = 1;
    foreach ($question_stats as $qs) {
        $q   = $qs['question'];
        $typ = $q['type'];

        $rows .= buildRow($r++, [['h', "Q$qnum: " . $q['text']], ['h', 'Type: ' . $typ]]);

        if ($typ === 'RATING') {
            $rows .= buildRow($r++, [['s', 'Average Rating'], ['n', $qs['avg_rating'] ?? 'N/A']]);
            $rows .= buildRow($r++, [['s', 'CSAT Score (%)'], ['n', $qs['csat'] ?? 'N/A']]);
            $rows .= buildRow($r++, [['s', 'Total Responses'], ['n', $qs['total']]]);
            $rows .= buildRow($r++, [['h', 'Rating'], ['h', 'Count'], ['h', '% of Total']]);
            foreach ($qs['dist'] as $star => $cnt) {
                $pct = $qs['total'] > 0 ? round($cnt / $qs['total'] * 100, 1) : 0;
                $label = match((int)$star) {
                    1 => '1 ⭐ Strongly Disagree',
                    2 => '2 ⭐ Disagree',
                    3 => '3 ⭐ Neutral',
                    4 => '4 ⭐ Agree',
                    5 => '5 ⭐ Strongly Agree',
                    default => (string)$star
                };
                $rows .= buildRow($r++, [['s', $label], ['n', $cnt], ['n', $pct]]);
            }
        } elseif (in_array($typ, ['CHOICE', 'MULTI_SELECT'])) {
            $rows .= buildRow($r++, [['s', 'Total Selections'], ['n', $qs['total']]]);
            $rows .= buildRow($r++, [['h', 'Option'], ['h', 'Count'], ['h', '% of Selections']]);
            foreach ($qs['dist'] as $opt => $cnt) {
                $pct = $qs['total'] > 0 ? round($cnt / $qs['total'] * 100, 1) : 0;
                $rows .= buildRow($r++, [['s', $opt], ['n', $cnt], ['n', $pct]]);
            }
        } else {
            $rows .= buildRow($r++, [['s', 'Total Text Responses'], ['n', $qs['total']]]);
            $rows .= buildRow($r++, [['h', 'Text Responses (Open-ended)']]);
            foreach ($qs['texts'] as $txt) {
                $rows .= buildRow($r++, [['s', $txt]]);
            }
        }
        $rows .= buildRow($r++, []);
        $qnum++;
    }

    return "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>
<worksheet xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\"
           xmlns:r=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships\">
<sheetData>$rows</sheetData></worksheet>";
}

// ── Sheet 2: Raw Responses ────────────────────────────────────────────────────
function buildRawSheet(array $questions, array $raw_rows): string {
    $rows = '';
    $r    = 1;

    // Header
    $header = [
        ['h', '#'], ['h', 'Respondent Name'], ['h', 'Phone'],
        ['h', 'Email'], ['h', 'Branch'], ['h', 'Submitted At']
    ];
    foreach ($questions as $q) {
        $header[] = ['h', $q['text']];
    }
    $rows .= buildRow($r++, $header);

    // Data rows
    foreach ($raw_rows as $idx => $row) {
        $cells = [
            ['n', $idx + 1],
            ['s', $row['name']],
            ['s', $row['phone']],
            ['s', $row['email']],
            ['s', $row['branch']],
            ['s', $row['submitted']],
        ];
        foreach ($questions as $q) {
            $cells[] = ['s', $row['answers'][$q['id']] ?? ''];
        }
        $rows .= buildRow($r++, $cells);
    }

    return "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>
<worksheet xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\"
           xmlns:r=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships\">
<sheetData>$rows</sheetData></worksheet>";
}

// ── Sheet 3: Per-Question Breakdown (compact) ─────────────────────────────────
function buildBreakdownSheet(array $question_stats): string {
    $rows = '';
    $r    = 1;
    $rows .= buildRow($r++, [
        ['h', '#'], ['h', 'Question'], ['h', 'Type'],
        ['h', 'Total Resp.'], ['h', 'CSAT %'], ['h', 'Avg Rating'],
        ['h', '★1 Count'], ['h', '★2 Count'], ['h', '★3 Count'],
        ['h', '★4 Count'], ['h', '★5 Count'], ['h', 'Top Choice'], ['h', 'Top Choice Count']
    ]);

    foreach ($question_stats as $i => $qs) {
        $q   = $qs['question'];
        $typ = $q['type'];
        $csat   = $typ === 'RATING' ? ($qs['csat'] ?? '') : '';
        $avgr   = $typ === 'RATING' ? ($qs['avg_rating'] ?? '') : '';
        $s1 = $typ === 'RATING' ? ($qs['dist'][1] ?? 0) : '';
        $s2 = $typ === 'RATING' ? ($qs['dist'][2] ?? 0) : '';
        $s3 = $typ === 'RATING' ? ($qs['dist'][3] ?? 0) : '';
        $s4 = $typ === 'RATING' ? ($qs['dist'][4] ?? 0) : '';
        $s5 = $typ === 'RATING' ? ($qs['dist'][5] ?? 0) : '';
        $topChoice = '';
        $topCount  = '';
        if (in_array($typ, ['CHOICE', 'MULTI_SELECT']) && !empty($qs['dist'])) {
            reset($qs['dist']);
            $topChoice = key($qs['dist']);
            $topCount  = current($qs['dist']);
        }
        $rows .= buildRow($r++, [
            ['n', $i + 1],
            ['s', $q['text']],
            ['s', $typ],
            ['n', $qs['total']],
            $csat !== '' ? ['n', $csat] : ['s', ''],
            $avgr !== '' ? ['n', $avgr] : ['s', ''],
            $s1 !== '' ? ['n', $s1] : ['s', ''],
            $s2 !== '' ? ['n', $s2] : ['s', ''],
            $s3 !== '' ? ['n', $s3] : ['s', ''],
            $s4 !== '' ? ['n', $s4] : ['s', ''],
            $s5 !== '' ? ['n', $s5] : ['s', ''],
            ['s', $topChoice],
            $topCount !== '' ? ['n', $topCount] : ['s', ''],
        ]);
    }

    return "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>
<worksheet xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\"
           xmlns:r=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships\">
<sheetData>$rows</sheetData></worksheet>";
}

// ── Assemble XLSX via ZipArchive ──────────────────────────────────────────────
$tmpFile = tempnam(sys_get_temp_dir(), 'fcpams_survey_') . '.xlsx';

$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die('Could not create export file.');
}

// [Content_Types].xml
$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"               ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml"      ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet2.xml"      ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet3.xml"      ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml"                 ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');

// _rels/.rels
$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

// xl/_rels/workbook.xml.rels
$zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>
  <Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"   Target="styles.xml"/>
</Relationships>');

// xl/workbook.xml
$zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Statistical Summary" sheetId="1" r:id="rId1"/>
    <sheet name="Raw Responses"       sheetId="2" r:id="rId2"/>
    <sheet name="Question Breakdown"  sheetId="3" r:id="rId3"/>
  </sheets>
</workbook>');

// xl/styles.xml  (minimal — style 1 = bold header)
$zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/></font>
  </fonts>
  <fills count="2">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
  </fills>
  <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>
  </cellXfs>
</styleSheet>');

// Sheets
$zip->addFromString('xl/worksheets/sheet1.xml', buildSummarySheet($survey, $question_stats, $response_count, $overall_csat));
$zip->addFromString('xl/worksheets/sheet2.xml', buildRawSheet($questions, $raw_rows));
$zip->addFromString('xl/worksheets/sheet3.xml', buildBreakdownSheet($question_stats));

$zip->close();

// ── Send file to browser ──────────────────────────────────────────────────────
$safeTitle = preg_replace('/[^A-Za-z0-9_\-]/', '_', $survey['title']);
$filename  = 'Survey_Report_' . $safeTitle . '_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: max-age=0');

readfile($tmpFile);
unlink($tmpFile);
exit;
