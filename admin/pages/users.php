<?php
defined('ESY') or exit;

$me = current_user();
$isAdmin = $me['role_slug'] === 'admin';
$roles = q_all('SELECT * FROM roles ORDER BY id');
$adminRoleId = (int)q_val("SELECT id FROM roles WHERE slug = 'admin'");
$activeAdmins = fn(int $exclude) => (int)q_val("SELECT COUNT(*) FROM users WHERE role_id = ? AND status = 'active' AND id <> ?", [$adminRoleId, $exclude]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $target = $id ? q_one('SELECT * FROM users WHERE id = ?', [$id]) : null;
    if ($target && (int)$target['role_id'] === $adminRoleId && !$isAdmin) { flash('Admin user ko sirf Admin badal sakta hai.', 'err'); redirect('/admin/?p=users'); }

    if (isset($_POST['delete'])) {
        if ($id === (int)$me['id']) flash('Aap khud ko delete nahi kar sakte.', 'err');
        elseif ($target && (int)$target['role_id'] === $adminRoleId && $activeAdmins($id) === 0) flash('Kam se kam ek admin hona zaroori hai.', 'err');
        else {
            q('UPDATE posts SET author_id = NULL WHERE author_id = ?', [$id]);
            q('DELETE FROM users WHERE id = ?', [$id]);
            log_activity('delete_user', $target['email'] ?? '');
            flash('User delete ho gaya.');
        }
        redirect('/admin/?p=users');
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $roleId = (int)($_POST['role_id'] ?? 0);
    $status = ($_POST['status'] ?? 'active') === 'disabled' ? 'disabled' : 'active';
    $pass = (string)($_POST['password'] ?? '');
    $err = '';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Naam aur sahi email zaroori hai.';
    elseif (!in_array($roleId, array_map('intval', array_column($roles, 'id')), true)) $err = 'Role chunein.';
    elseif ($roleId === $adminRoleId && !$isAdmin) $err = 'Admin role sirf Admin de sakta hai.';
    elseif (q_val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $id])) $err = 'Ye email pehle se kisi user ka hai.';
    elseif (!$id && strlen($pass) < 8) $err = 'Password kam se kam 8 characters ka ho.';
    elseif ($pass !== '' && strlen($pass) < 8) $err = 'Password kam se kam 8 characters ka ho.';
    elseif ($id === (int)$me['id'] && ($status === 'disabled' || $roleId !== (int)$me['role_id'])) $err = 'Aap apna khud ka role ya status nahi badal sakte.';
    elseif ($target && (int)$target['role_id'] === $adminRoleId && ($roleId !== $adminRoleId || $status === 'disabled') && $activeAdmins($id) === 0) $err = 'Kam se kam ek active admin hona zaroori hai.';
    if ($err) { flash($err, 'err'); redirect('/admin/?p=users' . ($id ? '&edit=' . $id : '&new=1')); }

    if ($id) {
        q('UPDATE users SET name=?, email=?, role_id=?, status=? WHERE id=?', [$name, $email, $roleId, $status, $id]);
        if ($pass !== '') q('UPDATE users SET pass_hash=? WHERE id=?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
        log_activity('update_user', $email);
    } else {
        q('INSERT INTO users (name, email, pass_hash, role_id, status) VALUES (?,?,?,?,?)', [$name, $email, password_hash($pass, PASSWORD_DEFAULT), $roleId, $status]);
        log_activity('create_user', $email);
    }
    flash('User save ho gaya ✔');
    redirect('/admin/?p=users');
}

$edit = isset($_GET['edit']) ? q_one('SELECT * FROM users WHERE id = ?', [(int)$_GET['edit']]) : (isset($_GET['new']) ? ['id' => 0, 'name' => '', 'email' => '', 'role_id' => 0, 'status' => 'active'] : null);
$rows = q_all('SELECT u.*, r.name role_name, r.slug role_slug FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.id');
$roleOpts = [];
foreach ($roles as $r) if ($r['slug'] !== 'admin' || $isAdmin) $roleOpts[$r['id']] = $r['name'];

admin_header('Users', 'users', '<a class="btn primary" href="/admin/?p=users&new=1">+ Naya User</a>');
if ($edit): ?>
<form method="post" class="card narrow-form">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
  <h2><?= $edit['id'] ? 'User edit' : 'Naya user' ?></h2>
  <?= f_text('name', 'Naam', $edit['name'], ['required' => true]) ?>
  <?= f_text('email', 'Email (login ke liye)', $edit['email'], ['type' => 'email', 'required' => true]) ?>
  <?= f_select('role_id', 'Role', $edit['role_id'], $roleOpts, ['help' => 'Har role kya kar sakta hai, ye <a href="/admin/?p=roles">Roles</a> me dekhein/badlein.']) ?>
  <?= f_select('status', 'Status', $edit['status'], ['active' => 'Active', 'disabled' => 'Disabled (login band)']) ?>
  <?= f_text('password', $edit['id'] ? 'Naya password (khali chhodenge to purana rahega)' : 'Password (8+ characters)', '', ['type' => 'password']) ?>
  <div class="row-actions"><a class="btn ghost" href="/admin/?p=users">Cancel</a><button class="btn primary">Save</button></div>
</form>
<?php endif; ?>
<div class="card"><div class="tbl-wrap"><table class="tbl">
  <thead><tr><th>Naam</th><th>Email</th><th>Role</th><th>Status</th><th>Aakhri login</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><b><?= e($r['name']) ?></b><?= (int)$r['id'] === (int)$me['id'] ? ' <span class="badge">aap</span>' : '' ?></td>
      <td><?= e($r['email']) ?></td>
      <td><span class="badge"><?= e($r['role_name']) ?></span></td>
      <td><span class="badge <?= $r['status'] === 'active' ? 'green' : 'gray' ?>"><?= e($r['status']) ?></span></td>
      <td><small><?= $r['last_login'] ? e(time_ago($r['last_login'])) : 'kabhi nahi' ?></small></td>
      <td class="actions">
        <?php if ($r['role_slug'] !== 'admin' || $isAdmin): ?>
          <a class="btn ghost sm" href="/admin/?p=users&edit=<?= $r['id'] ?>">Edit</a>
          <?php if ((int)$r['id'] !== (int)$me['id']): ?><form method="post" onsubmit="return confirm('User delete karein?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn danger sm" name="delete" value="1">✕</button></form><?php endif; ?>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
<?php admin_footer();
