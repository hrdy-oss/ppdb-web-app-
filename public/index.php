<?php
require __DIR__ . '/../app/web.php';
$c = ['menunggu' => 0, 'diterima' => 0, 'ditolak' => 0];
foreach (db()->query('SELECT status, COUNT(*) n FROM pendaftar GROUP BY status') as $r) $c[$r['status']] = (int)$r['n'];
page_header('Beranda');
?>
<div class="card">
  <h1>PPDB Online Provinsi Nusa Raya</h1>
  <p>Pendaftaran Peserta Didik Baru tingkat SMA/SMK. Buat akun, lengkapi data, unggah berkas persyaratan, lalu pantau status pendaftaran Anda.</p>
  <?php if (!current_user()): ?>
    <a class="btn" href="/register.php">Buat Akun</a> <a class="btn" href="/login.php" style="background:#555">Masuk</a>
  <?php else: ?>
    <a class="btn" href="/dashboard.php">Buka Pendaftaran Saya</a>
  <?php endif; ?>
</div>
<div class="stats">
  <div class="card"><b><?= $c['menunggu'] ?></b>Menunggu verifikasi</div>
  <div class="card"><b><?= $c['diterima'] ?></b>Diterima</div>
  <div class="card"><b><?= $c['ditolak'] ?></b>Ditolak</div>
</div>
<?php page_footer();
