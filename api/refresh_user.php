
<?php
// FILE: api/refresh_user.php
// INSTRUKSI: Upload file ini ke folder /api/

require 'db_connect.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);

if (isset($input['user_id'])) {
    $user_id = $input['user_id'];
    
    // Query data terbaru dari DB
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.email, u.phone, u.role, u.merchant_config, 
               u.is_verified, u.is_phone_verified, u.is_kyc_verified, u.two_factor_enabled, u.creator_id
        FROM users u 
        WHERE u.id = ?
    ");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Format Data
        $row['merchantConfig'] = json_decode($row['merchant_config']);
        unset($row['merchant_config']);
        
        // Konversi Boolean (PENTING untuk UI React)
        $row['isVerified'] = $row['is_verified'] == 1;
        $row['isPhoneVerified'] = $row['is_phone_verified'] == 1;
        $row['isKycVerified'] = $row['is_kyc_verified'] == 1; // FIX 2: Pastikan status KYC terkirim
        $row['twoFactorEnabled'] = $row['two_factor_enabled'] == 1;
        
        echo json_encode(['success' => true, 'user' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
}
?>