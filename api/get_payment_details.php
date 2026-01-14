<?php
require 'db_connect.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Token required']);
    exit;
}

// Cari transaksi berdasarkan Token URL
$stmt = $conn->prepare("
    SELECT t.*, u.merchant_config 
    FROM transactions t 
    JOIN users u ON t.merchant_id = u.id 
    WHERE t.payment_token = ? LIMIT 1
");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $trx = $result->fetch_assoc();
    $merchantConfig = json_decode($trx['merchant_config'], true);
    
    // Cek apakah Link Kadaluarsa
    if ($trx['status'] === 'pending' && $trx['expires_at'] && strtotime($trx['expires_at']) < time()) {
        $conn->query("UPDATE transactions SET status = 'expired' WHERE id = " . $trx['id']);
        $trx['status'] = 'expired';
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'trx_id' => $trx['trx_id'],
            'amount' => $trx['amount'],
            'description' => $trx['description'],
            'status' => $trx['status'],
            'qr_string' => $trx['qr_string'],
            'expires_at' => $trx['expires_at'],
            // Kirim data branding merchant (Logo/Warna) agar tampilan sesuai pemilik link
            'merchant_name' => $merchantConfig['merchantName'] ?? 'QiosLink Merchant',
            'branding' => $merchantConfig['branding'] ?? null
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Link Invalid or Not Found']);
}
?>