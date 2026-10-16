<?php
defined('ESY') or exit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        $c = q_one('SELECT * FROM blog_categories WHERE id = ?', [(int)$_POST['delete']]);
        if ($c) {
            // Sub-categories move up to this category's parent; posts become uncategorised.
            q('UPDATE blog_categories SET parent_id = ? WHERE parent_id = ?', [$c['parent_id'], $c['id']]);
            q('UPDATE posts SET blog_cat_id = NULL WHERE blog_cat_id = ?', [$c['id']]);
            q('DELETE FROM blog_categories WHERE id = ?', [$c['id']]);
            log_activity('delete_blog_category', $c['name_hi']);
            flash('Category delete ho gayi. Uski sub-categories upar ki category me chali gayi.');
        }
        redirect('/admin/?p=blog_categories');
    }
    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name_hi'] ?? ''));
    $nameEn = trim((string)($_POST['name_en'] ?? ''));
    $slug = slugify(trim((string)($_POST['slug'] ?? '')) ?: ($nameEn ?: $name));
    $parent = (int)($_POST['parent_id'] ?? 0) ?: null;
    // A category cannot be placed under itself or one of its own sub-categories.
    if ($id && $parent && in_array($parent, blog_cat_ids_with_children($id), true)) $parent = null;
    $vals = [$parent, $slug, $name, $nameEn, trim((string)($_POST['description_hi'] ?? '')), trim((string)($_POST['description_en'] ?? '')), (int)($_POST['sort'] ?? 0)];
    if ($name === '') flash('Hindi naam zaroori hai.', 'err');
    elseif (q_val('SELECT id FROM blog_categories WHERE slug = ? AND id <> ?', [$slug, $id])) flash('Ye slug pehle se hai. Dusra rakhein.', 'err');
    elseif ($id) {
        q('UPDATE blog_categories SET parent_id=?, slug=?, name_hi=?, name_en=?, description_hi=?, description_en=?, sort=? WHERE id=?', array_merge($vals, [$id]));
        log_activity('update_blog_category', $name);
        flash('Update ho gaya ✔');
    } else {
        q('INSERT INTO blog_categories (parent_id, slug, name_hi, name_en, description_hi, description_en, sort) VALUES (?,?,?,?,?,?,?)', $vals);
        log_activity('create_blog_category', $name);
        flash('Category ban gayi ✔');
    }
    redirect('/admin/?p=blog_categories');
}

$tree = blog_cat_tree();
$counts = array_column(q_all("SELECT blog_cat_id, COUNT(*) n FROM posts WHERE type = 'blog' GROUP BY blog_cat_id"), 'n', 'blog_cat_id');
$edit = isset($_GET['edit']) ? q_one('SELECT * FROM blog_categories WHERE id = ?', [(int)$_GET['edit']]) : null;
$e = $edit ?: ['id' => 0, 'parent_id' => (int)($_GET['parent'] ?? 0), 'slug' => '', 'name_hi' => '', 'name_en' => '', 'description_hi' => '', 'description_en' => '', 'sort' => 0];
$parentOpts = ['' => '— Main category (koi parent nahi) —'];
foreach ($tree as $c) if (!$edit || !in_array((int)$c['id'], blog_cat_ids_with_children((int)$edit['id']), true)) $parentOpts[$c['id']] = str_repeat('— ', $c['depth']) . $c['name_hi'];

admin_header('Blog Categories', 'blog_categories', '<a class="btn ghost" href="/hi/blog" target="_blank">Blog dekhein ↗</a>');
?>
<div class="grid2" style="align-items:start">
  <form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
    <h2><?= $edit ? 'Category edit karein' : 'Nayi category / sub-category' ?></h2>
    <?= f_select('parent_id', 'Parent category', $e['parent_id'] ?: '', $parentOpts, ['help' => 'Sub-category banane ke liye uski main category chunein.']) ?>
    <div class="grid2">
      <?= f_text('name_hi', 'Naam (हिंदी) *', $e['name_hi'], ['required' => true]) ?>
      <?= f_text('name_en', 'Name (English)', $e['name_en']) ?>
    </div>
    <div class="grid2">
      <?= f_text('slug', 'URL slug', $e['slug'], ['placeholder' => 'auto', 'help' => 'Link: /hi/blog/category/<b>slug</b>']) ?>
      <?= f_text('sort', 'Order', $e['sort'], ['type' => 'number']) ?>
    </div>
    <?= f_area('description_hi', 'Description (हिंदी), category page par dikhega', $e['description_hi'], ['rows' => 2]) ?>
    <?= f_area('description_en', 'Description (English)', $e['description_en'], ['rows' => 2]) ?>
    <div class="row-actions"><?php if ($edit): ?><a class="btn ghost" href="/admin/?p=blog_categories">Cancel</a><?php endif; ?><button class="btn primary"><?= $edit ? 'Update' : '+ Add' ?></button></div>
  </form>
  <div class="card">
    <h2>Saari categories</h2>
    <?php if (!$tree): ?><p class="muted">Abhi koi category nahi. Left side form se pehli category banaiye, jaise "सरकारी नौकरी", "किसान", "समाचार".</p><?php endif; ?>
    <table class="tbl">
      <tbody>
      <?php foreach ($tree as $c): ?>
        <tr>
          <td style="padding-left:<?= 0.6 + $c['depth'] * 1.4 ?>rem"><?= $c['depth'] ? '↳ ' : '📁 ' ?><b><?= e($c['name_hi']) ?></b><?= $c['name_en'] ? ' <small class="muted">/ ' . e($c['name_en']) . '</small>' : '' ?><br><small class="muted">/blog/category/<?= e($c['slug']) ?></small></td>
          <td class="num"><?= (int)($counts[$c['id']] ?? 0) ?> post</td>
          <td class="actions">
            <a class="btn ghost sm" href="/admin/?p=blog_categories&parent=<?= $c['id'] ?>" title="Iske andar sub-category banayein">+ Sub</a>
            <a class="btn ghost sm" href="/admin/?p=blog_categories&edit=<?= $c['id'] ?>">Edit</a>
            <form method="post" onsubmit="return confirm('Category delete karein? Iske posts bina category ke ho jayenge.')"><?= csrf_field() ?><button class="btn danger sm" name="delete" value="<?= $c['id'] ?>">✕</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php admin_footer();
