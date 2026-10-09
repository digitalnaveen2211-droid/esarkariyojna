<?php
// Admin layout, navigation and form helpers.
defined('ESY') or exit;
require_once ROOT . '/app/seo.php';

function admin_nav(): array {
    return [
        ['dashboard', '📊', 'Dashboard', 'dashboard.view'],
        ['analytics', '📈', 'Analytics', 'analytics.view'],
        ['posts', '📄', 'Yojnayein', 'posts.edit'],
        ['pages', '📃', 'Pages', 'pages.manage'],
        ['categories', '🏷️', 'Categories', 'categories.manage'],
        ['media', '🖼️', 'Media', 'media.manage'],
        ['menus', '🧭', 'Menus', 'menus.manage'],
        ['links', '🔗', 'Tracking Links', 'links.manage'],
        ['ads', '📢', 'Ads', 'ads.manage'],
        ['settings&tab=home', '🏠', 'Home page content', 'settings.manage'],
        ['settings', '🎨', 'Site Settings', 'settings.manage'],
        ['code', '🧩', 'Code & Tracking', 'code.manage'],
        ['users', '👥', 'Users', 'users.manage'],
        ['roles', '🔐', 'Roles', 'roles.manage'],
        ['activity', '🕘', 'Activity Log', 'users.manage'],
    ];
}

