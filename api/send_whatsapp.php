
<?php
// FILE: api/send_whatsapp.php
// INSTRUKSI: Upload file ini ke folder /api/

require 'db_connect.php';

header("Content-Type: application/json");
$input = json_decode(file_get_contents("php://input"), true);

// Parameter: user_id (untuk 2FA) atau phone (untuk Login OTP)
$targetPhone = '';
$otpCode = rand(100000, 999999);

// CASE 1: Kirim OTP berdasarkan ID (Resend/2FA)
if (isset($input['user_id'])) {
    $user_id = $input['user_id'];
    $res = $conn->query("SELECT phone FROM users WHERE id = $user_id");
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $targetPhone = $row['phone'];
        // Update Code di DB
        $conn->query("UPDATE users SET verification_code = '$otpCode' WHERE id = $user_id");
    }
}
// CASE 2: Kirim OTP berdasarkan No HP (Login Awal)
else if (isset($input['phone'])) {
    $targetPhone = $input['phone'];
    // Cek apakah user ada
    $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->bind_param("s", $targetPhone);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $conn->query("UPDATE users SET verification_code = '$otpCode' WHERE id = " . $row['id']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Phone number not registered']);
        exit;
    }
}

if (empty($targetPhone)) {
    echo json_encode(['success' => false, 'message' => 'Target phone not found']);
    exit;
}

// AMBIL CONFIG DARI SUPERADMIN (ID 1)
$resAdmin = $conn->query("SELECT merchant_config FROM users WHERE id = 1");
$adminRow = $resAdmin->fetch_assoc();
$config = json_decode($adminRow['merchant_config'], true);
$authConf = $config['auth'] ?? [];

$provider = $authConf['waProvider'] ?? 'fonnte';
$message = "*QiosLink Security*\nKode OTP Anda: *$otpCode*\nJangan berikan kode ini kepada siapapun.";

try {
    if ($provider === 'fonnte') {
        $token = $authConf['fonnteToken'] ?? '';
        if (empty($token)) throw new Exception("Fonnte Token missing");

        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://api.fonnte.com/send',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => array(
            'target' => $targetPhone,
            'message' => $message,
          ),
          CURLOPT_HTTPHEADER => array(
            "Authorization: $token"
          ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        // Validasi response Fonnte jika perlu
    } 
    else if ($provider === 'meta') {
        // Implementasi Meta Cloud API
        // Butuh: PhoneID, AccessToken
        // Endpoint: https://graph.facebook.com/v17.0/PHONE_ID/messages
        // Payload: messaging_product="whatsapp", to=$targetPhone, type="text", text={body: $message}
    }

    echo json_encode(['success' => true, 'message' => 'OTP Sent via ' . $provider]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>