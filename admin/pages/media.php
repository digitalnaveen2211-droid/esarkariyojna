<?php
defined('ESY') or exit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        $m = q_one('SELECT * FROM media WHERE id = ?', [(int)$_POST['delete']]);
        if ($m) {
            $file = realpath(ROOT . $m['path']);
            if ($file && strpos($file, realpath(UPLOAD_DIR)) === 0) @unlink($file);
            q('DELETE FROM media WHERE id = ?', [$m['id']]);
            log_activity('delete_media', $m['path']);
            flash('Image delete ho gayi.');
        }
    } else {
        $n = 0;
        $files = $_FILES['files'] ?? null;
        if ($files && is_array($files['name'])) {
            foreach ($files['name'] as $i => $name) {
                if ($name === '') continue;
                [$url, $err] = handle_upload(['name' => $name, 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]]);
                if ($err) flash("$name: $err", 'err'); else $n++;
            }
        }
        if ($n) { flash("$n image upload ho gayi ✔"); log_activity('upload_media', "$n files"); }
    }
    redirect('/admin/?p=media');
}

$per = 48;
$pg = max(1, (int)($_GET['pg'] ?? 1));
$total = (int)q_val('SELECT COUNT(*) FROM media');
$rows = q_all('SELECT m.*, u.name uname FROM media m LEFT JOIN users u ON u.id = m.uploaded_by ORDER BY m.id DESC LIMIT ' . $per . ' OFFSET ' . (($pg - 1) * $per));
admin_header('Media Library', 'media');
?>
<form method="post" enctype="multipart/form-data" class="card upload-box">
  <?= csrf_field() ?>
  <label class="drop">⬆ Images chunein ya yahan drop karein<input type="file" name="files[]" accept="image/*" multiple onchange="this.form.submit()"></label>
  <small class="muted">JPG, PNG, WEBP, GIF, ICO · max 5 MB har file</small>
</form>
<div class="media-grid">
  <?php foreach ($rows as $m): ?>
    <figure class="media-item">
      <img src="<?= e($m['path']) ?>" alt="" loading="lazy">
      <figcaption>
        <input readonly value="<?= e($m['path']) ?>" onclick="this.select();navigator.clipboard&&navigator.clipboard.writeText(this.value)" title="Click karke URL copy karein">
        <small class="muted"><?= e(round($m['size'] / 1024)) ?> KB · <?= e($m['uname'] ?? '') ?></small>
        <form method="post" onsubmit="return confirm('Image delete karein? Jahan use hui hai wahan nahi dikhegi.')"><?= csrf_field() ?><button class="btn danger sm" name="delete" value="<?= $m['id'] ?>">Delete</button></form>
      </figcaption>
    </figure>
  <?php endforeach; if (!$rows): ?><p class="muted">Abhi koi image nahi.</p><?php endif; ?>
</div>
<?= paginate($total, $per, $pg, ['p' => 'media']) ?>
<?php admin_footer();
