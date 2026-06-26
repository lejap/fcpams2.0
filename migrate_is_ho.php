<?php
// One-time migration: add is_ho column to branches table
// Run this ONCE via browser: https://fcpams.bansalancoop.com/migrate_is_ho.php
// Then delete this file.

require_once 'config/db.php';

try {
    // Run standard SQL (works on both MySQL and MariaDB)
    $sql = "ALTER TABLE branches ADD COLUMN is_ho TINYINT(1) NOT NULL DEFAULT 0";
    if ($conn->query($sql)) {
        echo "<p style='color:green;font-family:sans-serif;font-size:1.1rem;'>✅ Migration successful: <code>is_ho</code> column added to <code>branches</code> table.</p>";
    } else {
        throw new Exception($conn->error);
    }
} catch (Throwable $e) {
    // Catch if the column already exists (MySQL error code 1060 / Duplicate column name)
    if ($conn->errno === 1060 || strpos(strtolower($e->getMessage()), 'duplicate column') !== false) {
        echo "<p style='color:blue;font-family:sans-serif;font-size:1.1rem;'>ℹ️ Database already up-to-date! The <code>is_ho</code> column already exists.</p>";
    } else {
        echo "<p style='color:red;font-family:sans-serif;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "<p style='font-family:sans-serif;'>You can now delete this file. <a href='admin/branches.php'>Go to Branches</a></p>";
