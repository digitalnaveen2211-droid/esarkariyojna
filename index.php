<?php
require __DIR__ . '/app/bootstrap.php';

if (!is_installed()) redirect('/install.php');
migrate();

$path = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$path = preg_replace('#^index\.php/?#', '', $path);
$S = settings();
$qs = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
$qs = $qs !== '' ? '?' . $qs : '';

// Old static URLs -> new clean URLs
if (preg_match('#^yojna/([a-z0-9-]+)\.html$#', $path, $m)) {
    header('Location: /' . default_lang() . '/yojna/' . $m[1], true, 301);
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

// Ad view / click beacon from assets/app.js
if ($path === 't/ad') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
        log_ad_event((int)($in['a'] ?? 0), (string)($in['e'] ?? ''), (string)($in['s'] ?? ''), (string)($in['p'] ?? ''));
    }
    http_response_code(204);
    exit;
}

// Image ad click: count it, then send the visitor to the advertiser
if (preg_match('#^ad/(\d+)$#', $path, $m)) {
    $ad = q_one('SELECT id, link FROM ads WHERE id = ?', [(int)$m[1]]);
    if (!$ad || $ad['link'] === '') { http_response_code(404); exit('Ad not found'); }
    log_ad_event((int)$ad['id'], 'click', (string)($_GET['s'] ?? ''), (string)parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH));
    header('Cache-Control: no-store');
    header('Location: ' . safe_url($ad['link']), true, 302);
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
    // Each URL lists its Hindi + English versions (hreflang) so Google shows the right language.
    $entry = function (string $p, bool $hasEn, string $lastmod = '') use ($base) {
        $alts = '<xhtml:link rel="alternate" hreflang="hi" href="' . e($base . lurl($p, 'hi')) . '"/>';
        if ($hasEn) $alts .= '<xhtml:link rel="alternate" hreflang="en" href="' . e($base . lurl($p, 'en')) . '"/>';
        $alts .= '<xhtml:link rel="alternate" hreflang="x-default" href="' . e($base . lurl($p, 'hi')) . '"/>';
        $mod = $lastmod ? '<lastmod>' . e($lastmod) . '</lastmod>' : '';
        $out = '<url><loc>' . e($base . lurl($p, 'hi')) . '</loc>' . $mod . $alts . "</url>\n";
        if ($hasEn) $out .= '<url><loc>' . e($base . lurl($p, 'en')) . '</loc>' . $mod . $alts . "</url>\n";
        return $out;
    };
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
    echo $entry('/', true);
    foreach (categories() as $c) echo $entry("/category/{$c['slug']}", true);
    foreach (q_all("SELECT type, slug, title_en, content_hi, content_en, updated_at FROM posts WHERE status = 'published' ORDER BY type, sort") as $p) {
        echo $entry(($p['type'] === 'yojna' ? '/yojna/' : '/page/') . $p['slug'], has_english($p), substr($p['updated_at'], 0, 10));
    }
    echo '</urlset>';
    exit;
}
if ($path === 'robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /go/\nDisallow: /t/\nDisallow: /ad/\n\nSitemap: " . rtrim($S['site_url'], '/') . "/sitemap.xml\n";
    exit;
}

// Language comes from the URL: /hi/... (Hindi) or /en/... (English)
if (preg_match('#^(hi|en)(?:/(.*))?$#', $path, $m)) {
    define('LANG', $m[1]);
    $rest = $m[2] ?? '';
} else {
    // Old URLs without a language (/, /yojna/x, /page/x, /category/x) -> 301 to the default language
    if ($path === '' || preg_match('#^(yojna|page|category)/[a-z0-9-]+$#', $path)) {
        header('Location: ' . lurl('/' . $path, default_lang()) . $qs, true, 301);
        exit;
    }
    define('LANG', default_lang());
    $rest = null;
}

$view = '404';
$data = [];
if ($rest === '') {
    $view = 'home';
} elseif ($rest !== null && preg_match('#^yojna/([a-z0-9-]+)$#', $rest, $m) && ($y = find_post('yojna', $m[1]))) {
    $view = 'yojna';
    $data['y'] = $y;
} elseif ($rest !== null && preg_match('#^page/([a-z0-9-]+)$#', $rest, $m) && ($p = find_post('page', $m[1]))) {
    $view = 'page';
    $data['p'] = $p;
} elseif ($rest !== null && preg_match('#^category/([a-z0-9-]+)$#', $rest, $m) && ($c = category_by_slug($m[1]))) {
    $view = 'home';
    $data['cat'] = $c;
}
if ($view === '404') http_response_code(404);

log_visit('/' . $path);
extract($data);
require ROOT . '/app/views/' . $view . '.php';
