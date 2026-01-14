
<?php
// FILE: api/update_config.php
// INSTRUKSI: Upload file ini ke folder /api/

require 'db_connect.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);

// Fungsi Recursive Trim untuk membersihkan semua input string
function recursive_trim($data) {
    if (is_array($data)) {
        return array_map('recursive_trim', $data);
    }
    if (is_string($data)) {
        return trim($data);
    }
    return $data;
}

if(isset($input['user_id']) && isset($input['config'])) {
    
    $user_id = $input['user_id'];
    // SANITIZE INPUT CONFIG
    $config = recursive_trim($input['config']);
    
    // Validasi User ID
    if (!is_numeric($user_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid User ID']);
        exit;
    }
    
    // 1. Ambil config lama dulu agar data tidak hilang (merge)
    $stmt = $conn->prepare("SELECT merchant_config, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $oldConfig = json_decode($row['merchant_config'], true) ?? [];
        
        // Merge config: Timpa config lama dengan yang baru
        // Kita gunakan array_replace_recursive agar nested object seperti 'auth' atau 'kyc' tidak tertimpa total jika parsial
        $newConfig = array_replace_recursive($oldConfig, $config);
        
        // Encode kembali ke JSON
        $jsonConfig = json_encode($newConfig);
        
        // Update DB
        $update = $conn->prepare("UPDATE users SET merchant_config = ? WHERE id = ?");
        $update->bind_param("si", $jsonConfig, $user_id);
        
        if ($update->execute()) {
            echo json_encode(['success' => true, 'message' => 'Configuration Updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database update failed: ' . $conn->error]);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Input Data']);
}
?>