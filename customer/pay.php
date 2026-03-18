<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$user = currentUser();

$stmt = $db->prepare("SELECT o.*, u.email FROM orders o JOIN users u ON o.customer_id = (SELECT id FROM customers WHERE user_id = u.id) WHERE o.id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order || ($order['payment_status'] ?? '') === 'paid') {
    setFlash('danger', 'Invalid order or already paid.');
    redirect(BASE_URL . '/customer/order_history.php');
}

// Paystack Integration
$url = "https://api.paystack.co/transaction/initialize";
$fields = [
  'email' => $order['email'],
  'amount' => $order['total_amount'] * 100, // Paystack uses Kobo/Pesewas
  'callback_url' => (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . BASE_URL . "/customer/verify.php",
  'metadata' => [
      'order_id' => $id,
      'customer_name' => $user['name']
  ]
];

$fields_string = http_build_query($fields);
$ch = curl_init();
curl_setopt($ch,CURLOPT_URL, $url);
curl_setopt($ch,CURLOPT_POST, true);
curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
curl_setopt($ch,CURLOPT_HTTPHEADER, array(
  "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
  "Cache-Control: no-cache",
));
curl_setopt($ch,CURLOPT_RETURNTRANSFER, true); 

$result = curl_exec($ch);
$response = json_decode($result, true);

if ($response['status']) {
    // Generate transaction reference locally for tracking if needed
    $ref = $response['data']['reference'];
    $db->prepare("UPDATE orders SET tx_ref = ? WHERE id = ?")->execute([$ref, $id]);
    
    // Redirect to Paystack
    header('Location: ' . $response['data']['authorization_url']);
} else {
    setFlash('danger', 'Could not initialize payment: ' . ($response['message'] ?? 'Unknown error'));
    redirect(BASE_URL . '/customer/order_history.php');
}
?>
