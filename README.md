================================================================================
PANDUAN DEPLOYMENT BACKEND PHP (METODE GITHUB)
================================================================================

Backend QiosLink kini telah dipisahkan agar lebih mudah dikelola.
Repository Sumber: https://github.com/nabhan-rp/qioslink-php

Ikuti langkah-langkah di bawah ini untuk meng-online-kan sistem backend QiosLink.

--------------------------------------------------------------------------------
LANGKAH 1: PERSIAPAN DATABASE (cPanel)
--------------------------------------------------------------------------------
Sebelum upload file, siapkan "Rumah" untuk datanya.

1. Login ke cPanel -> Menu **MySQL Databases**.
2. Buat Database Baru (contoh: `u123_qioslink`).
3. Buat User Database Baru (contoh: `u123_admin`, password: `rahasia123`).
   *Simpan password ini, jangan sampai hilang!*
4. Klik tombol **Add User To Database**.
   - Pilih User tadi dan Database tadi.
   - Centang **ALL PRIVILEGES**.
   - Klik Make Changes.

--------------------------------------------------------------------------------
LANGKAH 2: UPLOAD FILE PHP (BACKEND)
--------------------------------------------------------------------------------
1. Buka link: https://github.com/nabhan-rp/qioslink-php
2. Klik tombol hijau **Code** -> **Download ZIP**.
3. Buka **File Manager** di cPanel Anda.
4. Masuk ke folder `public_html`.
5. Buat folder baru bernama `api`.
6. Masuk ke folder `api` tersebut, lalu klik **Upload**.
7. Upload file ZIP yang baru didownload dari GitHub.
8. Klik Kanan pada ZIP tersebut -> **Extract**.
9. **PENTING:** Pastikan file-file seperti `db_connect.php`, `create_payment.php`, dll berada LANGSUNG di dalam folder `public_html/api/`.
   *(Jika setelah extract file berada di dalam subfolder `qioslink-php-main`, pindahkan (Move) semua isinya keluar ke `public_html/api/`).*

--------------------------------------------------------------------------------
LANGKAH 3: IMPORT DATABASE
--------------------------------------------------------------------------------
1. Di folder `api` yang baru Anda upload, cari file bernama `database.sql` (atau `file_sql.txt`).
2. Download file tersebut ke komputer Anda.
3. Kembali ke cPanel utama, buka menu **phpMyAdmin**.
4. Klik nama database yang Anda buat di Langkah 1.
5. Klik tab **Import** (di bagian atas).
6. Upload file `database.sql` tadi.
7. Klik **Go** / **Kirim**.

--------------------------------------------------------------------------------
LANGKAH 4: KONEKSIKAN PHP KE DATABASE
--------------------------------------------------------------------------------
Agar backend bisa bicara dengan database, Anda harus mengedit kredensialnya.

1. Di File Manager cPanel (folder `public_html/api/`).
2. Cari file bernama `db_connect.php`.
3. Klik Kanan -> **Edit**.
4. Ubah bagian ini sesuai data Langkah 1:

   ```php
   $host = "localhost";
   $username = "u123_admin";     // Ganti dengan User Database Anda
   $password = "rahasia123";     // Ganti dengan Password Database Anda
   $dbname = "u123_qioslink";    // Ganti dengan Nama Database Anda
   ```

5. Klik **Save Changes**.

--------------------------------------------------------------------------------
LANGKAH 5: GABUNGKAN DENGAN FRONTEND (REACT)
--------------------------------------------------------------------------------
Sekarang backend sudah siap. Tinggal pasang Frontend-nya.

1. Di komputer lokal Anda (tempat coding React), jalankan:
   `npm run build`
2. Akan muncul folder `dist`.
3. Buka File Manager cPanel -> `public_html`.
4. Upload SEMUA isi folder `dist` (file `index.html`, folder `assets`, dll) ke `public_html`.

--------------------------------------------------------------------------------
STRUKTUR FINAL DI FILE MANAGER
--------------------------------------------------------------------------------
Pastikan susunan file Anda terlihat seperti ini agar sistem berjalan lancar:

/public_html
  ├── index.html                <-- (File React Frontend)
  ├── assets/                   <-- (Folder React Frontend)
  │
  ├── api/                      <-- (Folder Backend dari GitHub)
  │   ├── db_connect.php        <-- (Sudah diedit passwordnya)
  │   ├── create_payment.php
  │   ├── manage_users.php
  │   ├── database.sql
  │   └── ... (file php lainnya)
  │
  └── callback.php              <-- (PENTING: File webhook)

*Catatan: Jika file `callback.php` ada di dalam folder `api` di GitHub, Anda boleh membiarkannya di sana, TAPI URL webhook di Qiospay harus disesuaikan menjadi `domain.com/api/callback.php`.*

--------------------------------------------------------------------------------
LANGKAH 6: INTEGRASI QIOSPAY
--------------------------------------------------------------------------------
1. Login ke Dashboard Qiospay.
2. Masuk menu Integrasi.
3. Isi URL Callback:
   - Jika `callback.php` ada di `public_html`: `https://domain-anda.com/callback.php`
   - Jika `callback.php` ada di `public_html/api`: `https://domain-anda.com/api/callback.php`
4. Simpan.

SELESAI! QiosLink Anda sudah live menggunakan backend dari GitHub.
