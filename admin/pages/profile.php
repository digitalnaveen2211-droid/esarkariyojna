<?php
defined('ESY') or exit;
$me = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $cur = (string)($_POST['current'] ?? '');
    $new = (string)($_POST['new'] ?? '');
    if ($name !== '') q('UPDATE users SET name = ? WHERE id = ?', [$name, $me['id']]);
    if ($new !== '') {
        if (!password_verify($cur, $me['pass_hash'])) { flash('Purana password galat hai.', 'err'); redirect('/admin/?p=profile'); }
        if (strlen($new) < 8) { flash('Naya password kam se kam 8 characters ka ho.', 'err'); redirect('/admin/?p=profile'); }
        q('UPDATE users SET pass_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
        log_activity('change_password');
    }
    flash('Profile save ho gaya ✔');
    redirect('/admin/?p=profile');
}
admin_header('Mera Profile', 'profile');
?>
<form method="post" class="card narrow-form">
  <?= csrf_field() ?>
  <?= f_text('name', 'Naam', $me['name']) ?>
  <p class="muted">Email: <b><?= e($me['email']) ?></b> · Role: <b><?= e($me['role_name']) ?></b></p>
  <h3>Password badlein</h3>
  <?= f_text('current', 'Purana password', '', ['type' => 'password']) ?>
  <?= f_text('new', 'Naya password (8+ characters)', '', ['type' => 'password']) ?>
  <button class="btn primary">Save</button>
</form>
<?php admin_footer();
