<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if it doesn't exist in the session
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Get CSRF Token
 */
if (!function_exists('csrf_token')) {
    function csrf_token() {
        return $_SESSION['csrf_token'] ?? '';
    }
}

/**
 * Output Hidden CSRF Input Field
 */
if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
    }
}

/**
 * Validate CSRF Token on POST Requests
 */
if (!function_exists('validate_csrf')) {
    function validate_csrf() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                die("<div style='padding:2rem;font-family:Arial,sans-serif;color:#ef4444;text-align:center;'>
                        <h2>CSRF Verification Failed</h2>
                        <p>Your session may have expired. Please go back, refresh the page, and try again.</p>
                        <a href='javascript:history.back()' style='color:#3b82f6;text-decoration:none;font-weight:bold;'>&larr; Go Back</a>
                     </div>");
            }
        }
    }
}

/**
 * Authentication Guard
 * Redirects to login if user is not authenticated or lacks required role.
 */
if (!function_exists('auth_guard')) {
    function auth_guard($required_role = null) {
        if (!isset($_SESSION['user_id'])) {
            header("Location: " . BASE_URL . "login.php");
            exit();
        }

        if ($required_role) {
            $role = $_SESSION['role'];
            // ADMIN can also access STAFF pages
            if ($required_role === 'STAFF' && ($role === 'STAFF' || $role === 'ADMIN')) return;
            if ($role !== $required_role) {
                if ($role === 'ADMIN')    { header("Location: " . BASE_URL . "admin/dashboard.php"); }
                elseif ($role === 'STAFF')  { header("Location: " . BASE_URL . "staff/dashboard.php"); }
                else                       { header("Location: " . BASE_URL . "citizen/dashboard.php"); }
                exit();
            }
        }
    }
}

/**
 * Sanitize User Input
 */
if (!function_exists('sanitize')) {
    function sanitize($data) {
        global $conn;
        return mysqli_real_escape_string($conn, strip_tags(trim($data)));
    }
}

/**
 * Generate Reference Number
 * Format: [TYPE]-YYYY-XXXX
 */
if (!function_exists('generate_ref_no')) {
    function generate_ref_no($type) {
        global $conn;
        $prefix = strtoupper(substr($type, 0, 3));
        $year = date('Y');
        
        $query = "SELECT COUNT(*) as total FROM tickets WHERE ref_no LIKE '$prefix-$year-%'";
        $result = $conn->query($query);
        $row = $result->fetch_assoc();
        $next_id = str_pad($row['total'] + 1, 3, '0', STR_PAD_LEFT);
        
        return "$prefix-$year-$next_id";
    }
}

/**
 * Get Status Badge Class
 */
if (!function_exists('get_status_class')) {
    function get_status_class($status) {
        switch ($status) {
            case 'PENDING': return 'badge-warning';
            case 'IN_PROGRESS': return 'badge-primary';
            case 'RESOLVED': return 'badge-success';
            case 'CLOSED': return 'badge-info';
            default: return 'badge-secondary';
        }
    }
}

/**
 * Format Remark for Display
 * Converts literal \r\n escape sequences AND real newlines to <br> tags.
 * Use this everywhere admin_remark / confirm_remark is displayed.
 */
if (!function_exists('format_remark')) {
    function format_remark($text) {
        if (empty($text)) return '';
        // Replace literal backslash-r-backslash-n (4 chars) with a real newline
        $text = str_replace(['\\r\\n', '\\r', '\\n'], "\n", $text);
        // Normalize Windows-style real CRLF to LF
        $text = str_replace("\r\n", "\n", $text);
        return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }
}
?>
