<?php
defined('ESY') or exit;

$perms = all_permissions();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        $r = q_one('SELECT * FROM roles WHERE id = ?', [(int)$_POST['delete']]);
        if (!$r || $r['is_system']) flash('Ye role delete nahi ho sakta.', 'err');
        elseif (q_val('SELECT COUNT(*) FROM users WHERE role_id = ?', [$r['id']])) flash('Is role me users hain. Pehle unka role badlein.', 'err');
        else { q('DELETE FROM roles WHERE id = ?', [$r['id']]); flash('Role delete ho gaya.'); log_activity('delete_role', $r['name']); }
    } elseif (isset($_POST['new_role'])) {
        $name = trim((string)$_POST['new_role']);
        $slug = slugify($name);
        if ($name === '' || q_val('SELECT id FROM roles WHERE slug = ?', [$slug])) flash('Naam khali hai ya pehle se hai.', 'err');
        else { q('INSERT INTO roles (slug, name, permissions) VALUES (?,?,?)', [$slug, $name, json_encode(['dashboard.view'])]); flash('Role ban gaya. Ab permissions tick karein.'); log_activity('create_role', $name); }
    } else {
        foreach (q_all('SELECT * FROM roles') as $r) {
            if ($r['slug'] === 'admin') continue; // admin always has everything
            $chosen = array_values(array_intersect(array_keys($perms), (array)($_POST['perm'][$r['id']] ?? [])));
            q('UPDATE roles SET permissions = ? WHERE id = ?', [json_encode($chosen), $r['id']]);
        }
        log_activity('update_roles');
        flash('Permissions save ho gayi ✔');
    }
    redirect('/admin/?p=roles');
}

$roles = q_all('SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) n FROM roles r ORDER BY r.id');
admin_header('Roles & Permissions', 'roles');
?>
<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="tbl-wrap"><table class="tbl perm-tbl">
    <thead><tr><th>Permission</th><?php foreach ($roles as $r): ?><th class="center"><?= e($r['name']) ?><br><small class="muted"><?= $r['n'] ?> user</small></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($perms as $key => $label): ?>
      <tr><td><?= e($label) ?><br><code class="muted"><?= e($key) ?></code></td>
      <?php foreach ($roles as $r): $has = $r['slug'] === 'admin' || in_array($key, json_decode($r['permissions'], true) ?: [], true); ?>
        <td class="center"><input type="checkbox" name="perm[<?= $r['id'] ?>][]" value="<?= e($key) ?>"<?= $has ? ' checked' : '' ?><?= $r['slug'] === 'admin' ? ' disabled' : '' ?>></td>
      <?php endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="muted">Administrator ke paas hamesha saari permissions rehti hain.</p>
  <button class="btn primary">💾 Permissions save karein</button>
</form>
<div class="grid2">
  <form method="post" class="card"><?= csrf_field() ?><h2>Naya role</h2>
    <div class="form-row"><?= f_text('new_role', 'Role ka naam', '', ['placeholder' => 'jaise: SEO Manager']) ?><button class="btn primary">+ Banayein</button></div></form>
  <div class="card"><h2>Role delete</h2>
    <?php foreach ($roles as $r): if ($r['is_system']) continue; ?>
      <form method="post" class="inline" onsubmit="return confirm('Role delete karein?')"><?= csrf_field() ?><button class="btn danger sm" name="delete" value="<?= $r['id'] ?>">✕ <?= e($r['name']) ?></button></form>
    <?php endforeach; ?>
  </div>
</div>
<?php admin_footer();
