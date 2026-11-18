<?php
// Pemakaian: php bin/create-admin.php email@contoh.id "Nama Admin" PasswordKuat123
require dirname(__DIR__) . '/app/bootstrap.php';
if (PHP_SAPI !== 'cli') exit(1);
[$self, $email, $nama, $pass] = $argv + [null, null, null, null];
if (!$email || !$nama || !$pass || strlen($pass) < 8) {
    fwrite(STDERR, "Pemakaian: php bin/create-admin.php email \"Nama\" password(min 8)\n");
    exit(1);
}
$st = db()->prepare("INSERT INTO users (nama,email,password_hash,role) VALUES (?,?,?,'admin')
                     ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), role='admin'");
$st->execute([$nama, strtolower($email), password_hash($pass, PASSWORD_DEFAULT)]);
echo "Admin siap: $email\n";
