<?php
require __DIR__ . '/../app/web.php';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 15;
$like = '%' . addcslashes($q, '%_\\') . '%';

$st = db()->prepare('SELECT COUNT(*) FROM pendaftar WHERE nama_lengkap LIKE ?');
$st->execute([$like]);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);

$st = db()->prepare('SELECT id,nama_lengkap,nisn,sekolah_tujuan,jalur,status,created_at FROM pendaftar WHERE nama_lengkap LIKE :q ORDER BY id DESC LIMIT :l OFFSET :o');
$st->bindValue(':q', $like);
$st->bindValue(':l', $per, PDO::PARAM_INT);
$st->bindValue(':o', ($page - 1) * $per, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();

page_header('Daftar Pendaftar');
?>
<div class="card">
  <h2>Daftar Pendaftar (<?= $total ?>)</h2>
  <form method="get" style="display:flex;gap:.5rem"><input name="q" placeholder="Cari nama..." value="<?= e($q) ?>"><button>Cari</button></form>
  <table>
    <tr><th>#</th><th>Nama</th><th>NISN</th><th>Sekolah Tujuan</th><th>Jalur</th><th>Status</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= (int)$r['id'] ?></td><td><?= e($r['nama_lengkap']) ?></td><td><?= e(substr($r['nisn'], 0, 4)) ?>******</td>
          <td><?= e($r['sekolah_tujuan']) ?></td><td><?= e(JALUR[$r['jalur']]) ?></td><td><?= status_badge($r['status']) ?></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="6">Belum ada data.</td></tr><?php endif; ?>
  </table>
  <p>
    <?php if ($page > 1): ?><a class="btn" href="?q=<?= urlencode($q) ?>&page=<?= $page - 1 ?>">&laquo; Sebelumnya</a><?php endif; ?>
    Halaman <?= $page ?> / <?= $pages ?>
    <?php if ($page < $pages): ?><a class="btn" href="?q=<?= urlencode($q) ?>&page=<?= $page + 1 ?>">Berikutnya &raquo;</a><?php endif; ?>
  </p>
</div>
<?php page_footer();
