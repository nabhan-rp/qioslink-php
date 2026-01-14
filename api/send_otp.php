<?php
require 'db_connect.php';

// Terima Input: { phone: "628...", user_id: 1, action: "login"|"2fa" }
$input = json_decode(file_get_contents("php://input"), true);

$targetPhone = '';
$userId = null;

// Skenario 1: Login by Phone (Passwordless)
if (isset($input['phone'])) {
    $cleanPhone = preg_replace('/[^0-9]/', '', $input['phone']);
    // Cek apakah user ada
    $stmt = $conn->prepare("SELECT id, phone, wa_login_enabled, role FROM users WHERE phone = ?");
    $stmt->bind_param("s", $cleanPhone);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        // Cek permission WA Login
        // Jika role Admin/Merchant, pastikan wa_login_enabled = 1 demi keamanan
        if (in_array($row['role'], ['superadmin','merchant']) && $row['wa_login_enabled'] == 0) {
            echo json_encode(['success' => false, 'message' => 'WhatsApp login disabled for this account']); exit;
        }
        $userId = $row['id'];
        $targetPhone = $cleanPhone;
    } else {
        echo json_encode(['success' => false, 'message' => 'Phone number not found']); exit;
    }
} 
// Skenario 2: 2FA Request (User sudah login password, tapi butuh 2FA)
else if (isset($input['user_id'])) {
    $userId = $input['user_id'];
    $res = $conn->query("SELECT phone FROM users WHERE id = $userId");
    if ($res->num_rows > 0) {
        $targetPhone = $res->fetch_assoc()['phone'];
    }
}

if (empty($targetPhone) || empty($userId)) {
    echo json_encode(['success' => false, 'message' => 'Target invalid']); exit;
}

// Generate OTP
$otp = rand(100000, 999999);
$conn->query("UPDATE users SET verification_code = '$otp' WHERE id = $userId");

// Ambil Config Gateway dari Superadmin
$adminConf = $conn->query("SELECT merchant_config FROM users WHERE id = 1")->fetch_assoc();
$conf = json_decode($adminConf['merchant_config'], true);
$waConf = $conf['auth'] ?? [];

$provider = $waConf['waProvider'] ?? 'fonnte';
$message = "*QiosLink Security Code*\n\nKode OTP Anda: *$otp*\n\nJangan berikan kode ini kepada siapapun.";

// Kirim
try {
    if ($provider === 'fonnte') {
        $token = $waConf['fonnteToken'] ?? '';
        if (empty($token)) throw new Exception("Fonnte Token Not Configured");

        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://api.fonnte.com/send',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_POST => true,
          CURLOPT_POSTFIELDS => array(
            'target' => $targetPhone,
            'message' => $message,
          ),
          CURLOPT_HTTPHEADER => array("Authorization: $token"),
        ));
        $resp = curl_exec($curl);
        curl_close($curl);
    }
    // Tambahkan blok 'else if ($provider === 'meta')' jika perlu
    
    echo json_encode(['success' => true, 'message' => 'OTP Sent']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Sending Failed: ' . $e->getMessage()]);
}
?>