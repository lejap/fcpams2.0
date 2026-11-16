<?php
/**
 * track_visitor.php — Include this at the TOP of any public-facing page
 * to record a unique visit (once per visitor per page per day).
 *
 * Usage:
 *   require_once 'includes/track_visitor.php';   // from root
 *   require_once '../includes/track_visitor.php'; // from subdirs
 *
 * It is safe to include after config/db.php and includes/functions.php.
 * It will silently do nothing if the visitor_logs table does not yet exist.
 */

if (!isset($conn)) return; // Needs DB connection

// Build a stable, anonymised daily key: sha256(ip + user_agent + date + secret)
$_vl_ip      = $_SERVER['REMOTE_ADDR']     ?? '0.0.0.0';
$_vl_ua      = $_SERVER['HTTP_USER_AGENT'] ?? '';
$_vl_page    = basename($_SERVER['PHP_SELF'] ?? 'unknown');
$_vl_today   = date('Y-m-d');
$_vl_secret  = 'fcpams_visitor_salt_2026'; // change if you like — just keep it consistent
$_vl_key     = hash('sha256', $_vl_ip . $_vl_ua . $_vl_today . $_vl_secret);

// Only record once per key per page per day
$_vl_check = $conn->query(
    "SELECT id FROM visitor_logs
     WHERE session_key = '$_vl_key'
       AND page        = '" . $conn->real_escape_string($_vl_page) . "'
       AND DATE(visited_at) = '$_vl_today'
     LIMIT 1"
);

if ($_vl_check && $_vl_check->num_rows === 0) {
    $_vl_ref = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 512);
    $conn->query(
        "INSERT INTO visitor_logs (session_key, page, ip_address, user_agent, referer, visited_at)
         VALUES (
             '$_vl_key',
             '" . $conn->real_escape_string(substr($_vl_page, 0, 255)) . "',
             '" . $conn->real_escape_string($_vl_ip) . "',
             '" . $conn->real_escape_string(substr($_vl_ua, 0, 512)) . "',
             '" . $conn->real_escape_string($_vl_ref) . "',
             NOW()
         )"
    );
}
// Clean up local variables so they don't pollute the including file
unset($_vl_ip, $_vl_ua, $_vl_page, $_vl_today, $_vl_secret, $_vl_key, $_vl_check, $_vl_ref);
?>
