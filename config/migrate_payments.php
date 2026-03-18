<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$db = getDB();

echo "Starting Migration: Payments Support...\n";

try {
    // 1. Add payment fields to orders table
    $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_status VARCHAR(20) DEFAULT 'unpaid'");
    $db->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS tx_ref VARCHAR(100) UNIQUE");
    
    // 2. Add payment reference to sales table
    $db->exec("ALTER TABLE sales ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(100)");
    
    echo "Migration Successful!\n";
} catch (Exception $e) {
    echo "Migration Failed: " . $e->getMessage() . "\n";
}
