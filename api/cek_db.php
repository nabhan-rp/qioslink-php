<?php
header("Content-Type: text/plain");

$host = "localhost";
$username = "jajanserver_u888_masterjajan";
$password = "%2Fjajanserver%2Fjajanin"; // Pastikan ini sesuai password cPanel
$dbname = "jajanserver_qioslinkjajan";

echo "Mencoba koneksi ke database...\n";
echo "User: $username\n";
echo "Db: $dbname\n";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    echo "GAGAL KONEKSI: " . $conn->connect_error;
} else {
    echo "BERHASIL KONEKSI! Database siap digunakan.";
}
?>