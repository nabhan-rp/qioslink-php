<?php
// GUNAKAN __DIR__ UNTUK MENGHINDARI ERROR 'NO SUCH FILE'
require_once __DIR__ . '/simple_smtp.php';

header("Content-Type: application/json");
$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['config']) || !isset($input['recipient'])) {
    echo json_encode(['success' => false, 'message' => 'Missing config']); exit;
}

$conf = $input['config'];
$secure = isset($conf['secure']) ? $conf['secure'] : 'tls';

// FIX UNDEFINED KEYS: Berikan nilai default jika tidak dikirim dari frontend
$fromEmail = isset($conf['fromEmail']) && !empty($conf['fromEmail']) ? $conf['fromEmail'] : 'noreply@' . $_SERVER['HTTP_HOST'];
$fromName = isset($conf['fromName']) && !empty($conf['fromName']) ? $conf['fromName'] : 'QiosLink Notification';

try {
    $mail = new SimpleSMTP($conf['host'], $conf['port'], $conf['user'], $conf['pass'], $secure);
    $body = "
        <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: #4f46e5;'>QiosLink SMTP Test</h2>
            <p><strong>Success!</strong> Your SMTP configuration is correct.</p>
            <p>Host: {$conf['host']}</p>
            <p>Sent to: {$input['recipient']}</p>
            <hr>
            <small>Sent via QiosLink Universal Engine (V2)</small>
        </div>
    ";
    
    $mail->send($input['recipient'], "QiosLink SMTP Test", $body, $fromEmail, $fromName);
    
    echo json_encode(['success' => true, 'message' => 'Test email sent successfully via SimpleSMTP']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Send Failed: " . $e->getMessage()]);
}
?>