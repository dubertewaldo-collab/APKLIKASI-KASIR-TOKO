# Kasir Toko (PHP + MySQL)

## Pasang di XAMPP
1. Salin folder `kasir-toko` ke `C:\xampp\htdocs\`.
2. Nyalakan **Apache** dan **MySQL** di XAMPP.
3. Buka `http://localhost/phpmyadmin` → tab **Import** → pilih `database.sql` → **Import**.
4. Buka `http://localhost/kasir-toko/`.

Login awal: `admin` / `admin123`. Segera ganti lewat menu **Pengguna**,
lalu buat akun untuk kasir di menu yang sama.

## Mengubah nama toko
Edit `config/app.php` (NAMA_TOKO, ALAMAT_TOKO, batas stok menipis).
