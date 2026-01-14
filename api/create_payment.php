<?php
// =============================================================================
// FILE: api/create_payment.php (VERSI KODE UNIK OTOMATIS)
// =============================================================================

require 'db_connect.php';
require 'qris_utils.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);

if(isset($input['merchant_id']) && isset($input['amount'])) {
    
    $merchant_id = $input['merchant_id'];
    $base_amount = (int)$input['amount']; // Nominal Asli
    $description = isset($input['description']) ? $input['description'] : 'Payment';
    $expiry_minutes = isset($input['expiry_minutes']) ? (int)$input['expiry_minutes'] : 0;
    $single_use = isset($input['single_use']) && $input['single_use'] ? 1 : 0;
    
    // Parameter Integrasi
    $external_id = isset($input['external_id']) ? $input['external_id'] : null;
    $callback_url = isset($input['callback_url']) ? $input['callback_url'] : null;
    
    // --------------------------------------------------------------------------
    // 1. LOGIKA KODE UNIK (ANTI BENTROK)
    // --------------------------------------------------------------------------
    // Kita akan mencari angka acak (1-999) agar nominal akhir (Base + Unik)
    // belum pernah ada di status 'pending'.
    
    $final_amount = $base_amount;
    $is_unique_found = false;
    $max_retries = 20; // Coba 20x cari angka
    $attempt = 0;

    // Jika ini request dari WHMCS (ada external_id), kita cek dulu
    // apakah invoice ini SUDAH punya transaksi pending sebelumnya?
    // Jika ya, gunakan nominal yang sama biar kode uniknya tidak berubah-ubah saat refresh.
    if (!empty($external_id)) {
        $cekExisting = $conn->prepare("SELECT amount, trx_id, qr_string, payment_url FROM transactions WHERE merchant_id = ? AND external_ref_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
        $cekExisting->bind_param("is", $merchant_id, $external_id);
        $cekExisting->execute();
        $resExisting = $cekExisting->get_result();
        
        if ($resExisting->num_rows > 0) {
            // Sudah ada, kembalikan data lama
            $data = $resExisting->fetch_assoc();
            echo json_encode([
                "success" => true,
                "trx_id" => $data['trx_id'],
                "qr_string" => $data['qr_string'],
                "payment_url" => $data['payment_url'],
                "amount" => (int)$data['amount'], // Ini sudah termasuk kode unik lama
                "is_existing" => true
            ]);
            exit;
        }
    }

    // Jika belum ada, cari kode unik baru
    while (!$is_unique_found && $attempt < $max_retries) {
        // Generate angka unik 1 - 750 (agar tidak terlalu mahal, tapi cukup acak)
        // Jika amount 0 (misal testing), jangan tambah kode unik
        $unique_code = ($base_amount > 0) ? rand(1, 750) : 0;
        $candidate_amount = $base_amount + $unique_code;
        
        // Cek apakah ada transaksi PENDING dengan nominal ini?
        $cekSql = "SELECT id FROM transactions WHERE amount = $candidate_amount AND status = 'pending'";
        $cekRes = $conn->query($cekSql);
        
        if ($cekRes->num_rows == 0) {
            // Aman! Nominal ini belum dipakai orang lain
            $final_amount = $candidate_amount;
            $is_unique_found = true;
        }
        $attempt++;
    }

    if (!$is_unique_found) {
        echo json_encode(["success" => false, "message" => "Server Busy: Cannot generate unique amount. Please try again."]);
        exit;
    }

    // --------------------------------------------------------------------------
    // 2. PROSES SIMPAN TRANSAKSI
    // --------------------------------------------------------------------------
    
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

        // Generate QR dengan nominal FINAL (Base + Kode Unik)
        $dynamicQR = generateDynamicQR($config['qrisString'], $final_amount);
        
        $trx_id = "TRX-" . date("ymd") . rand(1000,9999);
        $payment_token = bin2hex(random_bytes(16));
        
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $domain_url = str_replace('/api', '', $protocol . "://" . $_SERVER['HTTP_HOST']);
        $payment_url = $domain_url . "/?pay=" . $payment_token; 
        
        $expires_at = null;
        if ($expiry_minutes > 0) {
            $expires_at = date('Y-m-d H:i:s', strtotime("+$expiry_minutes minutes"));
        }

        $sql = "INSERT INTO transactions 
                (trx_id, merchant_id, amount, description, status, qr_string, payment_token, payment_url, expires_at, is_single_use, external_ref_id, external_callback_url) 
                VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?)";
                
        $insert = $conn->prepare($sql);
        // Perhatikan: kita bind $final_amount, bukan $base_amount
        $insert->bind_param("siisssssiss", $trx_id, $merchant_id, $final_amount, $description, $dynamicQR, $payment_token, $payment_url, $expires_at, $single_use, $external_id, $callback_url);
        
        if ($insert->execute()) {
            echo json_encode([
                "success" => true,
                "trx_id" => $trx_id,
                "qr_string" => $dynamicQR,
                "payment_url" => $payment_url,
                "amount" => $final_amount, // Return nominal yang sudah ada kode uniknya
                "original_amount" => $base_amount
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
