<?php
require __DIR__ . '/app/bootstrap.php';

if (!is_installed()) redirect('/install.php');

$path = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$path = preg_replace('#^index\.php/?#', '', $path);
$S = settings();

// Old static URLs -> new clean URLs
if (preg_match('#^yojna/([a-z0-9-]+)\.html$#', $path, $m)) {
    header('Location: /yojna/' . $m[1], true, 301);
    exit;
}

// Click beacon from assets/app.js
if ($path === 't/click') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
        $url = (string)($in['u'] ?? '');
        if ($url !== '' && strlen($url) < 1000) log_click($url, (string)($in['t'] ?? ''), (string)($in['p'] ?? ''));
    }
    http_response_code(204);
    exit;
}

// Tracked short links: /go/{slug}
if (preg_match('#^go/([a-z0-9-]+)$#', $path, $m)) {
    $l = q_one('SELECT * FROM links WHERE slug = ?', [$m[1]]);
    if (!$l) { http_response_code(404); exit('Link not found'); }
    $page = (string)parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH);
    log_click($l['target'], $l['title'] ?: $l['slug'], $page, (int)$l['id']);
    header('Cache-Control: no-store');
    header('Location: ' . safe_url($l['target']), true, 302);
    exit;
}

if ($path === 'sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');
    $base = rtrim($S['site_url'], '/');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    echo '<url><loc>' . e($base . '/') . '</loc></url>' . "\n";
    foreach (categories() as $c) echo '<url><loc>' . e("$base/category/{$c['slug']}") . '</loc></url>' . "\n";
    foreach (q_all("SELECT type, slug, updated_at FROM posts WHERE status = 'published' ORDER BY type, sort") as $p) {
        $loc = $base . ($p['type'] === 'yojna' ? '/yojna/' : '/page/') . $p['slug'];
        echo '<url><loc>' . e($loc) . '</loc><lastmod>' . e(substr($p['updated_at'], 0, 10)) . '</lastmod></url>' . "\n";
    }
    echo '</urlset>';
    exit;
}
if ($path === 'robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /go/\nDisallow: /t/\n\nSitemap: " . rtrim($S['site_url'], '/') . "/sitemap.xml\n";
    exit;
}

$view = '404';
$data = [];
if ($path === '') {
    $view = 'home';
} elseif (preg_match('#^yojna/([a-z0-9-]+)$#', $path, $m) && ($y = find_post('yojna', $m[1]))) {
    $view = 'yojna';
    $data['y'] = $y;
} elseif (preg_match('#^page/([a-z0-9-]+)$#', $path, $m) && ($p = find_post('page', $m[1]))) {
    $view = 'page';
    $data['p'] = $p;
} elseif (preg_match('#^category/([a-z0-9-]+)$#', $path, $m) && ($c = category_by_slug($m[1]))) {
    $view = 'home';
    $data['cat'] = $c;
}
if ($view === '404') http_response_code(404);

log_visit('/' . $path);
extract($data);
require ROOT . '/app/views/' . $view . '.php';
