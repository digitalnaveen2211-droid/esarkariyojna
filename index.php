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
    echo $entry('/yojnayein', true);
    echo $entry('/blog', true);
    // Include only category pages with published content. Avoid indexing empty archives.
    $publishedBlogIds = array_fill_keys(array_map('intval', array_column(
        q_all("SELECT DISTINCT blog_cat_id FROM posts WHERE type='blog' AND status='published' AND blog_cat_id IS NOT NULL"), 'blog_cat_id'
    )), true);
    foreach (blog_categories() as $c) {
        $ids = blog_cat_ids_with_children((int)$c['id']);
        if (!array_intersect($ids, array_keys($publishedBlogIds))) continue;
        echo $entry("/blog/category/{$c['slug']}", trim((string)($c['name_en'] ?? '')) !== '');
    }
    $publishedSchemeIds = array_fill_keys(array_map('intval', array_column(
        q_all("SELECT DISTINCT category_id FROM posts WHERE type='yojna' AND status='published' AND category_id IS NOT NULL"), 'category_id'
    )), true);
    foreach (categories() as $c) {
        if (empty($publishedSchemeIds[(int)$c['id']])) continue;
        echo $entry("/category/{$c['slug']}", trim((string)($c['name_en'] ?? '')) !== '');
    }
    foreach (q_all("SELECT type, slug, title_en, content_hi, content_en, updated_at FROM posts WHERE status = 'published' ORDER BY type, sort") as $p) {
        echo $entry(post_path($p['type'], $p['slug']), has_english($p), substr($p['updated_at'], 0, 10));
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
    if ($path === '' || $path === 'yojnayein' || $path === 'blog' || preg_match('#^(yojna|page|category|blog|blog/category)/[a-z0-9-]+$#', $path)) {
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
} elseif ($rest === 'yojnayein') {
    $view = 'home';
    $data['allPage'] = true;
} elseif ($rest === 'blog') {
    $view = 'blog_list';
} elseif ($rest !== null && preg_match('#^blog/category/([a-z0-9-]+)$#', $rest, $m) && ($bc = blog_cat_by_slug($m[1]))) {
    $view = 'blog_list';
    $data['bcat'] = $bc;
} elseif ($rest !== null && preg_match('#^blog/([a-z0-9-]+)$#', $rest, $m) && ($b = find_post('blog', $m[1]))) {
    $view = 'blog_post';
    $data['b'] = $b;
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

// Only real, resolved public pages count as page views. Ignore scanners probing 404 routes.
if ($view !== '404') log_visit('/' . $path);
extract($data);
require ROOT . '/app/views/' . $view . '.php';
