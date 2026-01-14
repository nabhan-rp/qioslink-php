================================================================================
PANDUAN FITUR LUPA PASSWORD (FORGOT PASSWORD)
================================================================================

Fitur ini memungkinkan user mereset password mereka menggunakan OTP yang dikirim via Email.

--------------------------------------------------------------------------------
1. SYARAT WAJIB
--------------------------------------------------------------------------------
- Anda SUDAH mengkonfigurasi SMTP di Dashboard Admin (User ID 1).
  Menu: Settings & Profile -> SMTP -> Save Settings.
- Karena sistem menggunakan SMTP milik Superadmin untuk mengirim email reset ke user.

--------------------------------------------------------------------------------
2. CARA PASANG (DEPLOYMENT)
--------------------------------------------------------------------------------
Anda perlu mengupload 2 file PHP baru ke hosting Anda agar backend berjalan.

1. Buka file `backend_forgot_password.txt`.
   Rename menjadi: `forgot_password.php`.
   Upload ke folder `/api/` di hosting.

2. Buka file `backend_reset_password.txt`.
   Rename menjadi: `reset_password.php`.
   Upload ke folder `/api/` di hosting.

--------------------------------------------------------------------------------
CARA KERJA
--------------------------------------------------------------------------------
1. User klik "Forgot Password?" di halaman login.
2. User memasukkan Email.
3. Server mengirim OTP 6 angka ke email tersebut (menggunakan setting SMTP Admin).
4. User memasukkan OTP dan Password Baru.
5. Server memvalidasi OTP. Jika benar, password diubah dan OTP dihapus.
