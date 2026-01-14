
<?php
// FILE: api/forgot_password.php
// INSTRUKSI: Upload file ini ke folder /api/

require 'db_connect.php';
require_once 'simple_smtp.php'; // Pastikan sudah ada

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents("php://input"), true);

// --- 1. CHECK ACCOUNT (IDENTIFIER) ---
if ($action === 'check') {
    $id = $conn->real_escape_string($input['identifier'] ?? '');
    if(empty($id)) { echo json_encode(['success'=>false, 'message'=>'Input required']); exit; }

    // Cek Username atau Email
    $sql = "SELECT id, email, phone, is_phone_verified FROM users WHERE username = '$id' OR email = '$id'";
    $res = $conn->query($sql);

    if($res->num_rows > 0) {
        $u = $res->fetch_assoc();
        
        // Masking Data
        $email_masked = preg_replace('/(?<=.).(?=.*@)/', '*', $u['email']);
        $phone_masked = strlen($u['phone']) > 5 ? substr($u['phone'], 0, 4) . "****" . substr($u['phone'], -3) : $u['phone'];
        
        // Logic ketersediaan metode
        // WA hanya tersedia jika ada nomor HP (verifikasi opsional tergantung kebijakan admin, disini kita longgarkan)
        $has_wa = !empty($u['phone']); 
        $has_email = !empty($u['email']);

        echo json_encode([
            'success' => true,
            'methods' => [
                'has_wa' => $has_wa,
                'phone_masked' => $phone_masked,
                'has_email' => $has_email,
                'email_masked' => $email_masked
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Account not found']);
    }
    exit;
}

// --- 2. SEND OTP (WA) OR LINK (EMAIL) ---
if ($action === 'send') {
    $id = $conn->real_escape_string($input['identifier'] ?? '');
    $method = $input['method'] ?? 'email'; // 'wa' or 'email'
    
    // Ambil data user + Config Admin (untuk SMTP/WA API)
    $sql = "SELECT u.id, u.email, u.phone, (SELECT merchant_config FROM users WHERE id=1) as admin_config FROM users u WHERE u.username='$id' OR u.email='$id'";
    $res = $conn->query($sql);
    
    if($res->num_rows > 0) {
        $u = $res->fetch_assoc();
        $uid = $u['id'];
        $conf = json_decode($u['admin_config'], true);
        
        if ($method === 'wa') {
            if(empty($u['phone'])) { echo json_encode(['success'=>false,'message'=>'Phone empty']); exit; }
            
            // Generate OTP
            $otp = rand(100000, 999999);
            // Simpan OTP ke kolom verification_code (reuse kolom ini)
            $conn->query("UPDATE users SET verification_code = '$otp' WHERE id = $uid");
            
            // Kirim WA (Logic Fonnte)
            $token = $conf['auth']['fonnteToken'] ?? '';
            if($token) {
                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL => 'https://api.fonnte.com/send', CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => ['target' => $u['phone'], 'message' => "*QiosLink Reset Password*\nKode OTP: *$otp*"],
                    CURLOPT_HTTPHEADER => ["Authorization: $token"],
                ]);
                curl_exec($curl); curl_close($curl);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'WA Gateway not configured by Admin']);
            }
        } 
        else if ($method === 'email') {
            // Generate Token Panjang
            $token = bin2hex(random_bytes(32));
            // Simpan Token di DB (Bisa buat tabel baru 'password_resets' atau reuse field 'provider_id' sementara dengan prefix 'RESET:')
            // Demi kesederhanaan file bundle, kita pakai 'verification_code' tapi ini cuma 6 char biasanya di DB schema lama.
            // LEBIH AMAN: Kita update kolom password sementara dengan flag khusus atau kirim link berisi OTP 6 angka saja.
            // Solusi Cepat: Kirim Link yang berisi OTP 6 angka, user input OTP manual di web. 
            // ATAU: Kirim OTP 6 angka ke email. Ini paling mudah implementasinya di frontend yang sudah ada (verify step).
            
            $otp = rand(100000, 999999);
            $conn->query("UPDATE users SET verification_code = '$otp' WHERE id = $uid");
            
            // Kirim Email via SimpleSMTP
            $smtp = $conf['smtp'] ?? [];
            if($smtp) {
                try {
                    $mail = new SimpleSMTP($smtp['host'], $smtp['port'], $smtp['user'], $smtp['pass'], $smtp['secure']??'tls');
                    $body = "<h2>Reset Password Request</h2><p>Your verification code is: <b>$otp</b></p>";
                    $mail->send($u['email'], "Reset Password Code", $body, $smtp['fromEmail'], "QiosLink Security");
                    echo json_encode(['success' => true, 'message' => 'Code sent to email']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'SMTP Error: '.$e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'SMTP not configured']);
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    exit;
}

// --- 3. VERIFY OTP ---
if ($action === 'verify') {
    $id = $conn->real_escape_string($input['identifier'] ?? '');
    $otp = $conn->real_escape_string($input['otp'] ?? '');
    
    $sql = "SELECT id FROM users WHERE (username='$id' OR email='$id') AND verification_code='$otp'";
    $res = $conn->query($sql);
    
    if ($res->num_rows > 0) {
        $uid = $res->fetch_assoc()['id'];
        // Generate Temporary Reset Token (Simple Hash)
        $reset_token = hash('sha256', $uid . $otp . time() . 'SALT');
        // Simpan token ini di DB? Atau kirim UID terenkripsi?
        // Cara termudah stateless: Kirim UID + HashSignature
        // Tapi demi keamanan di simple script: Kita return UID dan OTP as proof (Frontend keep state).
        // Kita update verification_code jadi string khusus 'VERIFIED_TIMESTAMP'
        
        $marker = 'VERIFIED_' . time();
        $conn->query("UPDATE users SET verification_code = '$marker' WHERE id = $uid");
        
        // Return token gabungan uid.marker
        echo json_encode(['success' => true, 'token' => base64_encode("$uid:$marker")]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid Code']);
    }
    exit;
}

// --- 4. RESET PASSWORD ---
if ($action === 'reset') {
    $token = $input['token'] ?? ''; // base64(uid:marker)
    $pass = $input['password'] ?? '';
    
    if(empty($pass)) { echo json_encode(['success'=>false,'message'=>'Password empty']); exit; }
    
    $decoded = base64_decode($token);
    list($uid, $marker) = explode(':', $decoded);
    $uid = intval($uid);
    $marker = $conn->real_escape_string($marker);
    
    // Cek validitas marker di DB
    $sql = "SELECT id FROM users WHERE id = $uid AND verification_code = '$marker'";
    if ($conn->query($sql)->num_rows > 0) {
        // Reset Password
        // Di production: password_hash($pass, PASSWORD_DEFAULT)
        $new_pass = $conn->real_escape_string($pass);
        $conn->query("UPDATE users SET password = '$new_pass', verification_code = NULL WHERE id = $uid");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Session expired or invalid']);
    }
    exit;
}
?>
