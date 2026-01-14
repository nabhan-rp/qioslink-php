
<?php
// FILE: api/check_mutation.php
// INSTRUKSI: Rename jadi check_mutation.php -> Upload ke /api/

// HEADER CORS AGAR BISA DIAKSES PUBLIC
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Handle Preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'db_connect.php';

// Terima input JSON dari Frontend
$input = json_decode(file_get_contents("php://input"), true);

// Jika dipanggil manual dari tombol di Frontend (Single Check)
$target_merchant_id = isset($input['merchant_id']) ? $input['merchant_id'] : null;
$target_trx_id = isset($input['trx_id']) ? $input['trx_id'] : null;

// Logic Utama: Ambil daftar Merchant yang punya transaksi Pending
$sql = "SELECT DISTINCT t.merchant_id, u.merchant_config 
        FROM transactions t 
        JOIN users u ON t.merchant_id = u.id 
        WHERE t.status = 'pending'";

if ($target_merchant_id) {
    $sql .= " AND t.merchant_id = " . intval($target_merchant_id);
}

$result = $conn->query($sql);
$updated_count = 0;

if ($result && $result->num_rows > 0) {
    while ($merchant = $result->fetch_assoc()) {
        $config = json_decode($merchant['merchant_config'], true);
        $m_code = $config['merchantCode'] ?? '';
        $api_key = $config['qiospayApiKey'] ?? '';

        if (empty($m_code) || empty($api_key)) continue;

        // 1. Panggil API Mutasi Qiospay
        $url = "https://qiospay.id/api/mutasi/qris/$m_code/$api_key";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); 
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            // Jika koneksi ke Qiospay gagal, skip ke merchant berikutnya
            curl_close($ch);
            continue;
        }
        curl_close($ch);

        $mutasi = json_decode($response, true);

        // 2. Cek apakah ada data mutasi sukses
        if (isset($mutasi['status']) && $mutasi['status'] == 'success' && isset($mutasi['data'])) {
            
            foreach ($mutasi['data'] as $data) {
                // Filter hanya Kredit (Uang Masuk)
                if ($data['type'] !== 'CR') continue;

                $amount_paid = (int)$data['amount'];
                
                // Cari transaksi pending dengan nominal ini milik merchant ini
                $checkSql = "SELECT id, trx_id, external_callback_url, external_ref_id FROM transactions 
                             WHERE merchant_id = {$merchant['merchant_id']} 
                             AND amount = $amount_paid 
                             AND status = 'pending' 
                             ORDER BY created_at DESC LIMIT 1";
                
                $trxResult = $conn->query($checkSql);

                if ($trxResult && $trxResult->num_rows > 0) {
                    $trx = $trxResult->fetch_assoc();
                    $trx_id_internal = $trx['id'];

                    // UPDATE STATUS JADI PAID
                    $conn->query("UPDATE transactions SET status = 'paid' WHERE id = $trx_id_internal");
                    $updated_count++;

                    // FORWARDING (WHMCS/WOOCOMMERCE)
                    if (!empty($trx['external_callback_url'])) {
                        $forward_data = [
                            'status' => 'paid',
                            'trx_id' => $trx['trx_id'],
                            'external_id' => $trx['external_ref_id'],
                            'amount' => $amount_paid
                        ];
                        
                        $chf = curl_init($trx['external_callback_url']);
                        curl_setopt($chf, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($chf, CURLOPT_POSTFIELDS, http_build_query($forward_data));
                        curl_setopt($chf, CURLOPT_TIMEOUT, 5); // Timeout cepat untuk forwarding
                        curl_exec($chf);
                        curl_close($chf);
                    }
                }
            }
        }
    }
}

echo json_encode([
    'status' => 'success', 
    'message' => $updated_count > 0 ? "$updated_count Payment(s) Confirmed!" : "No matching payment found yet."
]);
?>