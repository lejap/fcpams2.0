<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " | FCPAMS" : "FCPAMS Staff"; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>images/proflogo.png">
</head>
<body>
<div class="admin-wrapper">
    <!-- Staff Top Header Bar -->
    <header class="admin-header">
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . BASE_URL . 'images/bclogo.png')): ?>
            <img src="<?php echo BASE_URL; ?>images/bclogo.png" alt="FCPAMS Logo" style="height:34px;">
            <?php else: ?>
            <div style="width:34px;height:34px;background:linear-gradient(135deg,#0e83b5,#3b82f6);border-radius:0.5rem;display:flex;align-items:center;justify-content:center;color:white;font-weight:800;font-size:0.85rem;">FC</div>
            <?php endif; ?>
            <span style="font-weight:800;font-size:1.1rem;color:#0f172a;letter-spacing:-0.02em;">FCPAMS System</span>
        </div>
        <div style="display:flex;align-items:center;gap:1rem;">
            <span style="font-size:0.82rem;color:#64748b;font-weight:500;">
                <?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?>
                <span style="background:#dcfce7;color:#166534;padding:0.1rem 0.45rem;border-radius:0.3rem;font-size:0.72rem;font-weight:700;margin-left:0.35rem;">STAFF</span>
            </span>
            <a href="<?php echo BASE_URL; ?>logout.php" style="color:#ef4444;font-weight:700;font-size:0.85rem;display:flex;align-items:center;gap:0.3rem;text-decoration:none;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <!-- Staff Body (sidebar + content injected by staff_sidebar.php) -->
    <div class="admin-body">

<script>
function toggleAdminNav() {
    const sidebar = document.getElementById('admin-sidebar');
    if (sidebar) {
        if (sidebar.style.display === 'none') {
            sidebar.style.display = 'flex';
        } else {
            sidebar.style.display = 'none';
        }
    }
}
</script>
