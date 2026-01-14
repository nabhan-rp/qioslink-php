<?php
// FILE: api/forgot_password.php
// INSTRUKSI: Upload file ini ke folder /api/

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/simple_smtp.php';

header("Content-Type: application/json");
$input = json_decode(file_get_contents("php://input"), true);

if (isset($input['email'])) {
    $email = $conn->real_escape_string($input['email']);
    
    // 1. Cek apakah email ada
    $check = $conn->query("SELECT id FROM users WHERE email = '$email'");
    if ($check->num_rows > 0) {
        
        // 2. Generate OTP
        $otp = rand(100000, 999999);
        $conn->query("UPDATE users SET verification_code = '$otp' WHERE email = '$email'");
        
        // 3. Ambil Config SMTP dari Superadmin (ID 1)
        // Kita butuh config sistem untuk mengirim email ke user public
        $adminRes = $conn->query("SELECT merchant_config FROM users WHERE id = 1");
        $adminData = $adminRes->fetch_assoc();
        $adminConfig = json_decode($adminData['merchant_config'], true);
        
        if (isset($adminConfig['smtp'])) {
            $conf = $adminConfig['smtp'];
            
            try {
                $secure = isset($conf['secure']) ? $conf['secure'] : 'tls';
                $fromEmail = isset($conf['fromEmail']) && !empty($conf['fromEmail']) ? $conf['fromEmail'] : 'noreply@' . $_SERVER['HTTP_HOST'];
                $fromName = isset($conf['fromName']) && !empty($conf['fromName']) ? $conf['fromName'] : 'QiosLink Security';

                $mail = new SimpleSMTP($conf['host'], $conf['port'], $conf['user'], $conf['pass'], $secure);
                
                $body = "
                <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 10px; max-width: 500px;'>
                    <h2 style='color: #4f46e5;'>Reset Password Request</h2>
                    <p>You requested to reset your password. Use the code below:</p>
                    <h1 style='letter-spacing: 5px; color: #333; background: #f3f4f6; padding: 10px; text-align: center; border-radius: 5px;'>$otp</h1>
                    <p>If you did not request this, please ignore this email.</p>
                    <hr>
                    <small style='color: #999;'>Sent by QiosLink System</small>
                </div>";
                
                $mail->send($email, "Reset Password OTP", $body, $fromEmail, $fromName);
                
                echo json_encode(['success' => true, 'message' => 'OTP Code sent to your email']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'SMTP Error: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'System SMTP not configured. Contact Admin.']);
        }
    } else {
        // Demi keamanan, tetap return success meski email tidak ada (User Enumeration Prevention)
        // Tapi untuk aplikasi internal/SaaS kecil, boleh jujur.
        echo json_encode(['success' => false, 'message' => 'Email not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Input']);
}
?>
