<?php
require 'db_connect.php';

$input = json_decode(file_get_contents("php://input"), true);

if(isset($input['trx_id'])) {
    $trx_id = $input['trx_id'];
    
    $stmt = $conn->prepare("UPDATE transactions SET status = 'cancelled' WHERE trx_id = ? AND status = 'pending'");
    $stmt->bind_param("s", $trx_id);
    
    if($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Transaction cancelled']);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB Error']);
    }
}
?>