function flash(?string $msg = null, string $type = 'ok') {
    start_session();
    if ($msg !== null) { $_SESSION['flash'][] = [$type, $msg]; return null; }
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function require_perm(string $perm): void {
    if (!can($perm)) {
        http_response_code(403);
        admin_header('Access denied');
        echo '<div class="card"><h2>Access denied</h2><p>Aapke role ko is section ki permission nahi hai. Admin se contact karein.</p></div>';
        admin_footer();
        exit;
    }
}

function admin_header(string $title, string $active = '', string $actions = ''): void {
    $u = current_user();
    $S = settings();
    ?><!DOCTYPE html>
<html lang="hi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> · <?= e($S['site_name']) ?> Admin</title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="<?= e($S['favicon']) ?>">
<link rel="stylesheet" href="/assets/admin.css?v=<?= @filemtime(ROOT . '/assets/admin.css') ?>">
</head><body>
<div class="shell">
  <aside class="sidebar" id="sidebar">
    <a class="side-brand" href="/admin/"><img src="<?= e($S['logo']) ?>" alt=""><span>CMS</span></a>
    <nav>
      <?php foreach (admin_nav() as [$key, $ic, $label, $perm]): if (!can($perm)) continue; ?>
        <a href="/admin/?p=<?= $key ?>" class="<?= $active === $key ? 'on' : '' ?>"><span class="ni"><?= $ic ?></span><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="/" target="_blank">🌐 Website dekhein ↗</a>
    </div>
  </aside>
  <div class="main">
    <header class="topbar">
      <button class="menu-btn" type="button" onclick="document.getElementById('sidebar').classList.toggle('open')" aria-label="Menu">☰</button>
      <h1><?= e($title) ?></h1>
      <div class="top-actions"><?= $actions ?></div>
      <div class="me">
        <a href="/admin/?p=profile" title="Profile"><span class="avatar"><?= e(mb_strtoupper(mb_substr($u['name'] ?? '?', 0, 1))) ?></span><span class="me-name"><?= e($u['name'] ?? '') ?><small><?= e($u['role_name'] ?? '') ?></small></span></a>
        <form method="post" action="/admin/?p=logout"><?= csrf_field() ?><button class="btn ghost sm">Logout</button></form>
      </div>
    </header>
    <div class="content">
    <?php foreach (flash() as [$t, $m]): ?><div class="alert <?= $t === 'ok' ? 'ok' : 'err' ?>"><?= e($m) ?></div><?php endforeach;
}

function admin_footer(string $extra = ''): void {
    ?>
    </div>
  </div>
</div>
<script src="/assets/admin.js?v=<?= @filemtime(ROOT . '/assets/admin.js') ?>"></script>
<?= $extra ?>
</body></html><?php
}

/* ---------- form helpers ---------- */
function f_text(string $name, string $label, $value, array $o = []): string {
    $type = $o['type'] ?? 'text';
    $attrs = '';
    foreach (['placeholder', 'maxlength', 'pattern', 'min', 'max', 'step'] as $a) if (isset($o[$a])) $attrs .= " $a=\"" . e($o[$a]) . '"';
    if (!empty($o['required'])) $attrs .= ' required';
    $help = isset($o['help']) ? '<small class="help">' . $o['help'] . '</small>' : '';
    return '<label class="field"><span>' . e($label) . '</span><input type="' . $type . '" name="' . e($name) . '" value="' . e($value) . '"' . $attrs . '>' . $help . '</label>';
}
function f_area(string $name, string $label, $value, array $o = []): string {
    $rows = $o['rows'] ?? 3;
    $cls = $o['class'] ?? '';
    $help = isset($o['help']) ? '<small class="help">' . $o['help'] . '</small>' : '';
    return '<label class="field"><span>' . e($label) . '</span><textarea name="' . e($name) . '" rows="' . $rows . '" class="' . e($cls) . '">' . e($value) . '</textarea>' . $help . '</label>';
}
function f_editor(string $name, string $label, $value): string {
    return '<div class="field"><span>' . e($label) . '</span><div class="editor" data-editor="' . e($name) . '"></div><textarea name="' . e($name) . '" class="editor-src" hidden>' . e($value) . '</textarea></div>';
}
function f_select(string $name, string $label, $value, array $opts, array $o = []): string {
    $h = '<label class="field"><span>' . e($label) . '</span><select name="' . e($name) . '">';
    foreach ($opts as $k => $v) $h .= '<option value="' . e($k) . '"' . ((string)$k === (string)$value ? ' selected' : '') . '>' . e($v) . '</option>';
    $help = isset($o['help']) ? '<small class="help">' . $o['help'] . '</small>' : '';
    return $h . '</select>' . $help . '</label>';
}
function f_check(string $name, string $label, $checked): string {
    return '<label class="check"><input type="hidden" name="' . e($name) . '" value="0"><input type="checkbox" name="' . e($name) . '" value="1"' . ($checked ? ' checked' : '') . '> ' . e($label) . '</label>';
}
function f_image(string $name, string $label, $value): string {
    return '<div class="field img-field"><span>' . e($label) . '</span>
      <div class="img-row"><img src="' . e($value ?: 'data:image/gif;base64,R0lGODlhAQABAAAAACw=') . '" class="img-prev" alt="">
      <div class="img-ctrl"><input type="text" name="' . e($name) . '" value="' . e($value) . '" placeholder="/uploads/... ya https://...">
      <div class="img-btns"><label class="btn ghost sm">⬆ Upload<input type="file" name="' . e($name) . '__file" accept="image/*" hidden></label>
      <button type="button" class="btn ghost sm" data-pick="' . e($name) . '">🖼 Media se chunein</button></div></div></div></div>';
}
// Applies any "<field>__file" uploads from the current request onto $data.
function apply_uploads(array $data, array $fields): array {
    foreach ($fields as $f) {
        if (!empty($_FILES[$f . '__file']['name'])) {
            [$url, $err] = handle_upload($_FILES[$f . '__file']);
            if ($err) flash("$f: $err", 'err'); else $data[$f] = $url;
        }
    }
    return $data;
}

function paginate(int $total, int $per, int $page, array $query): string {
    $pages = (int)ceil($total / $per);
    if ($pages <= 1) return '';
    $h = '<nav class="pager">';
    for ($i = max(1, $page - 4); $i <= min($pages, $page + 4); $i++) {
        $h .= '<a class="' . ($i === $page ? 'on' : '') . '" href="?' . e(http_build_query($query + ['pg' => $i])) . '">' . $i . '</a>';
    }
    return $h . '<span class="muted">' . $total . ' total</span></nav>';
}

function time_ago(string $ts): string {
    $d = time() - strtotime($ts);
    if ($d < 60) return 'abhi';
    if ($d < 3600) return floor($d / 60) . ' min pehle';
    if ($d < 86400) return floor($d / 3600) . ' ghante pehle';
    return date('d M Y, h:i A', strtotime($ts));
}
