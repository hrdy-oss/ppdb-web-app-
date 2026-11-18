<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

const SEKOLAH_TUJUAN = ['SMAN 1 Harapan', 'SMAN 2 Harapan', 'SMKN 1 Harapan', 'SMKN 2 Harapan', 'SMAN 3 Nusa Raya'];
const JALUR = ['zonasi' => 'Zonasi', 'prestasi' => 'Prestasi', 'afirmasi' => 'Afirmasi', 'perpindahan' => 'Perpindahan Tugas Orang Tua'];

// ---- Sesi disimpan di database (agar konsisten di banyak web server) ----
class DbSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }
    public function read(string $id): string|false
    {
        $st = db()->prepare('SELECT data FROM sessions WHERE id=? AND updated_at>?');
        $st->execute([$id, time() - 7200]);
        $r = $st->fetchColumn();
        return $r === false ? '' : (string)$r;
    }
    public function write(string $id, string $data): bool
    {
        return db()->prepare('REPLACE INTO sessions (id,data,updated_at) VALUES (?,?,?)')->execute([$id, $data, time()]);
    }
    public function destroy(string $id): bool
    {
        return db()->prepare('DELETE FROM sessions WHERE id=?')->execute([$id]);
    }
    public function gc(int $max): int|false
    {
        $st = db()->prepare('DELETE FROM sessions WHERE updated_at<?');
        $st->execute([time() - $max]);
        return $st->rowCount();
    }
}

set_exception_handler(function (Throwable $e) {
    error_log((string)$e);
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Error</title><h1>Terjadi kesalahan pada server</h1><p>Silakan coba lagi beberapa saat.</p>';
});

$https = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_save_handler(new DbSessionHandler(), true);
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

// ---- Helper ----
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $path): never { header('Location: ' . $path); exit; }
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }

function csrf_field(): string
{
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}
function csrf_verify(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Sesi formulir tidak valid. Muat ulang halaman.');
    }
}

function current_user(): ?array
{
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $st = db()->prepare('SELECT id,nama,email,role FROM users WHERE id=?');
            $st->execute([$_SESSION['uid']]);
            $u = $st->fetch() ?: null;
        }
    }
    return $u;
}
function require_login(): array { return current_user() ?? redirect('/login.php'); }
function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') { http_response_code(403); exit('Akses ditolak.'); }
    return $u;
}

// ---- Tampilan ----
function page_header(string $title): void
{
    $u = current_user();
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . e($title) . ' - PPDB Harapan</title><style>' . <<<'CSS'
*{box-sizing:border-box}body{margin:0;font:16px/1.55 system-ui,sans-serif;background:#f4f6fb;color:#1d2433}
nav{background:#1f3a6e;color:#fff;padding:.8rem 1.2rem;display:flex;gap:1rem;align-items:center;flex-wrap:wrap}
nav a{color:#fff;text-decoration:none}nav .brand{font-weight:700;margin-right:auto}
main{max-width:980px;margin:1.5rem auto;padding:0 1rem}
.card{background:#fff;border-radius:10px;padding:1.2rem 1.4rem;box-shadow:0 1px 4px #0001;margin-bottom:1rem}
label{display:block;margin:.7rem 0 .2rem;font-weight:600}
input,select{width:100%;padding:.55rem .7rem;border:1px solid #c5ccdb;border-radius:6px;font:inherit}
button,.btn{background:#2d5bd8;color:#fff;border:0;border-radius:6px;padding:.55rem 1rem;font:inherit;cursor:pointer;text-decoration:none;display:inline-block}
button.red{background:#c0392b}button.green{background:#1e8449}
table{width:100%;border-collapse:collapse}th,td{padding:.5rem;border-bottom:1px solid #e3e7f0;text-align:left;font-size:.93rem}
.badge{padding:.1rem .6rem;border-radius:99px;font-size:.8rem;color:#fff}.menunggu{background:#b9770e}.diterima{background:#1e8449}.ditolak{background:#c0392b}
.flash{padding:.7rem 1rem;border-radius:6px;margin-bottom:1rem}.flash.ok{background:#d5f5e3}.flash.err{background:#fadbd8}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem}.stats b{font-size:1.8rem;display:block}
footer{text-align:center;color:#667;font-size:.85rem;padding:1.5rem}form.inline{display:inline}
CSS
       . '</style></head><body><nav><a class="brand" href="/">PPDB Online Harapan</a><a href="/pendaftar.php">Daftar Pendaftar</a>';
    if ($u) {
        if ($u['role'] === 'admin') echo '<a href="/admin.php">Admin</a>';
        else echo '<a href="/dashboard.php">Pendaftaran Saya</a>';
        echo '<a href="/logout.php">Keluar (' . e($u['nama']) . ')</a>';
    } else {
        echo '<a href="/login.php">Masuk</a><a href="/register.php">Daftar Akun</a>';
    }
    echo '</nav><main>';
    foreach ($_SESSION['flash'] ?? [] as [$t, $m]) echo '<div class="flash ' . ($t === 'ok' ? 'ok' : 'err') . '">' . e($m) . '</div>';
    unset($_SESSION['flash']);
}

function page_footer(): void
{
    echo '</main><footer>Dilayani oleh server: <b>' . e(gethostname() ?: 'unknown') . '</b></footer></body></html>';
}

function status_badge(string $s): string { return '<span class="badge ' . e($s) . '">' . e(ucfirst($s)) . '</span>'; }
