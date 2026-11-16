<?php
/**
 * track_visitor.php — Include this at the TOP of any public-facing page.
 *
 * Counting logic:
 *  - 1 unique visitor = 1 person per CALENDAR DAY (not per page).
 *    The session_key is hashed from IP + user_agent + date, so the same
 *    person visiting on a NEW day is counted as 1 visitor that day.
 *  - Page views = every page load is recorded (can be many per visitor per day).
 *  - If a person visits index.php then login.php on the same day → still 1 unique visitor.
 *
 * Usage:
 *   require_once 'includes/track_visitor.php';    // from root
 *   require_once '../includes/track_visitor.php';  // from subdirs
 */

if (!isset($conn)) return;

$_vl_ip     = $_SERVER['REMOTE_ADDR']     ?? '0.0.0.0';
$_vl_ua     = $_SERVER['HTTP_USER_AGENT'] ?? '';
$_vl_page   = basename($_SERVER['PHP_SELF'] ?? 'unknown');
$_vl_today  = date('Y-m-d');
$_vl_secret = 'fcpams_visitor_salt_2026';

// Key is per-PERSON per-DAY (NOT per page) — same person visiting any page today = same key
$_vl_key = hash('sha256', $_vl_ip . $_vl_ua . $_vl_today . $_vl_secret);

// Check if this person already has ANY record today (unique visitor dedup across all pages)
$_vl_already_today = $conn->query(
    "SELECT id FROM visitor_logs
     WHERE session_key = '$_vl_key'
       AND DATE(visited_at) = '$_vl_today'
     LIMIT 1"
);

// Always insert a page view row; is_unique_today marks the first hit of the day
$_vl_is_unique = ($_vl_already_today && $_vl_already_today->num_rows === 0) ? 1 : 0;
$_vl_ref       = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 512);

$conn->query(
    "INSERT INTO visitor_logs (session_key, page, ip_address, user_agent, referer, is_unique_today, visited_at)
     VALUES (
         '$_vl_key',
         '" . $conn->real_escape_string(substr($_vl_page, 0, 255)) . "',
         '" . $conn->real_escape_string($_vl_ip) . "',
         '" . $conn->real_escape_string(substr($_vl_ua, 0, 512)) . "',
         '" . $conn->real_escape_string($_vl_ref) . "',
         $_vl_is_unique,
         NOW()
     )"
);

unset($_vl_ip, $_vl_ua, $_vl_page, $_vl_today, $_vl_secret, $_vl_key, $_vl_already_today, $_vl_is_unique, $_vl_ref);
?>
