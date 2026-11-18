<?php
// Berkas disimpan privat di S3; hanya admin atau pemilik berkas yang boleh mengunduh (melalui aplikasi).
require __DIR__ . '/../app/web.php';
$user = require_login();
$st = db()->prepare('SELECT * FROM pendaftar WHERE id=?');
$st->execute([(int)($_GET['id'] ?? 0)]);
$p = $st->fetch();
if (!$p || !$p['berkas_key'] || ($user['role'] !== 'admin' && (int)$p['user_id'] !== (int)$user['id'])) {
    http_response_code(404);
    exit('Berkas tidak ditemukan.');
}
[$code, $body] = s3()->get($p['berkas_key']);
if ($code !== 200) { http_response_code(502); exit('Gagal mengambil berkas dari object storage.'); }
header('Content-Type: ' . $p['berkas_mime']);
header('Content-Disposition: inline; filename="' . $p['berkas_nama'] . '"');
header('Content-Length: ' . strlen($body));
header('Cache-Control: private, no-store');
echo $body;
