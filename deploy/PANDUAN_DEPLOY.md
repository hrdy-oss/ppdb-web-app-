# Panduan Deploy - PPDB Online Harapan (LKS Cloud Computing Provinsi)

Panduan ini sekaligus kunci jawaban versi konsol AWS. Semua resource di **ap-southeast-1** dengan prefix **lks-**.

## 0. Persiapan
1. Push seluruh isi folder ini ke repo GitHub (public, agar bisa di-clone tanpa token). `.env` sudah ada di `.gitignore`.
2. Siapkan kredensial S3 dari lab (Access Key, Secret, Session Token) bila tidak ada IAM Role.

## Catatan OS dan region (baca dulu)
- **Amazon Linux 2023**: pakai `deploy/user-data-web.sh` (dnf, httpd, php-fpm).
- **Ubuntu 22.04/24.04**: pakai `deploy/user-data-web-ubuntu.sh` (apt, apache2, mod_php, user `www-data`).
- Document root Apache **harus** `.../public`, bukan folder repo. Jika halaman pertama berupa "Index of /" dengan folder app/, bin/, sql/, berarti document root masih default; semua isi repo (termasuk `.env`) bisa terbaca dari internet. Cek: `http://<IP>/.env` harus 404.
- Learner Lab biasanya hanya mengizinkan us-east-1/us-west-2. Jika begitu, ganti `ap-southeast-1` di seluruh panduan, `S3_REGION` pada `.env`, dan nama AZ (mis. us-east-1a/us-east-1b).

## 1. Jaringan (VPC)
| Resource | Nama | Nilai |
|---|---|---|
| VPC | lks-vpc | 10.20.0.0/16 |
| Subnet public | lks-public-a / lks-public-b | 10.20.1.0/24 (1a) / 10.20.2.0/24 (1b) |
| Subnet private app | lks-app-a / lks-app-b | 10.20.11.0/24 / 10.20.12.0/24 |
| Subnet private db | lks-db-a / lks-db-b | 10.20.21.0/24 / 10.20.22.0/24 |
| Internet Gateway | lks-igw | attach ke lks-vpc |
| NAT Gateway | lks-nat | di lks-public-a, dengan Elastic IP |
| Route table | lks-rt-public | 0.0.0.0/0 -> lks-igw; asosiasi: lks-public-a/b |
| Route table | lks-rt-private | 0.0.0.0/0 -> lks-nat; asosiasi: lks-app-a/b dan lks-db-a/b |
| VPC Endpoint (Gateway) | lks-vpce-s3 | layanan S3, route table lks-rt-private |

Aktifkan "Auto-assign public IPv4" hanya pada subnet public.

## 2. Security Group (semua di lks-vpc)
| Nama | Inbound |
|---|---|
| lks-sg-alb | TCP 80 dari 0.0.0.0/0 |
| lks-sg-bastion | TCP 22 dari My IP (atau 0.0.0.0/0 pada lab) |
| lks-sg-web | TCP 80 dari lks-sg-alb; TCP 22 dari lks-sg-bastion |
| lks-sg-db | TCP 3306 dari lks-sg-web dan lks-sg-bastion |

## 3. Database (RDS)
1. DB subnet group **lks-db-subnet**: subnet lks-db-a dan lks-db-b.
2. Create database: MySQL 8.0, template Dev/Test, identifier **lks-db**, instance db.t3.micro, user `admin`, initial database name **ppdb**, **Public access: No**, SG **lks-sg-db**.

## 4. Object Storage (S3)
1. Bucket **lks-ppdb-namapeserta** (nama harus unik global), region ap-southeast-1.
2. **Block all public access = ON** (berkas hanya diakses lewat aplikasi).

## 5. Bastion
EC2 **lks-bastion** (Amazon Linux 2023, t3.micro) di lks-public-a, SG lks-sg-bastion, key pair **lks-key**.

## 6. Inisialisasi database dan akun admin (dari bastion)
```bash
ssh -i lks-key.pem ec2-user@<IP_PUBLIK_BASTION>
sudo dnf install -y git mariadb105 php8.2-cli php8.2-mysqlnd php8.2-pdo
git clone https://github.com/USERNAME/REPO.git app && cd app
mysql -h <ENDPOINT_RDS> -u admin -p ppdb < sql/schema.sql
cp .env.example .env && nano .env        # isi DB_HOST, DB_PASS, dst
php bin/create-admin.php admin@ppdb.local "Admin PPDB" "PasswordKuat123"
```

## 7. Launch Template dan Auto Scaling
1. Launch template **lks-lt-web**: Amazon Linux 2023, t3.micro, SG lks-sg-web, key pair lks-key, **User data** = isi `deploy/user-data-web.sh` (setelah variabel diedit).
2. Target group **lks-tg-web**: instance, HTTP 80, health check path `/health.php`.
3. Application Load Balancer **lks-alb**: internet-facing, subnet lks-public-a/b, SG lks-sg-alb, listener HTTP 80 -> lks-tg-web.
4. Auto Scaling Group **lks-asg-web**: launch template lks-lt-web, subnet **lks-app-a/b**, attach ke lks-tg-web, health check ELB (grace 300 dtk), min 2 / desired 2 / max 4, target tracking CPU 50%.
5. Tag Name setiap instance: `lks-web` (ditetapkan di launch template).

## 8. Verifikasi
1. Buka `http://<DNS_ALB>`; refresh berkali-kali, nama server di footer berganti-ganti.
2. Daftar akun siswa, isi formulir, unggah PDF; objek muncul di S3 pada prefix `berkas/`.
3. Login admin, ubah status pendaftar, buka berkas.
4. Terminate satu instance: aplikasi tetap bisa diakses, ASG membuat instance pengganti.
5. Coba akses langsung endpoint RDS dari internet: harus gagal.

## Troubleshooting
- Target unhealthy: cek `sudo cat /var/log/cloud-init-output.log` di instance (lewat bastion), pastikan NAT berfungsi (git clone butuh internet).
- Upload gagal: cek kredensial S3 di `.env` (session token lab kedaluwarsa), cek nama bucket dan region.
- Muncul "Index of /" atau file repo terlihat: document root belum ke `public/`. Ubuntu: `sudo sed -i 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#' /etc/apache2/sites-available/000-default.conf && sudo systemctl restart apache2` (atau pakai user-data Ubuntu yang sudah benar).
- Cek ekstensi PHP: `php -m | grep -Ei "curl|fileinfo|pdo_mysql"`; bila ada yang kurang, `sudo dnf install php8.2-<nama>`.
- Login terus kembali ke halaman masuk: pastikan tabel `sessions` ada (sudah ada di `sql/schema.sql`).
