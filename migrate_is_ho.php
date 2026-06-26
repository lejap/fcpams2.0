<?php
// One-time migration: add is_ho column to branches table
// Run this ONCE via browser: http://localhost/fcpamsweb/migrate_is_ho.php
// Then delete this file.

require_once 'config/db.php';

$sql = "ALTER TABLE branches ADD COLUMN IF NOT EXISTS is_ho TINYINT(1) NOT NULL DEFAULT 0";
if ($conn->query($sql)) {
    echo "<p style='color:green;font-family:sans-serif;font-size:1.1rem;'>✅ Migration successful: <code>is_ho</code> column added to <code>branches</code> table.</p>";
    echo "<p style='font-family:sans-serif;'>You can now delete this file. <a href='admin/branches.php'>Go to Branches</a></p>";
} else {
    echo "<p style='color:red;font-family:sans-serif;'>❌ Error: " . htmlspecialchars($conn->error) . "</p>";
}
