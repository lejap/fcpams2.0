<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

auth_guard('ADMIN');

$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$detail = null;
if ($view_id > 0) {
    $detail = $conn->query("SELECT * FROM appreciations WHERE id=$view_id")->fetch_assoc();
}

$branch_filter = isset($_GET['branch']) ? sanitize($_GET['branch']) : '';
$staff_filter  = isset($_GET['staff'])  ? sanitize($_GET['staff'])  : '';
$date_from     = isset($_GET['dateFrom']) ? sanitize($_GET['dateFrom']) : '';
$date_to       = isset($_GET['dateTo'])   ? sanitize($_GET['dateTo'])   : '';

$where = "WHERE 1=1";
if ($branch_filter) $where .= " AND user_branch='$branch_filter'";
if ($staff_filter)  $where .= " AND staff_name LIKE '%$staff_filter%'";
if ($date_from)     $where .= " AND DATE(created_at) >= '$date_from'";
if ($date_to)       $where .= " AND DATE(created_at) <= '$date_to'";

$appreciations = $conn->query("SELECT * FROM appreciations $where ORDER BY created_at DESC");
$branches      = $conn->query("SELECT * FROM branches ORDER BY name");
$total         = $appreciations->num_rows;

$page_title = "Commendations & Appreciations";
include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>

