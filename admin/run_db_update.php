<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireAdmin(); // Only admins can run this

$db = getDB();
echo "<h2>Database Schema Update</h2>";
try {
    $stmt = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name='orders' AND column_name='custom_fabric'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE orders ADD COLUMN custom_fabric TEXT NULL");
        echo "<p style='color:green'>Success: Added 'custom_fabric' column to 'orders'.</p>";
    } else {
        echo "<p>'custom_fabric' column already exists.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
echo "<a href='../admin/dashboard.php'>Back to Dashboard</a>";
