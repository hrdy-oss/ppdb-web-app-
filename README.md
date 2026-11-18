# PPDB Online Harapan (PHP)

Aplikasi pendaftaran peserta didik baru untuk soal LKS Cloud Computing (tingkat provinsi).
Fitur: register/login, formulir pendaftaran + unggah berkas ke S3, daftar pendaftar (publik, NISN disamarkan), halaman admin (verifikasi, hapus).

- PHP 8.1+ murni (tanpa Composer); S3 diakses lewat Signature V4 buatan sendiri (`app/s3.php`)
- Sesi PHP disimpan di MySQL, jadi aman dipakai di belakang load balancer
- `GET /health.php` untuk health check ALB (`?deep=1` menguji database)
- Konfigurasi lewat `.env` (lihat `.env.example`); panduan lengkap: `deploy/PANDUAN_DEPLOY.md`

Struktur: `public/` (document root), `app/`, `sql/schema.sql`, `bin/create-admin.php`, `deploy/`.

Deploy: `deploy/user-data-web.sh` (Amazon Linux 2023) atau `deploy/user-data-web-ubuntu.sh` (Ubuntu). Document root Apache wajib `public/`.
