<?php
require __DIR__ . '/../app/web.php';
if (current_user()) redirect('/dashboard.php');
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $nama = trim($_POST['nama'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass = $_POST['password'] ?? '';
    if ($nama === '' || strlen($nama) > 100) $err = 'Nama wajib diisi (maks. 100 karakter).';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Format email tidak valid.';
    elseif (strlen($pass) < 8) $err = 'Password minimal 8 karakter.';
    else {
        try {
            db()->prepare("INSERT INTO users (nama,email,password_hash,role) VALUES (?,?,?,'siswa')")
                ->execute([$nama, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            flash('ok', 'Akun berhasil dibuat. Silakan masuk.');
            redirect('/login.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') $err = 'Email sudah terdaftar.'; else throw $ex;
        }
    }
}
page_header('Daftar Akun');
?>
<div class="card" style="max-width:460px;margin:auto">
  <h2>Buat Akun</h2>
  <?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Nama</label><input name="nama" required maxlength="100" value="<?= e($_POST['nama'] ?? '') ?>">
    <label>Email</label><input type="email" name="email" required maxlength="150" value="<?= e($_POST['email'] ?? '') ?>">
    <label>Password (min. 8 karakter)</label><input type="password" name="password" required minlength="8">
    <p><button>Daftar</button></p>
  </form>
</div>
<?php page_footer();
