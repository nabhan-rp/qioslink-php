<?php
// =============================================================================
// FILE: api/create_payment.php (VERSI ANTI DUPLIKAT)
// =============================================================================

require 'db_connect.php';
require 'qris_utils.php';

// Header JSON
header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);

if(isset($input['merchant_id']) && isset($input['amount'])) {
    
    $merchant_id = $input['merchant_id'];
    $amount = (int)$input['amount'];
    $description = isset($input['description']) ? $input['description'] : 'Payment';
    $expiry_minutes = isset($input['expiry_minutes']) ? (int)$input['expiry_minutes'] : 0;
    $single_use = isset($input['single_use']) && $input['single_use'] ? 1 : 0;
    
    // Parameter Tambahan untuk Integrasi (WHMCS/WooCommerce)
    $external_id = isset($input['external_id']) ? $input['external_id'] : null;
    $callback_url = isset($input['callback_url']) ? $input['callback_url'] : null;
    
    // --------------------------------------------------------------------------
    // 1. CEK DUPLIKAT (IDEMPOTENCY CHECK)
    // --------------------------------------------------------------------------
    // Jika ada external_id (Invoice ID), cek apakah sudah ada transaksi PENDING
    // dengan nominal yang sama. Jika ada, jangan buat baru, tapi kembalikan yang lama.
    
    if (!empty($external_id)) {
        $checkSql = "SELECT trx_id, qr_string, payment_url, amount 
                     FROM transactions 
                     WHERE merchant_id = ? 
                     AND external_ref_id = ? 
                     AND status = 'pending' 
                     AND amount = ? 
                     ORDER BY id DESC LIMIT 1";
                     
        $stmtCheck = $conn->prepare($checkSql);
        $stmtCheck->bind_param("isi", $merchant_id, $external_id, $amount);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        
        if ($resCheck->num_rows > 0) {
            $existing = $resCheck->fetch_assoc();
            
            // Update Callback URL jika berubah (opsional, jaga-jaga user ganti domain)
            if ($callback_url) {
                $updCb = $conn->prepare("UPDATE transactions SET external_callback_url = ? WHERE trx_id = ?");
                $updCb->bind_param("ss", $callback_url, $existing['trx_id']);
                $updCb->execute();
            }

            echo json_encode([
                "success" => true,
                "trx_id" => $existing['trx_id'],
                "qr_string" => $existing['qr_string'],
                "payment_url" => $existing['payment_url'],
                "amount" => $existing['amount'],
                "is_existing" => true // Penanda bahwa ini data lama
            ]);
            exit; // STOP DISINI, JANGAN BUAT BARU
        }
    }

    // --------------------------------------------------------------------------
    // 2. BUAT TRANSAKSI BARU (Jika belum ada)
    // --------------------------------------------------------------------------
    
    // Ambil Config Merchant
    $stmt = $conn->prepare("SELECT merchant_config FROM users WHERE id = ?");
    $stmt->bind_param("i", $merchant_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $config = json_decode($user['merchant_config'], true);
        
        if (!isset($config['qrisString']) || strlen($config['qrisString']) < 10) {
            echo json_encode(['success' => false, 'message' => 'Merchant QRIS configuration missing']);
            exit;
        }

        // Generate QR Dinamis
        $dynamicQR = generateDynamicQR($config['qrisString'], $amount);
        
        // Generate ID & Token
        $trx_id = "TRX-" . date("ymd") . rand(1000,9999);
        $payment_token = bin2hex(random_bytes(16));
        
        // URL Pembayaran
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $domain_url = $protocol . "://" . $_SERVER['HTTP_HOST'];
        // Hapus /api jika script dijalankan dari dalam folder api
        $domain_url = str_replace('/api', '', $domain_url); 
        $payment_url = $domain_url . "/?pay=" . $payment_token; 
        
        // Expiry
        $expires_at = null;
        if ($expiry_minutes > 0) {
            $expires_at = date('Y-m-d H:i:s', strtotime("+$expiry_minutes minutes"));
        }

        // Simpan ke Database (LENGKAP DENGAN EXTERNAL REF)
        $sql = "INSERT INTO transactions 
                (trx_id, merchant_id, amount, description, status, qr_string, payment_token, payment_url, expires_at, is_single_use, external_ref_id, external_callback_url) 
                VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?)";
                
        $insert = $conn->prepare($sql);
        // s=string, i=integer. Urutan: trx_id(s), merch(i), amt(i), desc(s), qr(s), tok(s), url(s), exp(s), single(i), ext_id(s), ext_cb(s)
        $insert->bind_param("siisssssiss", $trx_id, $merchant_id, $amount, $description, $dynamicQR, $payment_token, $payment_url, $expires_at, $single_use, $external_id, $callback_url);
        
        if ($insert->execute()) {
            echo json_encode([
                "success" => true,
                "trx_id" => $trx_id,
                "qr_string" => $dynamicQR,
                "payment_url" => $payment_url,
                "amount" => $amount,
                "is_existing" => false
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Database Error: " . $conn->error]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Merchant Not Found"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid Input Data"]);
}
?>