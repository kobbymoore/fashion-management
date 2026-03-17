<?php
/**
 * Fashion Studio GH – Password Reset Utility
 * Run ONCE via: http://localhost/fashion%20mgt%20system/fix_passwords.php
 * DELETE this file when done.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

$newPassword = 'Admin@1234';
$hash        = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

$db   = getDB();
$stmt = $db->prepare("UPDATE users SET email = 'kobbymoore02@gmail.com', password = ? WHERE role = 'admin'");
$stmt->execute([$hash]);

$stmt2 = $db->prepare("UPDATE users SET password = ? WHERE role = 'staff'");
$stmt2->execute([$hash]);

$affected = $stmt->rowCount();
echo "<h2>Done!</h2>";
echo "<p>Updated password hash for <strong>{$affected}</strong> user(s).</p>";
echo "<p>All accounts now use password: <code>{$newPassword}</code></p>";
echo "<p style='color:red'><strong>DELETE this file immediately after use!</strong></p>";
