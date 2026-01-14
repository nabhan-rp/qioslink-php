<?php
require 'db_connect.php';

$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : 0;
$role = isset($_GET['role']) ? $_GET['role'] : 'user';

if ($role === 'superadmin') {
    $sql = "SELECT id, trx_id as id_trx, amount, description, status, created_at, qr_string, payment_url FROM transactions ORDER BY created_at DESC LIMIT 100";
    $stmt = $conn->prepare($sql);
} else {
    $sql = "SELECT id, trx_id as id_trx, amount, description, status, created_at, qr_string, payment_url FROM transactions WHERE merchant_id = ? ORDER BY created_at DESC LIMIT 100";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
$transactions = [];

while($row = $result->fetch_assoc()) {
    $transactions[] = [
        'id' => $row['id_trx'], 
        'merchantId' => (string)$user_id,
        'amount' => $row['amount'],
        'description' => $row['description'],
        'status' => $row['status'],
        'createdAt' => $row['created_at'],
        'qrString' => $row['qr_string'],
        'paymentUrl' => $row['payment_url']
    ];
}

echo json_encode(['success' => true, 'transactions' => $transactions]);
?>
