<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();
try {
    $db->exec("ALTER TABLE orders ADD COLUMN custom_fabric TEXT NULL");
    echo "Success: custom_fabric column added to orders table.";
} catch (Exception $e) {
    echo "Error or already exists: " . $e->getMessage();
}
unlink(__FILE__);
