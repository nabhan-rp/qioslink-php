<?php
require 'db_connect.php';
require 'simple_smtp.php'; // WAJIB ADA

$input = json_decode(file_get_contents("php://input"), true);

if(isset($input['username']) && isset($input['password'])) {
    $username = $conn->real_escape_string($input['username']);
    $email = isset($input['email']) ? $conn->real_escape_string($input['email']) : '';
    $password = $input['password'];
    $confirmPass = isset($input['confirmPassword']) ? $input['confirmPassword'] : $password;
    
    if ($password !== $confirmPass) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']); exit;
    }

    $role = 'user'; 
    $creator_id = 1; // Default Superadmin

    // Cek Duplicate
    $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
    if($check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']); exit;
    }

    // Ambil Config SMTP dari Superadmin
    $stmtConfig = $conn->prepare("SELECT merchant_config FROM users WHERE id = ?");
    $stmtConfig->bind_param("i", $creator_id);
    $stmtConfig->execute();
    $resConfig = $stmtConfig->get_result();
    $creatorData = $resConfig->fetch_assoc();
    $creatorConf = json_decode($creatorData['merchant_config'], true);

    $requireVerification = false;
    $smtpConfig = null;

    if (isset($creatorConf['smtp']) && isset($creatorConf['smtp']['requireEmailVerification']) && $creatorConf['smtp']['requireEmailVerification'] === true) {
        $requireVerification = true;
        $smtpConfig = $creatorConf['smtp'];
    }

    $is_verified = $requireVerification ? 0 : 1;
    $otp_code = $requireVerification ? rand(100000, 999999) : null;

    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, creator_id, is_verified, verification_code) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssiss", $username, $email, $password, $role, $creator_id, $is_verified, $otp_code);
    
    if($stmt->execute()) {
        $sendMsg = null;
        if ($requireVerification && $smtpConfig) {
            try {
                // PASSING PARAMETER $secure ('tls', 'ssl', 'none')
                $secure = isset($smtpConfig['secure']) ? $smtpConfig['secure'] : 'tls';
                $mail = new SimpleSMTP($smtpConfig['host'], $smtpConfig['port'], $smtpConfig['user'], $smtpConfig['pass'], $secure);
                
                $body = "
                    <div style='font-family: sans-serif; padding: 20px; border: 1px solid #ddd;'>
                        <h2>Welcome to QiosLink</h2>
                        <p>Your verification code is:</p>
                        <h1 style='color: #4f46e5; letter-spacing: 5px;'>$otp_code</h1>
                        <p>Hosted on JajanServer Infrastructure.</p>
                    </div>
                ";
                $mail->send($email, "Verify Your Account", $body, $smtpConfig['fromEmail'], $smtpConfig['fromName']);
            } catch (Exception $e) {
                $sendMsg = " (Email Failed: " . $e->getMessage() . ")";
            }
        }
        echo json_encode(['success' => true, 'message' => 'Registration successful' . $sendMsg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
}
?>