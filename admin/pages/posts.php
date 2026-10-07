<?php
defined('ESY') or exit;

$type = ($p === 'pages') ? 'page' : 'yojna';
$canDelete = $type === 'page' ? can('pages.manage') : can('posts.publish');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if (!$canDelete) { flash('Delete ki permission nahi hai.', 'err'); }
    else {
        $row = q_one('SELECT title_hi FROM posts WHERE id = ? AND type = ?', [(int)$_POST['delete'], $type]);
        q('DELETE FROM posts WHERE id = ? AND type = ?', [(int)$_POST['delete'], $type]);
        log_activity('delete_' . $type, $row['title_hi'] ?? '');
        flash('Delete ho gaya.');
    }
    redirect('/admin/?p=' . $p);
}

$s = trim((string)($_GET['s'] ?? ''));
$status = in_array($_GET['status'] ?? '', ['published', 'draft'], true) ? $_GET['status'] : '';
$cat = (int)($_GET['cat'] ?? 0);
$where = ['p.type = ?'];
$params = [$type];
if ($s !== '') { $where[] = '(p.title_hi LIKE ? OR p.title_en LIKE ? OR p.slug LIKE ?)'; array_push($params, "%$s%", "%$s%", "%$s%"); }
if ($status) { $where[] = 'p.status = ?'; $params[] = $status; }
if ($cat) { $where[] = 'p.category_id = ?'; $params[] = $cat; }
$rows = q_all('SELECT p.*, c.name_hi cat_name, u.name author,
    (SELECT COUNT(*) FROM visits v WHERE v.path = CONCAT(?, p.slug) AND v.ts >= ?) views30
    FROM posts p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN users u ON u.id = p.author_id
    WHERE ' . implode(' AND ', $where) . ' ORDER BY p.sort, p.id', array_merge([$type === 'yojna' ? '/yojna/' : '/page/', date('Y-m-d', strtotime('-30 days'))], $params));

$label = $type === 'yojna' ? 'Yojnayein' : 'Pages';
admin_header($label, $p, '<a class="btn primary" href="/admin/?p=post_edit&type=' . $type . '">+ ' . ($type === 'yojna' ? 'Nayi Yojna' : 'Naya Page') . '</a>');
?>
<form class="filters card" method="get">
  <input type="hidden" name="p" value="<?= e($p) ?>">
  <input type="search" name="s" value="<?= e($s) ?>" placeholder="Title ya slug se khojein...">
  <select name="status"><option value="">Sab status</option><option value="published"<?= $status === 'published' ? ' selected' : '' ?>>Published</option><option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>Draft</option></select>
  <?php if ($type === 'yojna'): ?>
  <select name="cat"><option value="0">Sab categories</option><?php foreach (categories() as $c): ?><option value="<?= $c['id'] ?>"<?= $cat === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['icon'] . ' ' . $c['name_hi']) ?></option><?php endforeach; ?></select>
  <?php endif; ?>
  <button class="btn sm">Filter</button>
</form>
<div class="card"><div class="tbl-wrap"><table class="tbl">
  <thead><tr><th>Title</th><?php if ($type === 'yojna'): ?><th>Category</th><?php endif; ?><th>Status</th><th class="num">Views (30 din)</th><th>Update</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $url = ($type === 'yojna' ? '/yojna/' : '/page/') . $r['slug']; ?>
    <tr>
      <td><a class="strong" href="/admin/?p=post_edit&type=<?= $type ?>&id=<?= $r['id'] ?>"><?= e(trim($r['icon'] . ' ' . $r['title_hi'])) ?></a><?= $r['featured'] ? ' <span class="badge star">⭐ Featured</span>' : '' ?><br><small class="muted"><?= e($url) ?></small></td>
      <?php if ($type === 'yojna'): ?><td><?= e($r['cat_name'] ?? '—') ?></td><?php endif; ?>
      <td><span class="badge <?= $r['status'] === 'published' ? 'green' : 'gray' ?>"><?= $r['status'] === 'published' ? 'Live' : 'Draft' ?></span></td>
      <td class="num"><?= (int)$r['views30'] ?></td>
      <td><small><?= e(time_ago($r['updated_at'])) ?><?= $r['author'] ? '<br>' . e($r['author']) : '' ?></small></td>
      <td class="actions">
        <a class="btn ghost sm" href="/admin/?p=post_edit&type=<?= $type ?>&id=<?= $r['id'] ?>">Edit</a>
        <?php if ($r['status'] === 'published'): ?><a class="btn ghost sm" href="<?= e($url) ?>" target="_blank">View</a><?php endif; ?>
        <?php if ($canDelete): ?><form method="post" onsubmit="return confirm('Pakka delete karna hai?')"><?= csrf_field() ?><button class="btn danger sm" name="delete" value="<?= $r['id'] ?>">Delete</button></form><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="muted">Kuch nahi mila.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php admin_footer();
