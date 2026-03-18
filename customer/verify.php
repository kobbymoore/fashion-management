<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireLogin();

$ref = $_GET['reference'] ?? '';
if (!$ref) {
    setFlash('danger', 'No payment reference provided.');
    redirect(BASE_URL . '/customer/order_history.php');
}

$url = "https://api.paystack.co/transaction/verify/" . rawurlencode($ref);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
  "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
  "Cache-Control: no-cache",
));
$result = curl_exec($ch);
$response = json_decode($result, true);

if ($response['status'] && $response['data']['status'] === 'success') {
    $db = getDB();
    $orderId = $response['data']['metadata']['order_id'];
    $amount = $response['data']['amount'] / 100;

    // Update Order Status
    $db->prepare("UPDATE orders SET payment_status = 'paid', status = 'approved' WHERE id = ?")
       ->execute([$orderId]);

    // Record in Sales
    $db->prepare("INSERT INTO sales (order_id, amount, payment_method, sale_date, payment_reference) VALUES (?, ?, 'mobile_money', CURRENT_DATE, ?)")
       ->execute([$orderId, $amount, $ref]);

    auditLog('payment_success', "Payment successful for Order #$orderId. Ref: $ref");
    setFlash('success', 'Payment successful! Your order is now being processed.');
} else {
    setFlash('danger', 'Payment verification failed or was cancelled.');
}

redirect(BASE_URL . '/customer/order_history.php');
?>
