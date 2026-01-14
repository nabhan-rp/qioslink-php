
<?php
// FILE: api/create_kyc_session.php
// INSTRUKSI: Upload ke folder /api/. 

require 'db_connect.php';

header('Content-Type: application/json');

// --- DEBUG LOG FUNCTION ---
function logDebug($msg) {
    file_put_contents('kyc_debug.log', date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}

logDebug("--- NEW SESSION REQUEST ---");

$input = json_decode(file_get_contents("php://input"), true);
$userId = $input['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

// 1. Ambil Config Didit DARI SUPERADMIN (ID 1)
$sql = "SELECT merchant_config FROM users WHERE id = 1"; 
$res = $conn->query($sql);

if ($res->num_rows > 0) {
    $admin = $res->fetch_assoc();
    $config = json_decode($admin['merchant_config'], true);
    $kycConf = $config['kyc'] ?? [];

    $apiKey = isset($kycConf['diditApiKey']) ? trim($kycConf['diditApiKey']) : '';
    
    // DEEP CLEANING
    $apiKey = preg_replace('/^bearer\s+/i', '', $apiKey);
    $apiKey = trim($apiKey);

    $appId = isset($kycConf['diditAppId']) ? trim($kycConf['diditAppId']) : '';
    // NEW: Get Workflow ID
    $workflowId = isset($kycConf['diditWorkflowId']) ? trim($kycConf['diditWorkflowId']) : '';
    
    logDebug("Config Loaded. Workflow ID: " . $workflowId);
} else {
    logDebug("Superadmin config not found");
    echo json_encode(['success' => false, 'message' => 'System Config Error: Admin Not Found']);
    exit;
}

if (empty($apiKey)) {
    logDebug("API Key Missing in Database");
    echo json_encode(['success' => false, 'message' => 'KYC API Key Missing on Server. Please Contact Admin.']);
    exit;
}

// 2. Request Session ke Didit API
$url = "https://api.didit.me/v3/verifications"; 

// Create callback URL dynamically
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$callbackUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/api/kyc_callback.php";

$body = [
    "callback_url" => $callbackUrl,
    "vendor_data" => (string)$userId, 
    "metadata" => [
        "internal_user_id" => (string)$userId
    ],
    "features" => ["document", "face"] 
];

// Add App ID if exists
if (!empty($appId)) {
    $body['app_id'] = $appId;
}

// NEW: Add Workflow ID if exists (Force specific flow)
if (!empty($workflowId)) {
    $body['workflow_id'] = $workflowId;
}

$headers = [
    "Content-Type: application/json",
    "Authorization: Bearer " . $apiKey
];

logDebug("Sending to Didit...");

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

logDebug("Response ($httpCode): " . $response);

if ($curlError) {
    echo json_encode(['success' => false, 'message' => 'Connection Error: ' . $curlError]);
    exit;
}

$resData = json_decode($response, true);

// Success condition: HTTP 200/201 and URL is present
if (($httpCode >= 200 && $httpCode < 300) && isset($resData['url'])) {
    echo json_encode([
        'success' => true,
        'verification_url' => $resData['url'] 
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Didit Rejected Request',
        'debug' => $resData 
    ]);
}
?>