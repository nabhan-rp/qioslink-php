
<?php
// FILE: callback.php (ROOT PUBLIC_HTML)
// Versi Debugging untuk mendeteksi apakah Qiospay mengirim Webhook

require 'api/db_connect.php';

// 1. Tangkap semua input (JSON atau Form Data)
$raw_input = file_get_contents("php://input");
$json_data = json_decode($raw_input, true);
$post_data = $_POST; // Jika formatnya x-www-form-urlencoded

// 2. Gabungkan data untuk diproses
$data = !empty($json_data) ? $json_data : $post_data;

// 3. LOGGING (PENTING UNTUK CEK APAKAH CALLBACK MASUK)
$log_msg = date('Y-m-d H:i:s') . " | IP: " . $_SERVER['REMOTE_ADDR'];
$log_msg .= " | RAW: " . $raw_input;
$log_msg .= " | POST: " . json_encode($_POST) . "\n";
file_put_contents('webhook_log.txt', $log_msg, FILE_APPEND);

// 4. PROSES
// Sesuaikan parameter dengan dokumentasi Qiospay (biasanya status & amount)
$status = $data['status'] ?? '';
$amount = $data['amount'] ?? 0;

if ($status == 'success' && $amount > 0) {
    // Logic pencocokan
    $sql = "SELECT id FROM transactions WHERE amount = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $amount);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $conn->query("UPDATE transactions SET status = 'paid' WHERE id = " . $row['id']);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'ignored', 'message' => 'No transaction match']);
    }
} else {
    echo json_encode(['status' => 'failed']);
}
?>