#!/bin/bash
# User data untuk Launch Template lks-lt-web (Amazon Linux 2023). EDIT variabel di bagian atas.
set -euxo pipefail

REPO_URL="https://github.com/USERNAME/REPO.git"
DB_HOST="lks-db.xxxxxxxx.ap-southeast-1.rds.amazonaws.com"
DB_NAME="ppdb"
DB_USER="admin"
DB_PASS="GANTI_PASSWORD_DB"
S3_BUCKET="lks-ppdb-namapeserta"
S3_REGION="ap-southeast-1"
AWS_ACCESS_KEY_ID=""        # kosongkan jika memakai IAM Role
AWS_SECRET_ACCESS_KEY=""
AWS_SESSION_TOKEN=""

dnf install -y httpd git php8.2 php8.2-mysqlnd php8.2-pdo php8.2-cli

git clone --depth 1 "$REPO_URL" /var/www/ppdb

cat > /var/www/ppdb/.env <<ENVEOF
DB_HOST=$DB_HOST
DB_PORT=3306
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASS=$DB_PASS
S3_BUCKET=$S3_BUCKET
S3_REGION=$S3_REGION
AWS_ACCESS_KEY_ID=$AWS_ACCESS_KEY_ID
AWS_SECRET_ACCESS_KEY=$AWS_SECRET_ACCESS_KEY
AWS_SESSION_TOKEN=$AWS_SESSION_TOKEN
ENVEOF
chown root:apache /var/www/ppdb/.env
chmod 640 /var/www/ppdb/.env

cat > /etc/php.d/99-ppdb.ini <<'INI'
upload_max_filesize = 3M
post_max_size = 4M
expose_php = Off
INI

cat > /etc/httpd/conf.d/ppdb.conf <<'CONF'
ServerTokens Prod
<VirtualHost *:80>
    DocumentRoot /var/www/ppdb/public
    <Directory /var/www/ppdb/public>
        Options -Indexes
        Require all granted
    </Directory>
</VirtualHost>
CONF

systemctl enable --now php-fpm httpd
