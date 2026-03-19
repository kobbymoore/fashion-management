<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(BASE_URL . '/customer/order_history.php');

$db = getDB();
$user = currentUser();
$orderId = (int)($_POST['order_id'] ?? 0);

// Verify order belongs to customer and is pending
$stmt = $db->prepare("SELECT id, status FROM orders WHERE id = ? AND customer_id = (SELECT id FROM customers WHERE user_id = ?)");
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('danger', 'Order not found.');
} elseif ($order['status'] !== 'pending') {
    setFlash('danger', 'Only pending orders can be cancelled.');
} else {
    // Update status to cancelled
    $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
    auditLog('cancel_order', "Customer cancelled Order #$orderId");
    setFlash('success', 'Order cancelled successfully.');
}

redirect(BASE_URL . '/customer/order_history.php');
?>
