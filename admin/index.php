<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/lib.php';

if (!is_installed()) redirect('/install.php');
migrate();
header('X-Frame-Options: SAMEORIGIN');
header('X-Robots-Tag: noindex, nofollow');
start_session();

$p = preg_replace('/[^a-z_]/', '', (string)($_GET['p'] ?? 'dashboard'));

/* ---------- login ---------- */
if ($p === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    log_activity('logout');
    $_SESSION = [];
    session_destroy();
    setcookie('esy_admin', '', ['expires' => time() - 3600, 'path' => '/']);
    redirect('/admin/');
}

if (!current_user()) {
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $ip = client_ip();
        $fails = (int)q_val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND ts > ?', [$ip, date('Y-m-d H:i:s', time() - 900)]);
        if ($fails >= 5) {
            $err = 'Bahut zyada galat koshish. 15 minute baad try karein.';
        } else {
            $u = q_one("SELECT * FROM users WHERE email = ? AND status = 'active'", [strtolower(trim((string)($_POST['email'] ?? '')))]);
            if ($u && password_verify((string)($_POST['password'] ?? ''), $u['pass_hash'])) {
                session_regenerate_id(true);
                $_SESSION['uid'] = (int)$u['id'];
                q('UPDATE users SET last_login = ? WHERE id = ?', [now(), $u['id']]);
                q('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
                if (password_needs_rehash($u['pass_hash'], PASSWORD_DEFAULT)) q('UPDATE users SET pass_hash = ? WHERE id = ?', [password_hash($_POST['password'], PASSWORD_DEFAULT), $u['id']]);
                log_activity('login');
                redirect('/admin/');
            }
            q('INSERT INTO login_attempts (ip, ts) VALUES (?, ?)', [$ip, now()]);
            $err = 'Email ya password galat hai.';
        }
    }
    $S = settings();
    ?><!DOCTYPE html>
<html lang="hi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login · <?= e($S['site_name']) ?></title><meta name="robots" content="noindex,nofollow">
<link rel="icon" href="<?= e($S['favicon']) ?>"><link rel="stylesheet" href="/assets/admin.css"></head>
<body class="auth-page">
<form method="post" class="auth-box">
  <img src="<?= e($S['logo']) ?>" alt="" class="auth-logo">
  <h1>Admin Login</h1>
  <?php if (isset($_GET['installed'])): ?><div class="alert ok">CMS install ho gaya. Ab login karein.</div><?php endif; ?>
  <?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <label class="field"><span>Email</span><input type="email" name="email" required autofocus></label>
  <label class="field"><span>Password</span><input type="password" name="password" required></label>
  <button class="btn primary block">Login</button>
</form>
</body></html><?php
    exit;
}

mark_staff_browser((int)current_user()['id']);

/* ---------- router ---------- */
$routes = [
    'dashboard' => 'dashboard.view', 'analytics' => 'analytics.view', 'posts' => 'posts.edit', 'post_edit' => 'posts.edit',
    'pages' => 'pages.manage', 'categories' => 'categories.manage', 'media' => 'media.manage', 'menus' => 'menus.manage',
    'links' => 'links.manage', 'ads' => 'ads.manage', 'settings' => 'settings.manage', 'code' => 'code.manage', 'users' => 'users.manage',
    'roles' => 'roles.manage', 'activity' => 'users.manage', 'profile' => null, 'media_json' => null,
];
if (!array_key_exists($p, $routes)) $p = 'dashboard';
if ($p === 'dashboard' && !can('dashboard.view')) $p = 'profile';
if ($routes[$p]) require_perm($routes[$p]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_check();

require __DIR__ . '/pages/' . $p . '.php';
