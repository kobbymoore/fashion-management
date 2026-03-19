<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$db = getDB();

echo "Starting Migration: Custom Orders Support...\n";

try {
    // Add custom order fields to orders table
    $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS is_custom BOOLEAN DEFAULT FALSE");
    $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS custom_image VARCHAR(255)");
    $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS custom_voice VARCHAR(255)");
    $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS custom_description TEXT");
    
    echo "Migration Successful!\n";
} catch (Exception $e) {
    echo "Migration Failed: " . $e->getMessage() . "\n";
}
