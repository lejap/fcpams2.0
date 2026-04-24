<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('ADMIN');

// confirm_resolution.php now works directly on the tickets table (no resolutions table needed)
// The admin confirms a RESOLVED ticket by marking it CLOSED

$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ticket_id > 0) {
    $conn->begin_transaction();
    try {
        $admin_name = $_SESSION['name'];

        // Mark ticket as CLOSED, store who confirmed and when
        $stmt = $conn->prepare("
            UPDATE tickets 
            SET status = 'CLOSED',
                confirmed_at = CURRENT_TIMESTAMP,
                confirmed_by_name = ?
            WHERE id = ? AND status = 'RESOLVED'
        ");
        $stmt->bind_param("si", $admin_name, $ticket_id);
        $stmt->execute();

        $conn->commit();
        header("Location: submissions.php?confirmed=1");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        die("Confirmation failed: " . $e->getMessage());
    }
} else {
    header("Location: submissions.php");
    exit();
}
?>

