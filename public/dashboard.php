<?php
require __DIR__ . '/../app/web.php';
$user = require_login();
if ($user['role'] === 'admin') redirect('/admin.php');

$st = db()->prepare('SELECT * FROM pendaftar WHERE user_id=?');
$st->execute([$user['id']]);
$p = $st->fetch() ?: null;
$locked = $p && $p['status'] !== 'menunggu';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if ($locked) { flash('err', 'Data sudah diverifikasi dan tidak dapat diubah.'); redirect('/dashboard.php'); }

    $nama = trim($_POST['nama_lengkap'] ?? '');
    $nisn = trim($_POST['nisn'] ?? '');
    $asal = trim($_POST['asal_sekolah'] ?? '');
    $tujuan = $_POST['sekolah_tujuan'] ?? '';
    $jalur = $_POST['jalur'] ?? '';
    $nilai = $_POST['nilai_rata'] ?? '';

    if ($nama === '' || strlen($nama) > 100) $errors[] = 'Nama lengkap wajib diisi.';
    if (!preg_match('/^\d{10}$/', $nisn)) $errors[] = 'NISN harus 10 digit angka.';
    if ($asal === '' || strlen($asal) > 100) $errors[] = 'Asal sekolah wajib diisi.';
    if (!in_array($tujuan, SEKOLAH_TUJUAN, true)) $errors[] = 'Sekolah tujuan tidak valid.';
    if (!isset(JALUR[$jalur])) $errors[] = 'Jalur pendaftaran tidak valid.';
    if (!is_numeric($nilai) || $nilai < 0 || $nilai > 100) $errors[] = 'Nilai rata-rata harus 0-100.';

    // Validasi & unggah berkas ke S3
    $newKey = $newName = $newMime = null;
    $f = $_FILES['berkas'] ?? null;
    if ($f && $f['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
        $mime = $f['error'] === UPLOAD_ERR_OK ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) : '';
        if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 2 * 1024 * 1024) $errors[] = 'Berkas gagal diunggah atau lebih dari 2 MB.';
        elseif (!isset($allowed[$mime])) $errors[] = 'Berkas harus PDF, JPG, atau PNG.';
        elseif (!$errors) {
            $newKey = 'berkas/' . $user['id'] . '/' . bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
            if (!s3()->put($newKey, file_get_contents($f['tmp_name']), $mime)) {
                $errors[] = 'Gagal menyimpan berkas ke object storage.';
                $newKey = null;
            } else {
                $newName = substr(preg_replace('/[^A-Za-z0-9._-]/', '_', basename($f['name'])), 0, 150);
                $newMime = $mime;
            }
        }
    }
    if (!$p && !$newKey && !$errors) $errors[] = 'Berkas persyaratan wajib diunggah.';

    if (!$errors) {
        if ($p) {
            $sql = 'UPDATE pendaftar SET nama_lengkap=?,nisn=?,asal_sekolah=?,sekolah_tujuan=?,jalur=?,nilai_rata=?';
            $args = [$nama, $nisn, $asal, $tujuan, $jalur, $nilai];
            if ($newKey) { $sql .= ',berkas_key=?,berkas_nama=?,berkas_mime=?'; array_push($args, $newKey, $newName, $newMime); }
            db()->prepare($sql . ' WHERE id=?')->execute([...$args, $p['id']]);
            if ($newKey && $p['berkas_key']) s3()->delete($p['berkas_key']);
        } else {
            db()->prepare('INSERT INTO pendaftar (user_id,nama_lengkap,nisn,asal_sekolah,sekolah_tujuan,jalur,nilai_rata,berkas_key,berkas_nama,berkas_mime)
                           VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([$user['id'], $nama, $nisn, $asal, $tujuan, $jalur, $nilai, $newKey, $newName, $newMime]);
        }
        flash('ok', 'Data pendaftaran berhasil disimpan.');
        redirect('/dashboard.php');
    }
    if ($newKey) s3()->delete($newKey); // batalkan unggahan jika data lain tidak valid
    $p = array_merge($p ?? [], $_POST);
}

$v = fn(string $k) => e((string)($p[$k] ?? ''));
page_header('Pendaftaran Saya');
?>
<div class="card">
  <h2>Formulir Pendaftaran</h2>
  <?php if (!empty($p['status'])): ?><p>Status: <?= status_badge($p['status']) ?></p><?php endif; ?>
  <?php foreach ($errors as $er): ?><div class="flash err"><?= e($er) ?></div><?php endforeach; ?>
  <?php if ($locked): ?><p>Data Anda sudah diverifikasi sehingga tidak dapat diubah.</p><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label>Nama Lengkap</label><input name="nama_lengkap" required maxlength="100" value="<?= $v('nama_lengkap') ?>" <?= $locked ? 'disabled' : '' ?>>
    <label>NISN (10 digit)</label><input name="nisn" required pattern="\d{10}" value="<?= $v('nisn') ?>" <?= $locked ? 'disabled' : '' ?>>
    <label>Asal Sekolah</label><input name="asal_sekolah" required maxlength="100" value="<?= $v('asal_sekolah') ?>" <?= $locked ? 'disabled' : '' ?>>
    <label>Sekolah Tujuan</label>
    <select name="sekolah_tujuan" <?= $locked ? 'disabled' : '' ?>>
      <?php foreach (SEKOLAH_TUJUAN as $s): ?><option <?= ($p['sekolah_tujuan'] ?? '') === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
    </select>
    <label>Jalur</label>
    <select name="jalur" <?= $locked ? 'disabled' : '' ?>>
      <?php foreach (JALUR as $k => $l): ?><option value="<?= e($k) ?>" <?= ($p['jalur'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
    <label>Nilai Rata-rata Rapor (0-100)</label><input name="nilai_rata" type="number" step="0.01" min="0" max="100" required value="<?= $v('nilai_rata') ?>" <?= $locked ? 'disabled' : '' ?>>
    <label>Berkas Persyaratan (PDF/JPG/PNG, maks. 2 MB)</label>
    <?php if (!empty($p['berkas_key'])): ?>
      <p>Berkas saat ini: <a href="/file.php?id=<?= (int)$p['id'] ?>" target="_blank"><?= e($p['berkas_nama']) ?></a></p>
    <?php endif; ?>
    <input type="file" name="berkas" accept=".pdf,.jpg,.jpeg,.png" <?= $locked ? 'disabled' : '' ?>>
    <?php if (!$locked): ?><p><button>Simpan</button></p><?php endif; ?>
  </form>
</div>
<?php page_footer();
