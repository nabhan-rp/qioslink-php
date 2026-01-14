<?php
// Pastikan file db_connect.php ada di folder yang sama
require_once __DIR__ . '/db_connect.php';
// Pastikan file simple_smtp.php ada di folder yang sama
require_once __DIR__ . '/simple_smtp.php'; 

header("Content-Type: application/json");
$input = json_decode(file_get_contents("php://input"), true);

if (isset($input['user_id'])) {
    $user_id = $input['user_id'];
    
    $sql = "SELECT u.email, u.creator_id, c.merchant_config FROM users u LEFT JOIN users c ON u.creator_id = c.id WHERE u.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $data = $res->fetch_assoc();
        $email = $data['email'];
        // Config SMTP diambil dari Creator (Superadmin/Merchant)
        $creatorConf = json_decode($data['merchant_config'], true);
        
        $new_code = rand(100000, 999999);
        $upd = $conn->prepare("UPDATE users SET verification_code = ? WHERE id = ?");
        $upd->bind_param("si", $new_code, $user_id);
        $upd->execute();
        
        if (isset($creatorConf['smtp'])) {
            $conf = $creatorConf['smtp'];
            try {
                $secure = isset($conf['secure']) ? $conf['secure'] : 'tls';
                
                // Fallback jika fromEmail kosong
                $fromEmail = isset($conf['fromEmail']) ? $conf['fromEmail'] : 'noreply@' . $_SERVER['HTTP_HOST'];
                $fromName = isset($conf['fromName']) ? $conf['fromName'] : 'QiosLink Security';

                $mail = new SimpleSMTP($conf['host'], $conf['port'], $conf['user'], $conf['pass'], $secure);
                
                $body = "
                <div style='font-family: Arial, sans-serif;'>
                    <h2>Verification Code</h2>
                    <p>Use the code below to verify your account:</p>
                    <h1 style='letter-spacing: 5px; color: #4f46e5;'>$new_code</h1>
                    <p>If you didn't request this, please ignore.</p>
                </div>";
                
                $mail->send($email, "Resend OTP", $body, $fromEmail, $fromName);
                echo json_encode(['success' => true, 'message' => 'OTP Resent Successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'SMTP Error: ' . $e->getMessage()]);
            }
        } else {
             echo json_encode(['success' => false, 'message' => 'SMTP not configured by Admin']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
}
?>