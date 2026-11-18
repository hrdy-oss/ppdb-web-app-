#!/bin/bash
# User data untuk Launch Template lks-lt-web (Ubuntu 22.04/24.04). EDIT variabel di bagian atas.
# Penting: aplikasi di-clone ke /var/www/ppdb dan Apache DocumentRoot diarahkan ke /var/www/ppdb/public
set -euxo pipefail
export DEBIAN_FRONTEND=noninteractive

REPO_URL="https://github.com/USERNAME/REPO.git"
DB_HOST="lks-db.xxxxxxxx.REGION.rds.amazonaws.com"
DB_NAME="ppdb"
DB_USER="admin"
DB_PASS="GANTI_PASSWORD_DB"
S3_BUCKET="lks-ppdb-namapeserta"
S3_REGION="ap-southeast-1"   # samakan dengan region bucket (mis. us-east-1 di Learner Lab)
AWS_ACCESS_KEY_ID=""         # kosongkan jika memakai IAM Role
AWS_SECRET_ACCESS_KEY=""
AWS_SESSION_TOKEN=""

apt-get update
apt-get install -y apache2 libapache2-mod-php php-mysql php-curl php-cli git

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
chown root:www-data /var/www/ppdb/.env
chmod 640 /var/www/ppdb/.env

for d in /etc/php/*/apache2/conf.d; do
  printf "upload_max_filesize = 3M\npost_max_size = 4M\nexpose_php = Off\n" > "$d/99-ppdb.ini"
done

cat > /etc/apache2/sites-available/ppdb.conf <<'CONF'
<VirtualHost *:80>
    DocumentRoot /var/www/ppdb/public
    <Directory /var/www/ppdb/public>
        Options -Indexes
        Require all granted
    </Directory>
</VirtualHost>
CONF
sed -i 's/^ServerTokens.*/ServerTokens Prod/' /etc/apache2/conf-available/security.conf
a2dissite 000-default
a2ensite ppdb
systemctl enable apache2
systemctl restart apache2
