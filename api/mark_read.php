<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireLogin();

header('Content-Type: application/json');

try {
    $db = getDB();
    $user = currentUser();
    $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user['id']]);
    
    echo json_encode(['success' => true, 'count' => $stmt->rowCount()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
