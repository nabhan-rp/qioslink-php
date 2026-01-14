<?php
// FILE: api/social_login.php
// INSTRUKSI: Upload ke folder /api/

require 'db_connect.php';

$input = json_decode(file_get_contents("php://input"), true);

/*
  Input diharapkan dari Frontend (setelah sukses auth dengan Firebase/Google SDK):
  {
    "provider": "google", // google, facebook, github
    "provider_id": "1234567890",
    "email": "user@gmail.com",
    "name": "Budi Santoso",
    "photo": "https://..."
  }
*/

if(isset($input['provider']) && isset($input['email'])) {
    
    $provider = $input['provider'];
    $provider_id = $input['provider_id'] ?? '';
    $email = $input['email'];
    $name = $input['name'] ?? explode('@', $email)[0];
    
    // 1. Cek apakah user sudah ada berdasarkan Provider ID
    $stmt = $conn->prepare("SELECT * FROM users WHERE auth_provider = ? AND provider_id = ?");
    $stmt->bind_param("ss", $provider, $provider_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        // --- USER FOUND (LOGIN) ---
        $row = $res->fetch_assoc();
        
        // Format Output
        $row['merchantConfig'] = json_decode($row['merchant_config']);
        unset($row['merchant_config']);
        unset($row['password']);
        
        $row['isVerified'] = $row['is_verified'] == 1;
        $row['isPhoneVerified'] = $row['is_phone_verified'] == 1;
        
        echo json_encode(['success' => true, 'user' => $row]);
        exit;
        
    } else {
        // --- USER NOT FOUND BY PROVIDER ID ---
        // 2. Cek apakah email sudah terdaftar (Link Account)
        $stmtEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmtEmail->bind_param("s", $email);
        $stmtEmail->execute();
        $resEmail = $stmtEmail->get_result();
        
        if ($resEmail->num_rows > 0) {
            // --- LINK ACCOUNT ---
            // Email sama ditemukan, update provider_id user tersebut
            $existingUser = $resEmail->fetch_assoc();
            $uid = $existingUser['id'];
            
            $upd = $conn->prepare("UPDATE users SET auth_provider = ?, provider_id = ?, is_verified = 1 WHERE id = ?");
            $upd->bind_param("ssi", $provider, $provider_id, $uid);
            $upd->execute();
            
            // Fetch ulang data lengkap
            $final = $conn->query("SELECT * FROM users WHERE id = $uid")->fetch_assoc();
            $final['merchantConfig'] = json_decode($final['merchant_config']);
            unset($final['merchant_config']);
            unset($final['password']);
            
            echo json_encode(['success' => true, 'user' => $final, 'message' => 'Account Linked']);
            exit;
            
        } else {
            // --- REGISTER NEW USER ---
            // Buat username dari email/nama (bersihkan spasi)
            $baseUsername = strtolower(preg_replace("/[^A-Za-z0-9]/", '', $name));
            $username = $baseUsername;
            $counter = 1;
            
            // Loop cek username unik
            while(true) {
                $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
                if($check->num_rows == 0) break;
                $username = $baseUsername . $counter;
                $counter++;
            }
            
            $role = 'user';
            // Default Config kosong
            $defConfig = json_encode(["merchantName" => $name, "auth" => ["loginMethod"=>"standard"]]);
            
            $ins = $conn->prepare("INSERT INTO users (username, email, role, is_verified, auth_provider, provider_id, merchant_config) VALUES (?, ?, ?, 1, ?, ?, ?)");
            $ins->bind_param("ssssss", $username, $email, $role, $provider, $provider_id, $defConfig);
            
            if ($ins->execute()) {
                $newId = $conn->insert_id;
                $newUser = $conn->query("SELECT * FROM users WHERE id = $newId")->fetch_assoc();
                
                $newUser['merchantConfig'] = json_decode($newUser['merchant_config']);
                unset($newUser['merchant_config']);
                unset($newUser['password']);
                
                echo json_encode(['success' => true, 'user' => $newUser, 'message' => 'Account Created']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Registration Failed: ' . $conn->error]);
            }
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Data']);
}
?>