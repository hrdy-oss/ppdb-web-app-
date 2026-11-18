<?php
// Health check untuk Target Group ALB. Tambahkan ?deep=1 untuk ikut menguji koneksi database.
http_response_code(200);
header('Content-Type: text/plain');
if (isset($_GET['deep'])) {
    require __DIR__ . '/../app/bootstrap.php';
    try { db()->query('SELECT 1'); echo "OK db\n"; }
    catch (Throwable $e) { http_response_code(503); echo "DB DOWN\n"; }
    exit;
}
echo "OK\n";
