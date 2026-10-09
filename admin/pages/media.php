<?php
defined('ESY') or exit;

$oldFmt = "(path LIKE '%.jpg' OR path LIKE '%.jpeg' OR path LIKE '%.png' OR path LIKE '%.gif')";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert_webp'])) {
    // Convert older JPG/PNG/GIF uploads to WebP in small batches, and point every place that used them to the new file.
    // The original file is kept on disk so old shared links / cached pages keep working.
    $done = $skipped = 0;
    $start = microtime(true);
    $skipIds = array_map('intval', (array)($_SESSION['webp_skip'] ?? []));
    $rows = q_all("SELECT * FROM media WHERE $oldFmt" . ($skipIds ? ' AND id NOT IN (' . implode(',', $skipIds) . ')' : '') . ' ORDER BY id LIMIT 30');
    foreach ($rows as $m) {
        if (microtime(true) - $start > 20) break;
        $src = ROOT . $m['path'];
        $ext = strtolower(pathinfo($m['path'], PATHINFO_EXTENSION));
        $newPath = preg_replace('/\.[a-z]+$/i', '.webp', $m['path']);
        if (!is_file($src) || !to_webp($src, $ext, ROOT . $newPath)) { $_SESSION['webp_skip'][] = (int)$m['id']; $skipped++; continue; }
        q('UPDATE media SET path = ?, size = ? WHERE id = ?', [$newPath, (int)filesize(ROOT . $newPath), $m['id']]);
        $r = [$m['path'], $newPath];
        q('UPDATE posts SET image = REPLACE(image, ?, ?), content_hi = REPLACE(content_hi, ?, ?), content_en = REPLACE(content_en, ?, ?)', [...$r, ...$r, ...$r]);
        q('UPDATE settings SET v = REPLACE(v, ?, ?)', $r);
        try { q('UPDATE ads SET image = REPLACE(image, ?, ?), html = REPLACE(html, ?, ?)', [...$r, ...$r]); } catch (Throwable $e) {}
        $done++;
    }
    log_activity('convert_webp', "$done converted, $skipped skipped");
    flash("$done images WebP me badal gayi ✔" . ($skipped ? " · $skipped nahi badal payi (animated GIF ya file missing), wo waise hi rahengi." : ''));
    redirect('/admin/?p=media');
}

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
<?php $skipIds = array_map('intval', (array)($_SESSION['webp_skip'] ?? []));
$pending = (int)q_val("SELECT COUNT(*) FROM media WHERE $oldFmt" . ($skipIds ? ' AND id NOT IN (' . implode(',', $skipIds) . ')' : ''));
if ($pending && function_exists('imagewebp')): ?>
<form method="post" class="card form-row" style="justify-content:space-between;align-items:center">
  <?= csrf_field() ?>
  <span><b><?= $pending ?></b> purani image JPG/PNG/GIF me hain. Inhe WebP me badalne se site tez chalegi. Jahan-jahan ye lagi hain (yojna, settings, ads), wahan link apne aap badal jayega.</span>
  <button class="btn primary" name="convert_webp" value="1">⚡ WebP me badlein</button>
</form>
<?php endif; ?>
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
