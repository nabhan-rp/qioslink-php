<?php
require 'db_connect.php';
$input = json_decode(file_get_contents("php://input"), true);
if (isset($input['user_id']) && isset($input['code'])) {
    $user_id = $input['user_id'];
    $code = $input['code'];
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND verification_code = ?");
    $stmt->bind_param("is", $user_id, $code);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $update = $conn->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?");
        $update->bind_param("i", $user_id);
        $update->execute();
        echo json_encode(['success' => true, 'message' => 'Email verified successfully']);
    } else { echo json_encode(['success' => false, 'message' => 'Invalid OTP Code']); }
} else { echo json_encode(['success' => false, 'message' => 'Invalid Input']); }
?>