<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();
try {
    // Check if column exists first
    $stmt = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name='orders' AND column_name='custom_fabric'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE orders ADD COLUMN custom_fabric TEXT NULL");
        echo "Successfully added 'custom_fabric' column to 'orders' table.";
    } else {
        echo "'custom_fabric' column already exists.";
    }
} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage();
}
// unlink(__FILE__); // Keep it for verification this time