<?php if ($detail): ?>
<div class="fade-in">
    <div style="margin-bottom:1.5rem;">
        <a href="appreciations.php" style="color:#64748b;text-decoration:none;font-size:0.9rem;display:inline-flex;align-items:center;gap:0.4rem;"><i class="fas fa-arrow-left"></i> Back to Appreciations</a>
        <h1 style="font-size:1.75rem;color:#0891b2;margin-top:0.5rem;"><i class="fas fa-star" style="color:#f59e0b;"></i> Appreciation Details #<?php echo $detail['id']; ?></h1>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:1.5rem;margin-bottom:2rem;">
        <div class="glass-card">
            <h4 style="margin-bottom:1.25rem;color:#0891b2;font-size:1rem;display:flex;align-items:center;gap:0.5rem;border-bottom:1px solid rgba(8,145,178,0.15);padding-bottom:0.5rem;"><i class="fas fa-user"></i> Submitter Details</h4>
            
            <div style="margin-bottom:1rem;">
                <div style="font-size:0.72rem;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">Member Name</div>
                <div style="font-weight:700;color:#1e293b;font-size:1rem;"><?php echo htmlspecialchars($detail['user_name']); ?></div>
            </div>
            
            <div style="margin-bottom:1rem;">
                <div style="font-size:0.72rem;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">Phone Number</div>
                <div style="font-weight:500;color:#1e293b;"><?php echo htmlspecialchars($detail['user_phone']); ?></div>
            </div>
            
            <div style="margin-bottom:1rem;">
                <div style="font-size:0.72rem;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">Email Address</div>
                <div style="font-weight:500;color:#1e293b;"><?php echo htmlspecialchars($detail['user_email'] ?: '-'); ?></div>
            </div>
            
            <div style="margin-bottom:1rem;">
                <div style="font-size:0.72rem;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">Branch</div>
                <div style="margin-top:0.25rem;">
                    <span style="background:#e0f2fe;color:#0369a1;padding:0.2rem 0.55rem;border-radius:0.35rem;font-size:0.75rem;font-weight:700;">
                        <?php echo htmlspecialchars($detail['user_branch']); ?>
                    </span>
                </div>
            </div>
            
            <div>
                <div style="font-size:0.72rem;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">Date Submitted</div>
                <div style="font-weight:500;color:#1e293b;font-size:0.9rem;"><?php echo date('M d, Y H:i', strtotime($detail['created_at'])); ?></div>
            </div>
        </div>
        <div class="glass-card" style="display:flex;flex-direction:column;justify-content:space-between;">
            <div>
                <h4 style="margin-bottom:1.25rem;color:#0891b2;font-size:1rem;display:flex;align-items:center;gap:0.5rem;border-bottom:1px solid #f1f5f9;padding-bottom:0.5rem;"><i class="fas fa-star" style="color:#f59e0b;"></i> Recognized Personnel</h4>
                <div style="background:linear-gradient(135deg,#fef9c3,#fef08a);color:#854d0e;padding:0.75rem 1.25rem;border-radius:0.5rem;font-size:1.1rem;font-weight:800;display:inline-flex;align-items:center;gap:0.5rem;margin-bottom:1.5rem;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
                    <i class="fas fa-award" style="font-size:1.4rem;color:#d97706;"></i>
                    <?php echo htmlspecialchars($detail['staff_name']); ?>
                </div>
                
                <h4 style="margin-bottom:0.75rem;color:#64748b;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;">Message of Appreciation</h4>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;padding:1.5rem;border-radius:0.75rem;font-style:italic;color:#334155;font-size:1.05rem;line-height:1.6;position:relative;">
                    <span style="font-size:3rem;position:absolute;left:0.5rem;top:-0.5rem;color:#cbd5e1;font-family:serif;user-select:none;pointer-events:none;">&ldquo;</span>
                    <span style="position:relative;z-index:1;padding-left:1rem;display:block;"><?php echo htmlspecialchars($detail['appreciation']); ?></span>
                </div>
            </div>
            
            <div style="margin-top:2rem;text-align:right;">
                <a href="appreciations.php" class="btn" style="background:#0891b2;color:white;text-decoration:none;padding:0.6rem 2rem;border-radius:0.3rem;">Back to List</a>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="fade-in">
    <div style="margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.75rem;color:#0891b2;margin-bottom:0.2rem;">
                <i class="fas fa-star" style="color:#f59e0b;"></i> Commendations & Appreciations
            </h1>
            <p style="font-size:0.85rem;color:#64748b;margin:0;">Staff recognition submitted by members.</p>
        </div>
        <div style="background:linear-gradient(135deg,#0891b2,#0e7490);color:white;padding:0.5rem 1.25rem;border-radius:0.75rem;font-weight:700;font-size:1rem;">
            <?php echo $total; ?> Record<?php echo $total !== 1 ? 's' : ''; ?>
        </div>
    </div>

    <!-- Filter Row -->
    <form class="admin-filter-row" method="GET" action="appreciations.php">
        <div class="admin-filter-group">
            <label class="admin-filter-label">Branch</label>
            <select name="branch" class="admin-filter-input">
                <option value="">All Branches</option>
                <?php
                $branches_arr = [];
                while ($b = $branches->fetch_assoc()) $branches_arr[] = $b;
                foreach ($branches_arr as $b): ?>
                <option value="<?php echo htmlspecialchars($b['name']); ?>"
                    <?php echo $branch_filter === $b['name'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($b['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-filter-group">
            <label class="admin-filter-label">Staff Name</label>
            <input type="text" name="staff" class="admin-filter-input"
                value="<?php echo htmlspecialchars($staff_filter); ?>" placeholder="Search staff...">
        </div>
        <div class="admin-filter-group">
            <label class="admin-filter-label">Date From</label>
            <input type="date" name="dateFrom" class="admin-filter-input"
                value="<?php echo htmlspecialchars($date_from); ?>">
        </div>
        <div class="admin-filter-group">
            <label class="admin-filter-label">Date To</label>
            <input type="date" name="dateTo" class="admin-filter-input"
                value="<?php echo htmlspecialchars($date_to); ?>">
        </div>
        <button type="submit" class="btn" style="background:#0891b2;color:white;border:none;padding:0.5rem 2rem;">Filter</button>
        <a href="appreciations.php" class="btn btn-outline" style="padding:0.5rem 1rem;">Clear</a>
    </form>

    <!-- Table -->
    <div class="admin-table-wrapper">
        <div class="admin-table-header" style="background:linear-gradient(90deg,#0891b2,#0e7490);">
            <i class="fas fa-star"></i> Appreciation Records
            <span style="margin-left:auto;background:rgba(255,255,255,0.25);border-radius:9px;padding:0.1rem 0.55rem;font-size:0.75rem;"><?php echo $total; ?></span>
        </div>
        <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Member</th>
                    <th>Branch</th>
                    <th>Staff Recognized</th>
                    <th>Appreciation Message</th>
                    <th>Date Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($total > 0): ?>
                    <?php while ($row = $appreciations->fetch_assoc()): ?>
                    <tr>
                        <td style="font-weight:bold;color:#64748b;">#<?php echo $row['id']; ?></td>
                        <td>
                            <div style="font-weight:600;"><?php echo htmlspecialchars($row['user_name']); ?></div>
                            <div style="font-size:0.7rem;color:#64748b;"><?php echo htmlspecialchars($row['user_phone']); ?></div>
                        </td>
                        <td>
                            <span style="background:#e0f2fe;color:#0369a1;padding:0.15rem 0.45rem;border-radius:0.25rem;font-size:0.75rem;font-weight:700;">
                                <?php echo htmlspecialchars($row['user_branch']); ?>
                            </span>
                        </td>
                        <td>
                            <span style="display:inline-flex;align-items:center;gap:0.35rem;background:linear-gradient(135deg,#fef9c3,#fef08a);color:#854d0e;padding:0.25rem 0.65rem;border-radius:0.4rem;font-size:0.82rem;font-weight:700;">
                                <i class="fas fa-star" style="font-size:0.75rem;color:#d97706;"></i>
                                <?php echo htmlspecialchars($row['staff_name']); ?>
                            </span>
                        </td>
                        <td style="font-style:italic;color:#334155;max-width:260px;">
                            "<?php echo htmlspecialchars($row['appreciation']); ?>"
                        </td>
                        <td style="font-size:0.8rem;white-space:nowrap;"><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
                        <td>
                            <a href="appreciations.php?view=<?php echo $row['id']; ?>" class="btn btn-outline" style="padding:0.2rem 0.6rem;font-size:0.8rem;border-radius:2rem;">View</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:2.5rem;color:#94a3b8;">
                            <i class="fas fa-star" style="font-size:1.8rem;display:block;margin-bottom:0.5rem;color:#fbbf24;opacity:0.4;"></i>
                            No appreciation records found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/admin_footer.php'; ?>
