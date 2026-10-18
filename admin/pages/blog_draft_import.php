<?php
defined('ESY') or exit;
require_perm('settings.manage');
require_perm('posts.publish');
require_once ROOT . '/app/blog_draft_import.php';
header('Cache-Control: no-store');

$error = '';
$results = $_SESSION['blog_import_results'] ?? [];
unset($_SESSION['blog_import_results']);
$pending = $_SESSION['pending_blog_bundle'] ?? null;

function blog_pending_file(?array $pending): string {
    $path = (string)($pending['file'] ?? '');
    $base = basename($path);
    if (!preg_match('/^esy-blog-[a-f0-9]{32}\.zip$/D', $base)
        || dirname($path) !== sys_get_temp_dir()
        || (int)($pending['uid'] ?? 0) !== (int)current_user()['id']
        || (int)($pending['ts'] ?? 0) < time() - 900 || !is_file($path)) {
        throw new InvalidArgumentException('Preview expired. Upload ZIP again.');
    }
    return $path;
}
function blog_clear_pending(): void {
    $old = $_SESSION['pending_blog_bundle'] ?? null;
    $path = (string)($old['file'] ?? '');
    if (preg_match('/^esy-blog-[a-f0-9]{32}\.zip$/D', basename($path)) && dirname($path) === sys_get_temp_dir()) @unlink($path);
    unset($_SESSION['pending_blog_bundle']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview_blog_zip'])) {
    blog_clear_pending();
    $file = $_FILES['blog_zip'] ?? null;
    if (!$file || (int)$file['error'] !== UPLOAD_ERR_OK || (int)$file['size'] < 100 || (int)$file['size'] > 8000000 || !is_uploaded_file($file['tmp_name'])) {
        $error = 'Upload a valid blog ZIP (up to 8 MB).';
    } else {
        $dest = sys_get_temp_dir() . '/esy-blog-' . bin2hex(random_bytes(16)) . '.zip';
        try {
            if (!move_uploaded_file($file['tmp_name'], $dest)) throw new RuntimeException('Cannot store preview ZIP.');
            $preview = blog_bundle_inspect($dest);
            foreach ($preview['blogs'] as $blog) {
                if (q_val("SELECT id FROM posts WHERE type='blog' AND (slug=? OR title_hi=? OR title_en=?) LIMIT 1",
                    [$blog['slug'], $blog['title_hi'], $blog['title_en']])) {
                    throw new InvalidArgumentException('Blog already exists in CMS (draft or published): ' . $blog['slug']);
                }
            }
            $_SESSION['pending_blog_bundle'] = ['file'=>$dest,'uid'=>(int)current_user()['id'],'ts'=>time()];
            redirect('/admin/?p=blog_draft_import');
        } catch (Throwable $ex) {
            @unlink($dest);
            $error = $ex instanceof InvalidArgumentException || $ex instanceof JsonException ? $ex->getMessage() : 'Could not process ZIP; check server logs.';
            if ($error === 'Could not process ZIP; check server logs.') error_log('Blog preview: ' . $ex->getMessage());
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_blog_import'])) {
    blog_clear_pending();
    flash('Blog preview discarded. No draft was created.');
    redirect('/admin/?p=blog_draft_import');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_blog_import'])) {
    $written = [];
    try {
        $file = blog_pending_file($pending);
        $bundle = blog_bundle_inspect($file);
        $blogCategories = blog_categories();
        $allowedIds = array_fill_keys(array_map(static fn($c) => (int)$c['id'], $blogCategories), true);
        $selected = $_POST['blog_cat_ids'] ?? null;
        if (!is_array($selected)) throw new InvalidArgumentException('Choose categories for all articles.');
        $approved = [];
        foreach ($bundle['blogs'] as $i => $blog) {
            $catRaw = (string)($selected[$i] ?? '');
            $catId = $catRaw === '' ? null : filter_var($catRaw, FILTER_VALIDATE_INT);
            if ($catId !== null && ($catId === false || !isset($allowedIds[$catId]))) throw new InvalidArgumentException('Unknown blog category at row ' . ($i+1));
            $approved[] = ['post'=>$blog,'cat_id'=>$catId];
        }
        $targetDir = UPLOAD_DIR . '/blog';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) throw new RuntimeException('Media directory unavailable.');
        db()->beginTransaction();
        foreach ($approved as $item) {
            $blog = $item['post'];
            if (q_val("SELECT id FROM posts WHERE type='blog' AND (slug=? OR title_hi=? OR title_en=?) LIMIT 1 FOR UPDATE",
                [$blog['slug'],$blog['title_hi'],$blog['title_en']])) throw new InvalidArgumentException('Blog already exists: ' . $blog['slug']);
        }
        $links = [];
        foreach ($approved as $item) {
            $blog = $item['post'];
            $related = [];
            foreach ($blog['related_posts'] as $link) {
                if (q_val("SELECT id FROM posts WHERE type=? AND slug=? AND status='published' LIMIT 1", [$link['type'],$link['slug']])) $related[] = $link;
            }
            $img = $bundle['images'][$blog['banner']];
            $relative = '/uploads/blog/' . $blog['slug'] . '-' . bin2hex(random_bytes(7)) . '.jpg';
            $dest = ROOT . $relative;
            if (file_put_contents($dest, $img, LOCK_EX) !== strlen($img)) throw new RuntimeException('Cannot write banner image.');
            @chmod($dest, 0644);
            $written[] = $dest;
            // posts.elig_hi and posts.elig_en are NOT NULL in the shared CMS schema.
            // They apply to schemes, but blog records must still explicitly store empty strings.
            $columns = ['slug','blog_cat_id','title_hi','title_en','summary_hi','summary_en',
                'elig_hi','elig_en','content_hi','content_en','seo_title','seo_title_en',
                'seo_desc','seo_desc_en','keywords','image','author_id'];
            $values = [$blog['slug'],$item['cat_id'],$blog['title_hi'],$blog['title_en'],
                $blog['summary_hi'],$blog['summary_en'],'','',
                blog_bundle_render($blog,'hi',$related),blog_bundle_render($blog,'en',$related),
                $blog['seo_title'],$blog['seo_title_en'],$blog['seo_desc'],$blog['seo_desc_en'],
                (string)($blog['keywords'] ?? ''),$relative,(int)current_user()['id']];
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            q("INSERT INTO posts (type," . implode(',', $columns) . ",status,featured,created_at,updated_at)
                VALUES ('blog',$placeholders,'draft',0,NOW(),NOW())", $values);
            $links[] = ['id'=>(int)db()->lastInsertId(),'title'=>$blog['title_hi']];
        }
        db()->commit();
        foreach ($approved as $item) log_activity('blog_draft_bundle_import', $item['post']['slug']);
        blog_clear_pending();
        $_SESSION['blog_import_results'] = $links;
        flash(count($links) . ' complete bilingual blogs imported as DRAFT with featured images. Nothing published.');
        redirect('/admin/?p=blog_draft_import');
    } catch (Throwable $ex) {
        if (db()->inTransaction()) db()->rollBack();
        foreach ($written as $path) @unlink($path);
        $error = $ex instanceof InvalidArgumentException ? $ex->getMessage() : 'Import failed. No blogs created. Check server logs.';
        // Show a safe category of failure to the administrator, not database SQL,
        // filesystem paths, table names or credential-bearing exception details.
        if ($ex instanceof PDOException) $error = 'Database save failed. No blogs created. Contact the developer with the server log entry.';
        elseif ($ex instanceof RuntimeException && str_contains(strtolower($ex->getMessage()), 'banner')) $error = 'Banner upload failed. Check uploads/blog directory permissions.';
        if (!$ex instanceof InvalidArgumentException) error_log('Blog ZIP import: ' . $ex->getMessage());
    }
}

$preview = null;
if (is_array($_SESSION['pending_blog_bundle'] ?? null)) {
    try { $preview = blog_bundle_inspect(blog_pending_file($_SESSION['pending_blog_bundle'])); }
    catch (Throwable $ex) { blog_clear_pending(); $error = $error ?: 'Preview expired. Please upload ZIP again.'; }
}
admin_header('Blog Draft Import', 'blog_draft_import');
?>
<?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
<?php if ($results): ?><div class="card"><h2>Successfully imported — DRAFT only</h2>
<?php foreach ($results as $item): ?>
<p>✅ <a href="/admin/?p=post_edit&type=blog&id=<?= (int)$item['id'] ?>"><?= e($item['title']) ?></a></p>
<?php endforeach; ?></div><?php endif; ?>
<div class="card">
  <h2>Import bilingual Blog Draft ZIP</h2>
  <p>One package: <code>blog_drafts.json</code> + 1–5 matching <code>banners/slug.jpg</code>. Includes Hindi/English title, summary, HTML articles generated from safe blocks (tables, headings, FAQs), metadata, valid internal links and featured banner photos.</p>
  <p>Maximum 8 MB compressed. No paid API, no automatic publishing, no token exposed. Upload → Preview → Select Blog categories → Confirm DRAFT. Existing blog slugs and titles are never overwritten.</p>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="file" name="blog_zip" required accept=".zip,application/zip">
    <button type="submit" class="btn primary" name="preview_blog_zip" value="1">Preview Blog ZIP</button>
  </form>
</div>
<?php if ($preview): ?>
<div class="card">
<h2>Preview <?= count($preview['blogs']) ?> blogs (none saved yet)</h2>
<p class="muted">Review the source links and content in the downloaded JSON first. Banner images will be copied to Media storage only when you confirm. Missing related posts are ignored to prevent broken links.</p>
<form method="post">
  <?= csrf_field() ?>
  <?php foreach ($preview['blogs'] as $i => $blog): ?>
  <div style="padding:14px 0;border-bottom:1px solid #ddd">
    <h3><?= (int)($i+1) ?>. <?= e($blog['title_hi']) ?></h3>
    <p><?= e($blog['title_en']) ?></p>
    <p><small>Slug: <?= e($blog['slug']) ?> · <?= e($blog['banner']) ?> · SEO: <?= e($blog['seo_title']) ?></small></p>
    <p><?= e($blog['summary_hi']) ?></p>
    <label>Blog category
      <select name="blog_cat_ids[<?= (int)$i ?>]">
        <option value="">Uncategorized</option>
        <?php foreach (blog_categories() as $cat): ?>
          <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name_hi']) ?> / <?= e($cat['name_en']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <?php endforeach; ?>
  <p><button class="btn primary" name="confirm_blog_import" value="1" onclick="return confirm('Create all these blog DRAFTS with banner images? No publishing will occur.')">Create <?= count($preview['blogs']) ?> Blog Drafts</button>
  <button class="btn ghost" name="cancel_blog_import" value="1">Cancel</button></p>
</form></div>
<?php endif; ?>
<div class="card"><h3>After import</h3><p>Go to <a href="/admin/?p=blog&status=draft">Blog → Draft</a>; open each article, inspect Hindi/English content, category, banner and SEO. Publish manually only after final fact check. ZIP is stored temporarily for up to 15 minutes and removed on confirmation/cancel.</p></div>
<?php admin_footer();
