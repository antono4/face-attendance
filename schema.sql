-- =============================================
-- DATABASE ABSENSI DENGAN FACE RECOGNITION
-- =============================================

-- Buat database
CREATE DATABASE IF NOT EXISTS absensi_face
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE absensi_face;

-- =============================================
-- TABEL USERS (Pegawai & Admin)
-- =============================================
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nip VARCHAR(20) UNIQUE NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'pegawai') NOT NULL DEFAULT 'pegawai',
    face_descriptor TEXT NULL COMMENT 'JSON array 128-dimensi data wajah',
    gaji_pokok DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nip (nip),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABEL ABSENSI
-- =============================================
CREATE TABLE IF NOT EXISTS absensi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    tanggal DATE NOT NULL,
    waktu_masuk TIME NULL,
    waktu_keluar TIME NULL,
    status ENUM('hadir', 'terlambat') DEFAULT 'hadir',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_date (user_id, tanggal),
    INDEX idx_tanggal (tanggal),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABEL PAYROLL
-- =============================================
CREATE TABLE IF NOT EXISTS payroll (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    bulan INT NOT NULL CHECK (bulan BETWEEN 1 AND 12),
    tahun INT NOT NULL,
    total_kehadiran INT NOT NULL DEFAULT 0,
    total_gaji DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_bulan_tahun (user_id, bulan, tahun),
    INDEX idx_bulan_tahun (bulan, tahun)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- DATA AWAL - ADMIN DEFAULT
-- =============================================
-- Password: admin123 (hashed dengan password_hash)
INSERT INTO users (nip, nama_lengkap, password, role, gaji_pokok) VALUES
('999999', 'Administrator Sistem', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 0.00);

-- =============================================
-- DATA CONTOH - PEGAWAI
-- =============================================
-- Password untuk semua employee: employee123
INSERT INTO users (nip, nama_lengkap, password, role, gaji_pokok) VALUES
('100001', 'Budi Santoso', '$2y$10$YGKrDQf9xQJvJQZ8vGQJ5uqoVvZMqQXKGQWpLz9qZ7sN4GfJwvV7.', 'pegawai', 5000000.00),
('100002', 'Siti Rahayu', '$2y$10$YGKrDQf9xQJvJQZ8vGQJ5uqoVvZMqQXKGQWpLz9qZ7sN4GfJwvV7.', 'pegawai', 4500000.00),
('100003', 'Ahmad Fauzi', '$2y$10$YGKrDQf9xQJvJQZ8vGQJ5uqoVvZMqQXKGQWpLz9qZ7sN4GfJwvV7.', 'pegawai', 5500000.00);
