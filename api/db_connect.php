<?php
// Matikan tampilan error HTML kasar
error_reporting(0);
mysqli_report(MYSQLI_REPORT_OFF);

// =======================================================================
// KONFIGURASI CORS
// =======================================================================
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // Cache preflight 1 hari
} else {
    header("Access-Control-Allow-Origin: *");
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");         
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}

// Header JSON (Hapus jika file lain sudah set header ini)
// header("Content-Type: application/json; charset=UTF-8");

// =======================================================================
// KREDENSIAL DATABASE (SILAKAN ISI DISINI)
// =======================================================================

$host = "localhost";
// Ganti dengan USERNAME DATABASE asli Anda
$username = ""; 
// Ganti dengan PASSWORD DATABASE asli Anda.
$password = """; 
// Ganti dengan NAMA DATABASE asli Anda
$dbname = "";    

// =======================================================================
// LOGIKA KONEKSI
// =======================================================================
try {
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception($conn->connect_error);
    }
} catch (Exception $e) {
    http_response_code(500);
    // Kita kirim JSON manual karena koneksi gagal
    header("Content-Type: application/json");
    echo json_encode([
        "success" => false, 
        "message" => "Database Connection Failed. Check db_connect.php"
    ]);
    exit();
}
// Tidak ada closing tag php untuk keamanan whitespace
