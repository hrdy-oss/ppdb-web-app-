<?php
require __DIR__ . '/../app/web.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';
    if ($act === 'status' && in_array($_POST['status'] ?? '', ['menunggu', 'diterima', 'ditolak'], true)) {
        db()->prepare('UPDATE pendaftar SET status=? WHERE id=?')->execute([$_POST['status'], $id]);
        flash('ok', 'Status diperbarui.');
    } elseif ($act === 'delete') {
        $st = db()->prepare('SELECT berkas_key FROM pendaftar WHERE id=?');
        $st->execute([$id]);
        $key = $st->fetchColumn();
        if ($key) s3()->delete($key);
        db()->prepare('DELETE FROM pendaftar WHERE id=?')->execute([$id]);
        flash('ok', 'Data pendaftar dihapus.');
    }
    redirect('/admin.php');
}

$filter = $_GET['status'] ?? '';
$where = in_array($filter, ['menunggu', 'diterima', 'ditolak'], true) ? 'WHERE p.status=' . db()->quote($filter) : '';
$rows = db()->query("SELECT p.*, u.email FROM pendaftar p JOIN users u ON u.id=p.user_id $where ORDER BY p.id DESC LIMIT 200")->fetchAll();
$users = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='siswa'")->fetchColumn();

page_header('Admin');
?>
<div class="card">
  <h2>Panel Admin</h2>
  <p>Akun siswa terdaftar: <b><?= $users ?></b> |
    Filter: <a href="/admin.php">Semua</a> · <a href="?status=menunggu">Menunggu</a> · <a href="?status=diterima">Diterima</a> · <a href="?status=ditolak">Ditolak</a></p>
  <div style="overflow-x:auto"><table>
    <tr><th>#</th><th>Nama / Email</th><th>NISN</th><th>Tujuan</th><th>Jalur</th><th>Nilai</th><th>Berkas</th><th>Status</th><th>Aksi</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= e($r['nama_lengkap']) ?><br><small><?= e($r['email']) ?></small></td>
      <td><?= e($r['nisn']) ?></td><td><?= e($r['sekolah_tujuan']) ?></td><td><?= e(JALUR[$r['jalur']]) ?></td><td><?= e($r['nilai_rata']) ?></td>
      <td><?php if ($r['berkas_key']): ?><a href="/file.php?id=<?= (int)$r['id'] ?>" target="_blank">Lihat</a><?php else: ?>-<?php endif; ?></td>
      <td><?= status_badge($r['status']) ?></td>
      <td>
        <form class="inline" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="status">
          <button name="status" value="diterima" class="green">Terima</button>
          <button name="status" value="ditolak" class="red">Tolak</button></form>
        <form class="inline" method="post" onsubmit="return confirm('Hapus data ini?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="red">Hapus</button></form>
      </td>
    </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="9">Belum ada data.</td></tr><?php endif; ?>
  </table></div>
</div>
<?php page_footer();
