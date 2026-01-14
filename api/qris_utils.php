<?php
// Mencegah akses langsung via browser
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    header("HTTP/1.0 403 Forbidden"); exit;
}

// Algoritma CRC16 (CCITT-FALSE) standar EMVCo
function crc16($data) {
    $crc = 0xFFFF;
    for ($i = 0; $i < strlen($data); $i++) {
        $x = (($crc >> 8) ^ ord($data[$i])) & 0xFF;
        $x ^= $x >> 4;
        $crc = (($crc << 8) ^ ($x << 12) ^ ($x << 5) ^ $x) & 0xFFFF;
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

function formatField($tag, $value) {
    $length = str_pad(strlen($value), 2, '0', STR_PAD_LEFT);
    return $tag . $length . $value;
}

function generateDynamicQR($staticQr, $amount) {
    $rawQr = $staticQr;
    
    // 1. Bersihkan String: Cari posisi CRC lama (Tag 6304)
    // QR Nobu biasanya diakhiri dengan ...6304ABCD
    $crcPos = strrpos($rawQr, '6304');
    if ($crcPos !== false) {
        // Ambil string dari awal sampai sebelum '6304'
        $rawQr = substr($rawQr, 0, $crcPos);
    }
    
    // 2. Buat Tag 54 (Transaction Amount)
    // Format: "54" + "Panjang Karakter" + "Nominal"
    $amountStr = floor($amount); // Pastikan tidak ada desimal
    $amountField = formatField('54', $amountStr);
    
    // 3. Susun Ulang: QR Bersih + Tag Amount + Tag CRC Awal (6304)
    $payload = $rawQr . $amountField . '6304';
    
    // 4. Hitung Checksum baru
    $crc = crc16($payload);
    
    // 5. Gabungkan menjadi string final
    return $payload . $crc;
}
?>