<?php
// Authenticated administrator-only SEO patch importer for EXISTING, PUBLISHED yojna posts.
// No create/delete, no status/slug/category/image edits, no wholesale content replacement.
defined('ESY') or exit;
require_once ROOT . '/app/draft_import.php';
require_perm('posts.publish');
require_perm('settings.manage');
header('Cache-Control: no-store');
$seo_err = '';
$seo_info = [];
$seo_preview = $_SESSION['seo_patch_pending'] ?? null;

function seo_patch_validate(array $raw): array {
    if (($raw['schema'] ?? null) !== 'esy-seo-patch-v1') {
        throw new InvalidArgumentException('Expected schema esy-seo-patch-v1');
    }
    $items = $raw['updates'] ?? null;
    if (!is_array($items) || !array_is_list($items) || count($items) < 1 || count($items) > 25) {
        throw new InvalidArgumentException('Expected 1 to 25 SEO updates');
    }
    $out = [];
    $seen = [];
    foreach ($items as $i => $entry) {
        if (!is_array($entry)) throw new InvalidArgumentException("Invalid update row $i");
        $slug = draft_import_text($entry, 'slug', 150, true);
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || isset($seen[$slug])) {
            throw new InvalidArgumentException("Invalid or duplicate slug: $slug");
        }
        $seen[$slug] = true;
        $data = ['slug' => $slug];
        foreach (['seo_title' => 255, 'seo_title_en' => 255, 'seo_desc' => 500, 'seo_desc_en' => 500, 'keywords' => 500] as $k => $max) {
            $data[$k] = draft_import_text($entry, $k, $max, $k !== 'keywords');
        }
        $links = $entry['related_links'] ?? null;
        if (!is_array($links) || !array_is_list($links) || count($links) < 2 || count($links) > 5) {
            throw new InvalidArgumentException("Specify 2–5 related links for $slug");
        }
        $data['related_links'] = [];
        $targets = [];
        foreach ($links as $l) {
            if (!is_array($l) || ($l['type'] ?? '') !== 'yojna') throw new InvalidArgumentException("Related link must be a yojna for $slug");
            $target = draft_import_text($l, 'slug', 150, true);
            if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $target) || $target === $slug || isset($targets[$target])) {
                throw new InvalidArgumentException("Invalid/duplicate related scheme: $target");
            }
            $targets[$target] = true;
            $data['related_links'][] = ['slug' => $target,
                'label_hi' => draft_import_text($l, 'label_hi', 130, true),
                'label_en' => draft_import_text($l, 'label_en', 130, true)];
        }
        $out[] = $data;
    }
    return $out;
}

function seo_patch_validate_db(array $items): array {
    $rows = [];
    foreach ($items as $item) {
        $row = q_one("SELECT id, slug, title_hi, title_en, status, seo_title, seo_title_en, seo_desc, seo_desc_en, keywords, content_hi, content_en FROM posts WHERE type = 'yojna' AND slug = ?", [$item['slug']]);
        if (!$row || $row['status'] !== 'published') throw new InvalidArgumentException('Published scheme not found: ' . $item['slug']);
        if (trim((string)$row['content_hi']) === '' || trim((string)$row['content_en']) === '') {
            throw new InvalidArgumentException('Bilingual scheme content missing: ' . $item['slug']);
        }
        foreach ($item['related_links'] as $link) {
            if (!q_val("SELECT id FROM posts WHERE type = 'yojna' AND status = 'published' AND slug = ?", [$link['slug']])) {
                throw new InvalidArgumentException('Related scheme not published: ' . $link['slug']);
            }
        }
        $rows[$item['slug']] = $row;
    }
    return $rows;
}

function seo_patch_related_html(array $item, string $lang): string {
    $heading = $lang === 'hi' ? 'इन सरकारी योजनाओं की जानकारी भी देखें' : 'Explore related government schemes';
    $parts = [];
    foreach ($item['related_links'] as $l) {
        $target = '/'.$lang.'/yojna/'.$l['slug'];
        $label = $l[$lang === 'hi' ? 'label_hi' : 'label_en'];
        $parts[] = '<li><a href="' . e($target) . '">' . e($label) . '</a></li>';
    }
    return "\n<!-- esy-related-v1-start -->\n<h2>" . e($heading) . "</h2>\n<ul>" . implode("\n", $parts) . "</ul>\n<!-- esy-related-v1-end -->";
}

