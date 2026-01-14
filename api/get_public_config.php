
<?php
// FILE: api/get_public_config.php
// INSTRUKSI: Upload file ini ke folder /api/

require 'db_connect.php';

// Header JSON
header('Content-Type: application/json');

// Ambil Config milik SUPERADMIN (ID 1) -> Ini dianggap CONFIG SYSTEM GLOBAL
$sql = "SELECT merchant_config FROM users WHERE id = 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $fullConfig = json_decode($row['merchant_config'], true);
    
    // PENTING: Hanya return data yang aman untuk publik!
    $authConfig = isset($fullConfig['auth']) ? $fullConfig['auth'] : [];
    $kycConfig = isset($fullConfig['kyc']) ? $fullConfig['kyc'] : [];
    
    $safeConfig = [
        'loginMethod' => $authConfig['loginMethod'] ?? 'standard',
        'verifyKyc' => isset($kycConfig['enabled']) ? $kycConfig['enabled'] : false,
        // UPDATE: Kirim detail KYC agar Frontend tahu harus pakai Manual/Didit tanpa login admin
        'kyc' => [
            'enabled' => isset($kycConfig['enabled']) ? $kycConfig['enabled'] : false,
            'provider' => isset($kycConfig['provider']) ? $kycConfig['provider'] : 'manual',
            'manualContactType' => isset($kycConfig['manualContactType']) ? $kycConfig['manualContactType'] : 'whatsapp',
            'manualContactValue' => isset($kycConfig['manualContactValue']) ? $kycConfig['manualContactValue'] : ''
        ],
        'socialLogin' => [
            'google' => $authConfig['socialLogin']['google'] ?? false,
            'googleClientId' => $authConfig['socialLogin']['googleClientId'] ?? '',
            'facebook' => $authConfig['socialLogin']['facebook'] ?? false,
            'facebookAppId' => $authConfig['socialLogin']['facebookAppId'] ?? '',
            'github' => $authConfig['socialLogin']['github'] ?? false,
            'githubClientId' => $authConfig['socialLogin']['githubClientId'] ?? ''
        ]
    ];

    echo json_encode([
        'success' => true,
        'config' => $safeConfig
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Config not found']);
}
?>