<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('STAFF');

// Fetch all OPEN tickets (no user_id FK - tickets are anonymous, identified by name/phone)
$query = "SELECT * FROM tickets WHERE status = 'OPEN' ORDER BY created_at ASC";
$result = $conn->query($query);

// Counts for quick stats
$total_open     = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE status='OPEN'")->fetch_assoc()['c'];
$total_resolved = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE status='RESOLVED'")->fetch_assoc()['c'];

include '../includes/functions.php'; // already included above but safe

$page_title = "Staff Dashboard";
include '../includes/header.php';
?>

<style>
.stat-card{position:relative;background:rgba(255,255,255,0.85);border-radius:1rem;padding:1.25rem 1.5rem;box-shadow:0 4px 20px rgba(0,0,0,0.08);overflow:hidden;text-align:center;}
.stat-card-value{font-size:2.2rem;font-weight:800;line-height:1;margin-bottom:0.25rem;}
.stat-card-label{font-size:0.78rem;text-transform:uppercase;letter-spacing:0.07em;color:#64748b;font-weight:700;}
.admin-table-wrapper{background:rgba(255,255,255,0.9);border-radius:1rem;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);margin-top:1.5rem;}
.admin-table-header{background:#3b82f6;color:white;padding:1rem 1.5rem;font-weight:700;font-size:0.95rem;display:flex;align-items:center;gap:0.5rem}
.admin-table{width:100%;border-collapse:collapse}
.admin-table th{background:#f8fafc;color:#374151;padding:0.75rem 1rem;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:2px solid #e2e8f0}
.admin-table td{padding:0.75rem 1rem;border-bottom:1px solid #f1f5f9;color:#1e293b;font-size:0.88rem;vertical-align:middle}
.admin-table tr:hover td{background:#f8fafc}
.badge-type{padding:0.2rem 0.5rem;border-radius:0.3rem;font-size:0.7rem;font-weight:700;color:white;}
</style>

<div class="fade-in" style="max-width:1200px;margin:0 auto;">
    <div style="margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.75rem;color:white;margin-bottom:0.25rem;">Staff Portal</h1>
            <p style="color:rgba(255,255,255,0.7);font-size:0.9rem;">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong>. Manage and resolve incoming submissions.</p>
        </div>
        <a href="/fcpamsweb/logout.php" style="display:flex;align-items:center;gap:0.5rem;padding:0.5rem 1.25rem;border-radius:0.75rem;color:#ef4444;background:rgba(239,68,68,0.1);text-decoration:none;font-weight:600;border:1px solid rgba(239,68,68,0.2);">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div class="stat-card">
            <div class="stat-card-value" style="color:#ef4444;"><?php echo $total_open; ?></div>
            <div class="stat-card-label">Open Submissions</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-value" style="color:#10b981;"><?php echo $total_resolved; ?></div>
            <div class="stat-card-label">Resolved</div>
        </div>
    </div>

    <!-- Active Tickets Table -->
    <div class="admin-table-wrapper">
        <div class="admin-table-header">
            <i class="fas fa-layer-group"></i> Open Submissions – Awaiting Resolution
        </div>
        <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Ref No</th>
                    <th>Requester</th>
                    <th>Branch</th>
                    <th>Type</th>
                    <th>Message</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($ticket = $result->fetch_assoc()): ?>
                <tr>
                    <td style="font-weight:700;color:#64748b;"><?php echo htmlspecialchars($ticket['ref_no']); ?></td>
                    <td>
                        <div style="font-weight:600;"><?php echo htmlspecialchars($ticket['user_name']); ?></div>
                        <div style="font-size:0.75rem;color:#94a3b8;"><?php echo htmlspecialchars($ticket['user_phone']); ?></div>
                    </td>
                    <td>
                        <span style="background:#e2e8f0;color:#334155;padding:0.1rem 0.4rem;border-radius:0.25rem;font-size:0.75rem;font-weight:700;">
                            <?php echo htmlspecialchars($ticket['user_branch']); ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $type_colors = ['INQUIRY'=>'#3b82f6','SUGGESTION'=>'#8b5cf6','REQUEST'=>'#f59e0b'];
                        $tc = $type_colors[$ticket['type']] ?? '#64748b';
                        ?>
                        <span class="badge-type" style="background:<?php echo $tc; ?>;">
                            <?php echo htmlspecialchars($ticket['type']); ?>
                        </span>
                    </td>
                    <td style="max-width:260px;">
                        <?php echo htmlspecialchars(mb_substr($ticket['message'] ?? '', 0, 60)) . (strlen($ticket['message'] ?? '') > 60 ? '...' : ''); ?>
                    </td>
                    <td style="font-size:0.8rem;color:#64748b;"><?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></td>
                    <td>
                        <a href="resolve_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-primary" style="padding:0.3rem 0.9rem;font-size:0.82rem;white-space:nowrap;">
                            <i class="fas fa-check"></i> Resolve
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center;padding:2.5rem;color:#94a3b8;">
                    <i class="fas fa-check-circle" style="font-size:2rem;display:block;margin-bottom:0.75rem;color:#10b981;"></i>
                    No open submissions. Great job!
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
