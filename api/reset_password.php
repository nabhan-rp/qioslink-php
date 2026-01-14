<?php
// FILE: api/reset_password.php
// INSTRUKSI: Upload file ini ke folder /api/

require_once __DIR__ . '/db_connect.php';

header("Content-Type: application/json");
$input = json_decode(file_get_contents("php://input"), true);

if (isset($input['email']) && isset($input['otp']) && isset($input['new_password'])) {
    
    $email = $conn->real_escape_string($input['email']);
    $otp = $conn->real_escape_string($input['otp']);
    $new_pass = $input['new_password']; // Plain text as per existing system architecture
    
    // 1. Verifikasi OTP & Email
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND verification_code = ?");
    $stmt->bind_param("ss", $email, $otp);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        // 2. Update Password & Clear OTP
        $update = $conn->prepare("UPDATE users SET password = ?, verification_code = NULL WHERE email = ?");
        $update->bind_param("ss", $new_pass, $email);
        
        if ($update->execute()) {
            echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error during update']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP Code']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Missing Input Data']);
}
?>