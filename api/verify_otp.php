<?php
require 'db_connect.php';

// Input: { user_id: 1, code: "123456", phone: "..." }
$input = json_decode(file_get_contents("php://input"), true);

if ((isset($input['user_id']) || isset($input['phone'])) && isset($input['code'])) {
    
    $code = $input['code'];
    
    // Cari user
    $sql = "SELECT * FROM users WHERE verification_code = ? AND ";
    if (isset($input['user_id'])) {
        $sql .= "id = " . intval($input['user_id']);
    } else {
        $phone = preg_replace('/[^0-9]/', '', $input['phone']);
        $sql .= "phone = '$phone'";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        
        // Clear OTP & Set Phone Verified
        $conn->query("UPDATE users SET verification_code = NULL, is_phone_verified = 1 WHERE id = " . $user['id']);
        
        // Return User Data (Login Success)
        $user['merchantConfig'] = json_decode($user['merchant_config']);
        unset($user['merchant_config']);
        unset($user['password']);
        $user['isVerified'] = $user['is_verified'] == 1;
        $user['isPhoneVerified'] = true; // Auto true
        $user['twoFactorEnabled'] = $user['two_factor_enabled'] == 1;
        
        echo json_encode(['success' => true, 'user' => $user]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP Code']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Missing Data']);
}
?>