
<?php
// FILE: api/login.php
// INSTRUKSI: Replace file api/login.php Anda dengan ini.

require 'db_connect.php';

$input = json_decode(file_get_contents("php://input"), true);

if(isset($input['username']) && isset($input['password'])) {
    $user = $input['username'];
    $pass = $input['password'];
    
    // Support login via Email or Username
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.email, u.phone, u.password, u.role, u.merchant_config, 
               u.is_verified, u.is_phone_verified, u.two_factor_enabled, u.creator_id
        FROM users u 
        WHERE u.username = ? OR u.email = ?
    ");
    
    $stmt->bind_param("ss", $user, $user);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Validasi Password (Plain text untuk demo, di production gunakan password_verify)
        // Jika password di DB null (user social login), dia tidak bisa login via password
        if ($row['password'] !== $pass) {
            echo json_encode(['success' => false, 'message' => 'Invalid Password']);
            exit;
        }

        // --- 2FA CHECK LOGIC ---
        // Jika user mengaktifkan 2FA, jangan kembalikan data user lengkap.
        // Kembalikan flag 'require_2fa' agar frontend minta OTP.
        if ($row['two_factor_enabled'] == 1) {
            if (empty($row['phone'])) {
                // Edge case: 2FA aktif tapi no HP kosong -> Bypass atau Error?
                // Kita beri error agar dia hubungi admin
                echo json_encode(['success' => false, 'message' => '2FA Enabled but no Phone Number configured. Contact Admin.']);
                exit;
            }
            
            // Trigger kirim OTP otomatis disini (Opsional, atau biarkan frontend yang request)
            // Kita return saja ID dan Phone tersensor
            $maskedPhone = substr($row['phone'], 0, 4) . "****" . substr($row['phone'], -3);
            
            echo json_encode([
                'success' => false, 
                'require_2fa' => true,
                'user_id' => $row['id'],
                'phone_masked' => $maskedPhone,
                'message' => 'Two-Factor Authentication Required'
            ]);
            exit;
        }

        // Jika lolos 2FA atau tidak ada 2FA
        $row['merchantConfig'] = json_decode($row['merchant_config']);
        unset($row['merchant_config']);
        unset($row['password']); // Jangan kirim password
        
        $row['isVerified'] = $row['is_verified'] == 1;
        $row['isPhoneVerified'] = $row['is_phone_verified'] == 1;
        $row['twoFactorEnabled'] = $row['two_factor_enabled'] == 1;
        
        echo json_encode(['success' => true, 'user' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Missing Input']);
}
?>