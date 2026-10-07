<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/install_lib.php';

if (is_installed()) { http_response_code(403); exit('CMS pehle se install hai. <a href="/admin/">Admin login</a>'); }

$err = '';
$v = ['db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'db_pass' => '',
      'admin_name' => '', 'admin_email' => '', 'admin_pass' => '',
      'site_url' => (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'esarkariyojna.com')];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) $v[$k] = trim((string)($_POST[$k] ?? ''));
    // Only a database on this same server is allowed, so nobody else can take over a fresh install with their own DB.
    if (!in_array(strtolower($v['db_host']), ['localhost', '127.0.0.1', '::1'], true)) $err = 'Database host "localhost" ya "127.0.0.1" hi hona chahiye.';
    elseif ($v['db_name'] === '' || $v['db_user'] === '') $err = 'Database name aur user zaroori hai.';
    elseif (!filter_var($v['admin_email'], FILTER_VALIDATE_EMAIL)) $err = 'Admin email sahi nahi hai.';
    elseif (strlen($v['admin_pass']) < 8) $err = 'Admin password kam se kam 8 characters ka ho.';
    else {
        [$ok, $msg] = install_cms($v);
        if ($ok) { redirect('/admin/?installed=1'); }
        $err = $msg;
    }
}
?><!DOCTYPE html>
<html lang="hi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Install e-Sarkari Yojna CMS</title><meta name="robots" content="noindex">
<link rel="stylesheet" href="/assets/admin.css"></head>
<body class="auth-page">
<form method="post" class="auth-box wide" autocomplete="off">
  <img src="/assets/logo.png" alt="" class="auth-logo">
  <h1>CMS Install</h1>
  <p class="muted">CloudPanel me <b>Databases → Add Database</b> se ek MySQL database banaiye, phir uski details yahan daaliye.</p>
  <?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>
  <fieldset><legend>Database</legend>
    <div class="row2"><label>Host<input name="db_host" value="<?= e($v['db_host']) ?>" required></label>
    <label>Port<input name="db_port" value="<?= e($v['db_port']) ?>" required></label></div>
    <label>Database name<input name="db_name" value="<?= e($v['db_name']) ?>" required></label>
    <div class="row2"><label>DB user<input name="db_user" value="<?= e($v['db_user']) ?>" required></label>
    <label>DB password<input type="password" name="db_pass" value="<?= e($v['db_pass']) ?>"></label></div>
  </fieldset>
  <fieldset><legend>Admin account</legend>
    <label>Aapka naam<input name="admin_name" value="<?= e($v['admin_name']) ?>" required></label>
    <label>Email (login ke liye)<input type="email" name="admin_email" value="<?= e($v['admin_email']) ?>" required></label>
    <label>Password (8+ characters)<input type="password" name="admin_pass" minlength="8" required></label>
    <label>Website URL<input name="site_url" value="<?= e($v['site_url']) ?>" required></label>
  </fieldset>
  <button class="btn primary block">Install karein</button>
</form>
</body></html>
