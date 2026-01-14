
<?php
// FILE: api/kyc_callback.php
// INSTRUKSI: Upload file ini ke folder /api/ di hosting Anda.

require 'db_connect.php';

// 1. Ambil Data Webhook
$input = file_get_contents("php://input");
$event = json_decode($input, true);

// 2. Logging
$logMsg = date('Y-m-d H:i:s') . " | WEBHOOK: " . $input . "\n";
file_put_contents('kyc_log.txt', $logMsg, FILE_APPEND);

if (!$event) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

// 3. Proses Logika Didit.me
$data = $event['data'] ?? [];
$status = $data['status'] ?? ''; 
$decision = $data['decision'] ?? '';

// Cek apakah verifikasi disetujui
$isApproved = ($status === 'approved' || $decision === 'approved');

if ($isApproved) {
    $userId = null;
    
    if (isset($data['metadata']['internal_user_id'])) {
        $userId = $data['metadata']['internal_user_id'];
    } 
    
    if ($userId) {
        // UPDATE LOGIC: Set is_kyc_verified = 1 DAN role = 'merchant'
        $stmt = $conn->prepare("UPDATE users SET is_kyc_verified = 1, role = 'merchant', kyc_data = ? WHERE id = ?");
        $kycDataStr = json_encode($data);
        $stmt->bind_param("si", $kycDataStr, $userId);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => "User ID $userId verified and upgraded to Merchant"]);
        } else {
            echo json_encode(['status' => 'error', 'message' => "DB Update Failed"]);
        }
    } else {
        echo json_encode(['status' => 'ignored', 'message' => 'No User ID found in metadata']);
    }
} else {
    echo json_encode(['status' => 'ignored', 'message' => 'Verification not approved']);
}
?>