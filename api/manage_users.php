
<?php
require 'db_connect.php';

$input = json_decode(file_get_contents("php://input"), true);
$action = isset($_GET['action']) ? $_GET['action'] : ($input['action'] ?? '');

try {
    // LIST USERS
    if ($action === 'list') {
        $sql = "SELECT id, username, email, role, merchant_config, is_verified, is_kyc_verified, creator_id FROM users ORDER BY id ASC";
        $result = $conn->query($sql);
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $row['merchantConfig'] = json_decode($row['merchant_config']);
            unset($row['merchant_config']);
            
            // Explicit cast to ensure React receives boolean or explicit number
            $row['isVerified'] = ($row['is_verified'] == 1) ? true : false;
            $row['isKycVerified'] = ($row['is_kyc_verified'] == 1) ? true : false;
            
            $users[] = $row;
        }
        echo json_encode(['success' => true, 'users' => $users]);
        exit;
    }

    // VERIFY USER MANUAL (EMAIL ONLY)
    if ($action === 'verify') {
        if (!isset($input['id'])) throw new Exception("User ID required");
        
        $user_id = $input['id'];
        $stmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Email verified successfully']);
        } else {
            throw new Exception("DB Error: " . $conn->error);
        }
        exit;
    }
    
    // APPROVE KYC MANUAL (AND UPGRADE TO MERCHANT)
    if ($action === 'approve_kyc') {
        if (!isset($input['id'])) throw new Exception("User ID required");
        
        $user_id = $input['id'];
        // UPDATE: Ubah role jadi 'merchant' saat KYC diapprove manual
        $stmt = $conn->prepare("UPDATE users SET is_kyc_verified = 1, role = 'merchant' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User KYC Approved & Upgraded to Merchant']);
        } else {
            throw new Exception("DB Error: " . $conn->error);
        }
        exit;
    }

    // DELETE USER
    if ($action === 'delete') {
        if (!isset($input['id'])) throw new Exception("User ID required");
        $id = $input['id'];
        if ($id == 1) throw new Exception("Cannot delete root admin");

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        else throw new Exception("Delete failed: " . $conn->error);
        exit;
    }

    // CREATE / UPDATE USER
    if ($action === 'create' || $action === 'update') {
        $username = $input['username'];
        $email = $input['email'];
        $password = isset($input['password']) ? $input['password'] : null;
        $role = $input['role'];
        $config = isset($input['config']) ? json_encode($input['config']) : null;
        $creator_id = isset($input['creator_id']) ? $input['creator_id'] : 1; 

        if ($action === 'create') {
            if (!$password) throw new Exception("Password required for create");
            $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
            if($check->num_rows > 0) { echo json_encode(['success'=>false, 'message'=>'Username exists']); exit; }

            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, merchant_config, creator_id, is_verified) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("sssssi", $username, $email, $password, $role, $config, $creator_id);
            if ($stmt->execute()) echo json_encode(['success' => true]);
            else throw new Exception("Insert failed: " . $conn->error);
        } 
        else if ($action === 'update') {
            $id = $input['id'];
            if (!empty($password)) {
                $stmt = $conn->prepare("UPDATE users SET username=?, email=?, password=?, role=?, merchant_config=? WHERE id=?");
                $stmt->bind_param("sssssi", $username, $email, $password, $role, $config, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=?, merchant_config=? WHERE id=?");
                $stmt->bind_param("ssssi", $username, $email, $role, $config, $id);
            }
            if ($stmt->execute()) echo json_encode(['success' => true]);
            else throw new Exception("Update failed: " . $conn->error);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>