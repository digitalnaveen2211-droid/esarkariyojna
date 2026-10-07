<?php
defined('ESY') or exit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        q('DELETE FROM categories WHERE id = ?', [(int)$_POST['delete']]);
        log_activity('delete_category', (string)$_POST['delete']);
        flash('Category delete ho gayi. Uski yojnayein "koi category nahi" me chali gayi.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name_hi'] ?? ''));
        $slug = slugify(trim((string)($_POST['slug'] ?? '')) ?: (trim((string)($_POST['name_en'] ?? '')) ?: $name));
        $vals = [$slug, $name, trim((string)($_POST['name_en'] ?? '')), mb_substr(trim((string)($_POST['icon'] ?? '')), 0, 8), valid_color((string)($_POST['color'] ?? ''), '#eef2ff'), (int)($_POST['sort'] ?? 0)];
        if ($name === '') flash('Hindi naam zaroori hai.', 'err');
        elseif (q_val('SELECT id FROM categories WHERE slug = ? AND id <> ?', [$slug, $id])) flash('Ye slug pehle se hai.', 'err');
        elseif ($id) { q('UPDATE categories SET slug=?, name_hi=?, name_en=?, icon=?, color=?, sort=? WHERE id=?', array_merge($vals, [$id])); flash('Update ho gaya ✔'); log_activity('update_category', $name); }
        else { q('INSERT INTO categories (slug, name_hi, name_en, icon, color, sort) VALUES (?,?,?,?,?,?)', $vals); flash('Category ban gayi ✔'); log_activity('create_category', $name); }
    }
    redirect('/admin/?p=categories');
}

$rows = q_all('SELECT c.*, (SELECT COUNT(*) FROM posts p WHERE p.category_id = c.id) n FROM categories c ORDER BY sort, id');
admin_header('Categories', 'categories');
?>
<div class="card">
  <p class="muted">Har row edit karke <b>Save</b> dabaiye. Niche nayi category jodiye.</p>
  <div class="tbl-wrap"><table class="tbl cat-tbl">
    <thead><tr><th>Icon</th><th>Naam (हिंदी)</th><th>Name (English)</th><th>Slug</th><th>Rang</th><th>Order</th><th class="num">Yojna</th><th></th></tr></thead>
    <tbody>
    <?php foreach (array_merge($rows, [['id' => 0, 'slug' => '', 'name_hi' => '', 'name_en' => '', 'icon' => '', 'color' => '#eef2ff', 'sort' => count($rows), 'n' => '']]) as $c): $f = 'cat' . $c['id']; ?>
      <tr<?= $c['id'] ? '' : ' class="new-row"' ?>>
        <td><input form="<?= $f ?>" name="icon" value="<?= e($c['icon']) ?>" class="xs" placeholder="🏷️"></td>
        <td><input form="<?= $f ?>" name="name_hi" value="<?= e($c['name_hi']) ?>" placeholder="<?= $c['id'] ? '' : '+ Nayi category' ?>"></td>
        <td><input form="<?= $f ?>" name="name_en" value="<?= e($c['name_en']) ?>"></td>
        <td><input form="<?= $f ?>" name="slug" value="<?= e($c['slug']) ?>" placeholder="auto"></td>
        <td><input form="<?= $f ?>" type="color" name="color" value="<?= e($c['color']) ?>"></td>
        <td><input form="<?= $f ?>" type="number" name="sort" value="<?= (int)$c['sort'] ?>" class="xs"></td>
        <td class="num"><?= e($c['n']) ?></td>
        <td class="actions">
          <form id="<?= $f ?>" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn <?= $c['id'] ? 'ghost' : 'primary' ?> sm"><?= $c['id'] ? 'Save' : '+ Add' ?></button></form>
          <?php if ($c['id']): ?><form method="post" onsubmit="return confirm('Category delete karein?')"><?= csrf_field() ?><button class="btn danger sm" name="delete" value="<?= $c['id'] ?>">✕</button></form><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php admin_footer();
