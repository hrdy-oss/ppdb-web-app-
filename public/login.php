<?php
require __DIR__ . '/../app/web.php';
if (current_user()) redirect('/dashboard.php');
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $st = db()->prepare('SELECT * FROM users WHERE email=?');
    $st->execute([$email]);
    $u = $st->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        redirect($u['role'] === 'admin' ? '/admin.php' : '/dashboard.php');
    }
    $err = 'Email atau password salah.';
}
page_header('Masuk');
?>
<div class="card" style="max-width:460px;margin:auto">
  <h2>Masuk</h2>
  <?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Email</label><input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
    <label>Password</label><input type="password" name="password" required>
    <p><button>Masuk</button></p>
  </form>
</div>
<?php page_footer();
