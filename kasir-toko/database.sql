CREATE DATABASE IF NOT EXISTS kasir_toko CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE kasir_toko;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  nama VARCHAR(100) NOT NULL DEFAULT '',
  role ENUM('admin','kasir') NOT NULL DEFAULT 'kasir'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS produk (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(30) NULL UNIQUE,
  nama VARCHAR(100) NOT NULL,
  harga DECIMAL(12,2) NOT NULL,
  stok INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transaksi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  total DECIMAL(12,2) NOT NULL,
  bayar DECIMAL(12,2) NOT NULL,
  kembali DECIMAL(12,2) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_created (created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Nama & harga disalin ke sini supaya riwayat tetap benar walau produk diubah/dihapus.
CREATE TABLE IF NOT EXISTS transaksi_item (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transaksi_id INT NOT NULL,
  produk_id INT NULL,
  nama_produk VARCHAR(100) NOT NULL,
  harga DECIMAL(12,2) NOT NULL,
  qty INT NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE CASCADE,
  FOREIGN KEY (produk_id) REFERENCES produk(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Akun admin awal. Username: admin | Password: admin123
-- WAJIB diganti lewat menu Pengguna setelah login pertama!
INSERT INTO users (username, password, nama, role) VALUES
('admin', '$2y$12$j7w0thc5k2Vsss4whkZP9uGjmDJl8ZtCNDIy.2bDeMt0X9Zb5i7a6', 'Administrator', 'admin');

-- Contoh produk (boleh dihapus lewat menu Produk)
INSERT INTO produk (kode, nama, harga, stok) VALUES
('8999001', 'Mie instan goreng', 3500, 60),
('8999002', 'Air mineral 600 ml', 4000, 48),
('8999003', 'Teh botol 350 ml', 5000, 36),
('8999004', 'Kopi sachet', 2000, 100),
('8999005', 'Roti tawar', 16000, 12),
('8999006', 'Telur ayam (butir)', 2500, 90),
('8999007', 'Gula pasir 1 kg', 17000, 20),
('8999008', 'Minyak goreng 1 L', 19000, 4);