function seo_patch_content(string $content, array $item, string $lang): string {
    $start = '<!-- esy-related-v1-start -->';
    $end = '<!-- esy-related-v1-end -->';
    $addition = seo_patch_related_html($item, $lang);
    $pos = strpos($content, $start);
    if ($pos === false) return $content . $addition;
    $endpos = strpos($content, $end, $pos);
    if ($endpos === false) throw new InvalidArgumentException('Existing SEO block is incomplete for ' . $item['slug']);
    return substr($content, 0, $pos) . trim($addition) . substr($content, $endpos + strlen($end));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seo_upload'])) {
    try {
        $file = $_FILES['seo_json'] ?? null;
        if (!$file || (int)$file['error'] !== UPLOAD_ERR_OK || (int)$file['size'] > 1000000
            || (int)$file['size'] < 5 || !is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException('Choose JSON file up to 1 MB');
        }
        $raw = json_decode((string)file_get_contents($file['tmp_name']), true, 30, JSON_THROW_ON_ERROR);
        $items = seo_patch_validate($raw);
        $live = seo_patch_validate_db($items);
        $_SESSION['seo_patch_pending'] = [
            'items' => $items,
            'hashes' => array_map(static fn($r) => hash('sha256', json_encode($r, JSON_UNESCAPED_UNICODE)), $live),
        ];
        $seo_preview = $_SESSION['seo_patch_pending'];
        flash(count($items) . ' published yojna SEO changes ready for preview. No changes made yet.');
        redirect('/admin/?p=seo_batch');
    } catch (Throwable $e) {
        unset($_SESSION['seo_patch_pending']);
        $seo_preview = null;
        $seo_err = $e instanceof InvalidArgumentException || $e instanceof JsonException ? $e->getMessage() : 'Could not validate JSON';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seo_cancel'])) {
    unset($_SESSION['seo_patch_pending']);
    flash('SEO update preview discarded; nothing changed.');
    redirect('/admin/?p=seo_batch');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seo_confirm'])) {
    try {
        if (!is_array($seo_preview) || empty($seo_preview['items'])) throw new InvalidArgumentException('Preview expired. Upload JSON again.');
        $items = $seo_preview['items'];
        db()->beginTransaction();
        $live = seo_patch_validate_db($items);
        foreach ($live as $slug => $row) {
            $actual = hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE));
            if (!hash_equals((string)($seo_preview['hashes'][$slug] ?? ''), $actual)) {
                throw new InvalidArgumentException('Record changed since preview: ' . $slug . '. Upload JSON again.');
            }
        }
        foreach ($items as $item) {
            $row = $live[$item['slug']];
            $hi = seo_patch_content($row['content_hi'], $item, 'hi');
            $en = seo_patch_content($row['content_en'], $item, 'en');
            $st = q("UPDATE posts SET seo_title=?, seo_title_en=?, seo_desc=?, seo_desc_en=?, keywords=?, content_hi=?, content_en=?, updated_at=NOW() WHERE id=? AND type='yojna' AND status='published'",
                [$item['seo_title'], $item['seo_title_en'], $item['seo_desc'], $item['seo_desc_en'], $item['keywords'], $hi, $en, (int)$row['id']]);
            if ($st->rowCount() !== 1) throw new RuntimeException('Update unexpectedly failed: ' . $item['slug']);
        }
        db()->commit();
        foreach ($items as $item) log_activity('seo_batch_update', $item['slug']);
        unset($_SESSION['seo_patch_pending']);
        flash(count($items) . ' existing published schemes updated. Status and original content preserved. Verify live pages.');
        redirect('/admin/?p=seo_batch');
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        $seo_err = $e instanceof InvalidArgumentException ? $e->getMessage() : 'SEO update failed; see server logs';
        if (!$e instanceof InvalidArgumentException) error_log('SEO batch update error: ' . $e->getMessage());
    }
}
admin_header('SEO Update — Existing Schemes', 'seo_batch');
?>
<?php if ($seo_err): ?><div class="alert err"><?= e($seo_err) ?></div><?php endif; ?>
<div class="card">
  <h2>Safe SEO updates for published yojna pages</h2>
  <p>This tool ONLY updates Hindi/English SEO titles, descriptions, search keywords, and a related-schemes link section. It does not change published status, URL slugs, category, main article, featured image or official source.</p>
  <p><strong>Do not upload a Draft Import JSON here.</strong> This page accepts only <code>esy-seo-patch-v1</code> SEO update files.</p>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="file" name="seo_json" accept=".json,application/json" required>
    <button class="btn primary" name="seo_upload" value="1">Preview SEO Update JSON</button>
  </form>
</div>
<?php if (is_array($seo_preview) && !empty($seo_preview['items'])): ?>
<div class="card">
  <h2>Preview <?= count($seo_preview['items']) ?> existing published schemes</h2>
  <p class="muted">Check every target and its links. Changes will be made only after confirmation. All listed targets were verified against the CMS when this preview was prepared.</p>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Existing page</th><th>New Hindi / English SEO titles</th><th>Related internal links</th></tr></thead>
    <tbody>
      <?php foreach ($seo_preview['items'] as $item): ?>
      <tr>
        <td><b><?= e($item['slug']) ?></b><br><a href="/hi/yojna/<?= e($item['slug']) ?>" target="_blank">Current live page ↗</a></td>
        <td><?= e($item['seo_title']) ?><br><small><?= e($item['seo_title_en']) ?></small></td>
        <td><?= count($item['related_links']) ?> related published scheme links<br>
          <?php foreach ($item['related_links'] as $link): ?><small><?= e($link['label_hi']) ?> → <?= e($link['slug']) ?></small><br><?php endforeach; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <div style="display:flex;gap:12px;flex-wrap:wrap">
    <form method="post"><?= csrf_field() ?><button class="btn primary" name="seo_confirm" value="1" onclick="return confirm('Update SEO metadata and internal links for all these live pages? This does not unpublish them.')">Confirm SEO Updates</button></form>
    <form method="post"><?= csrf_field() ?><button class="btn ghost" name="seo_cancel" value="1">Cancel</button></form>
  </div>
</div>
<?php endif; ?>
<?php admin_footer();
