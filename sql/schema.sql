-- Jalankan pada database "ppdb" di RDS MySQL
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('siswa','admin') NOT NULL DEFAULT 'siswa',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pendaftar (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  nama_lengkap VARCHAR(100) NOT NULL,
  nisn CHAR(10) NOT NULL,
  asal_sekolah VARCHAR(100) NOT NULL,
  sekolah_tujuan VARCHAR(100) NOT NULL,
  jalur ENUM('zonasi','prestasi','afirmasi','perpindahan') NOT NULL,
  nilai_rata DECIMAL(5,2) NOT NULL,
  berkas_key VARCHAR(255) DEFAULT NULL,
  berkas_nama VARCHAR(150) DEFAULT NULL,
  berkas_mime VARCHAR(50) DEFAULT NULL,
  status ENUM('menunggu','diterima','ditolak') NOT NULL DEFAULT 'menunggu',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pendaftar_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sesi disimpan di database agar login tetap valid walau request berpindah antar web server
CREATE TABLE IF NOT EXISTS sessions (
  id VARCHAR(128) PRIMARY KEY,
  data MEDIUMTEXT NOT NULL,
  updated_at INT UNSIGNED NOT NULL,
  KEY idx_sessions_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
