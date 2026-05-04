<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Authentication Guard
 * Redirects to login if user is not authenticated or lacks required role.
 */
if (!function_exists('auth_guard')) {
    function auth_guard($required_role = null) {
        if (!isset($_SESSION['user_id'])) {
            header("Location: /fcpamsweb/login.php");
            exit();
        }

        if ($required_role) {
            $role = $_SESSION['role'];
            // ADMIN can also access STAFF pages
            if ($required_role === 'STAFF' && ($role === 'STAFF' || $role === 'ADMIN')) return;
            if ($role !== $required_role) {
                if ($role === 'ADMIN')    { header("Location: /fcpamsweb/admin/dashboard.php"); }
                elseif ($role === 'STAFF')  { header("Location: /fcpamsweb/staff/dashboard.php"); }
                else                       { header("Location: /fcpamsweb/citizen/dashboard.php"); }
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
        return mysqli_real_escape_string($conn, htmlspecialchars(strip_tags(trim($data))));
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
?>
