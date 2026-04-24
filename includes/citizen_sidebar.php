<div class="dashboard-layout container animate-fade-in relative z-10" style="position: relative;">
    <button id="nav-toggle" class="btn btn-outline" style="position: absolute; top: 1rem; left: 1rem; z-index: 100; padding: 0.5rem; border-radius: 0.5rem; background: rgba(255,255,255,0.8); border: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1);" onclick="toggleNav()" title="Toggle Navigation">
        <img src="/fcpamsweb/images/proflogo.png" alt="Menu" style="width: 24px; height: 24px; object-fit: contain;">
    </button>
    <aside class="sidebar glass-card" id="citizen-sidebar">
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
            <a href="/fcpamsweb/citizen/dashboard.php" class="sidebar-link">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="/fcpamsweb/citizen/submit_ticket.php?type=INQUIRY" class="sidebar-link">
                <i class="fas fa-comment-dots"></i> Inquiries
            </a>
            <a href="/fcpamsweb/citizen/submit_ticket.php?type=SUGGESTION" class="sidebar-link">
                <i class="fas fa-lightbulb"></i> Suggestions
            </a>
            <a href="/fcpamsweb/citizen/submit_ticket.php?type=REQUEST" class="sidebar-link">
                <i class="fas fa-file-alt"></i> Requests
            </a>
            <a href="/fcpamsweb/citizen/submit_complaint.php" class="sidebar-link">
                <i class="fas fa-exclamation-triangle"></i> Complaints
            </a>
            <a href="/fcpamsweb/citizen/surveys.php" class="sidebar-link">
                <i class="fas fa-poll"></i> Surveys
            </a>
        </nav>

        <!-- Logout -->
        <div style="margin-top:auto;padding-top:2rem;">
            <a href="/fcpamsweb/logout.php" class="sidebar-link" style="color:#f43f5e;background:rgba(244,63,94,0.1);justify-content:center;display:flex;align-items:center;gap:0.5rem;text-decoration:none;border-radius:0.6rem;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Dashboard Content -->
    <main class="dashboard-content">

<script>
function toggleNav() {
    const sidebar = document.getElementById('citizen-sidebar');
    if (sidebar.style.display === 'none') {
        sidebar.style.display = 'flex';
    } else {
        sidebar.style.display = 'none';
    }
}
</script>
