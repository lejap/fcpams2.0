<div class="dashboard-layout container animate-fade-in relative z-10" style="position: relative;">

    <!-- Overlay backdrop (mobile only) -->
    <div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

    <!-- Hamburger button (mobile only) -->
    <button class="mobile-nav-toggle" id="mobile-nav-toggle" onclick="openSidebar()" title="Open Menu">
        <i class="fas fa-bars"></i>
    </button>

    <aside class="sidebar glass-card" id="citizen-sidebar">
        <!-- Close button inside sidebar (mobile) -->
        <button onclick="closeSidebar()" id="sidebar-close-btn"
            style="display:none;position:absolute;top:1rem;right:1rem;background:transparent;border:none;font-size:1.4rem;color:#64748b;cursor:pointer;">
            <i class="fas fa-times"></i>
        </button>

        <!-- User Avatar + Info -->
        <div class="mb-8 text-center">
            <div style="width:64px;height:64px;background:linear-gradient(135deg,var(--primary),var(--secondary));border-radius:1.25rem;margin:0 auto 1rem;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:white;font-weight:800;box-shadow:0 8px 16px rgba(59,130,246,0.3);">
                <?php echo strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)); ?>
            </div>
            <h2 style="font-size:1.05rem;margin-bottom:0.1rem;color:#1e293b;"><?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?></h2>
            <p style="font-size:0.72rem;margin-bottom:0;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;">
                <?php echo htmlspecialchars($_SESSION['branch'] ?? ''); ?> Branch
            </p>
        </div>

        <!-- Nav Links -->
        <nav class="sidebar-nav">
            <a href="<?php echo BASE_URL; ?>citizen/dashboard.php" class="sidebar-link">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="<?php echo BASE_URL; ?>citizen/submit_complaint.php" class="sidebar-link">
                <i class="fas fa-exclamation-triangle"></i> File a Complaint
            </a>
            <a href="<?php echo BASE_URL; ?>citizen/submit_ticket.php?type=INQUIRY" class="sidebar-link">
                <i class="fas fa-comment-dots"></i> Submit Inquiry
            </a>
            <a href="<?php echo BASE_URL; ?>citizen/submit_ticket.php?type=REQUEST" class="sidebar-link">
                <i class="fas fa-file-alt"></i> Submit Request
            </a>
            <a href="<?php echo BASE_URL; ?>citizen/submit_ticket.php?type=SUGGESTION" class="sidebar-link">
                <i class="fas fa-lightbulb"></i> Make Suggestion
            </a>
            <a href="<?php echo BASE_URL; ?>citizen/submit_appreciation.php" class="sidebar-link">
                <i class="fas fa-star"></i> Commendation
            </a>
            <a href="<?php echo BASE_URL; ?>citizen/surveys.php" class="sidebar-link">
                <i class="fas fa-poll"></i> Surveys
            </a>
        </nav>

        <!-- Logout -->
        <div style="margin-top:auto;padding-top:2rem;">
            <a href="<?php echo BASE_URL; ?>logout.php" class="sidebar-link"
                style="color:#f43f5e;background:rgba(244,63,94,0.1);justify-content:center;display:flex;align-items:center;gap:0.5rem;text-decoration:none;border-radius:0.6rem;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Dashboard Content -->
    <main class="dashboard-content">

<script>
function openSidebar() {
    document.getElementById('citizen-sidebar').classList.add('open');
    document.getElementById('sidebar-overlay').classList.add('active');
    document.getElementById('mobile-nav-toggle').innerHTML = '<i class="fas fa-times"></i>';
    var closeBtn = document.getElementById('sidebar-close-btn');
    if (closeBtn && window.innerWidth < 768) closeBtn.style.display = 'block';
}
function closeSidebar() {
    document.getElementById('citizen-sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('active');
    document.getElementById('mobile-nav-toggle').innerHTML = '<i class="fas fa-bars"></i>';
    var closeBtn = document.getElementById('sidebar-close-btn');
    if (closeBtn) closeBtn.style.display = 'none';
}
document.addEventListener('DOMContentLoaded', function() {
    // Auto-close sidebar on nav link click (mobile)
    document.querySelectorAll('#citizen-sidebar .sidebar-link').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth < 768) closeSidebar();
        });
    });
});
</script>
