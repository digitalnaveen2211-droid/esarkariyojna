<?php
// e-Sarkari Yojna CMS core: config, database, settings, auth, permissions and helpers.
define('ESY', 1);
define('ROOT', dirname(__DIR__));
define('UPLOAD_DIR', ROOT . '/uploads');
define('UPLOAD_URL', '/uploads');
define('ESY_VERSION', '1.0.0');

date_default_timezone_set('Asia/Kolkata');

/* ---------- config ---------- */
// Config is a PHP file returning an array. It lives outside the web root when possible,
// so deploys (rsync) never overwrite it and it can never be downloaded.
function config_candidates(): array {
    return [dirname(ROOT) . '/esy-config.php', ROOT . '/app/config.php'];
}
function config(): ?array {
    static $c = false;
    if ($c === false) {
        $c = null;
        foreach (config_candidates() as $f) {
            if (is_file($f)) { $c = require $f; break; }
        }
    }
    return $c;
}
function is_installed(): bool { return config() !== null; }

/* ---------- database ---------- */
function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $c = config();
    if (!$c) throw new RuntimeException('CMS is not installed.');
    $dsn = 'mysql:host=' . $c['db_host'] . ';port=' . ($c['db_port'] ?? 3306) . ';dbname=' . $c['db_name'] . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $c['db_user'], $c['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+05:30'");
    return $pdo;
}
function q(string $sql, array $params = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function q_all(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function q_one(string $sql, array $params = []): ?array { $r = q($sql, $params)->fetch(); return $r ?: null; }
function q_val(string $sql, array $params = []) { $r = q($sql, $params)->fetchColumn(); return $r === false ? null : $r; }

/* ---------- settings ---------- */
function default_settings(): array {
    return [
        'site_name' => 'e-Sarkari Yojna',
        'tagline_hi' => 'आपकी योजना, आपकी पहचान',
        'tagline_en' => 'Apki Yojna, Apki Pahchan',
        'logo' => '/assets/logo.png',
        'favicon' => '/assets/favicon.png',
        'color_primary' => '#4338ca',
        'color_accent' => '#f97316',
        'meta_title' => 'e-Sarkari Yojna | Apki Yojna, Apki Pahchan',
        'meta_desc' => 'e-Sarkari Yojna par sarkari yojnaon ki saral jaankari: patrata, laabh, dastavez aur aavedan ke steps. Ye ek private, informational portal hai, sarkari website nahi.',
        'site_url' => 'https://esarkariyojna.com',
        'default_lang' => 'hi',
        'notice_on' => '1',
        'notice_hi' => '⚠️ यह एक सरकारी वेबसाइट नहीं है। यह एक निजी, सूचनात्मक पोर्टल है।',
        'notice_en' => '⚠️ This is NOT a government website. It is a private, informational portal.',
        'declaration_hi' => '<p><b>यह सरकारी वेबसाइट नहीं है।</b> e-Sarkari Yojna एक निजी, सूचनात्मक पोर्टल है जो सरकारी योजनाओं की जानकारी सरल भाषा में देने के लिए बनाया गया है।</p><ul><li>यहां दी गई जानकारी किसी सरकारी विभाग की आधिकारिक घोषणा नहीं है।</li><li>यह पोर्टल किसी भी सरकारी विभाग, मंत्रालय या सरकारी संस्था से संबंधित नहीं है।</li><li>आवेदन, भुगतान या किसी भी सरकारी काम के लिए इस पोर्टल पर कोई फॉर्म न भरें।</li><li>अंतिम पात्रता, दस्तावेज़ और शुल्क के लिए हमेशा आधिकारिक वेबसाइट देखें।</li></ul>',
        'declaration_en' => '<p><b>This is NOT a government website.</b> e-Sarkari Yojna is a private, informational portal created to explain government schemes in simple language.</p><ul><li>The information here is not an official announcement of any government department.</li><li>This portal is not affiliated with any government ministry, department or agency.</li><li>Do not fill any application, payment or government form through this portal.</li><li>For final eligibility, documents and fees, always check the official website.</li></ul>',
        'hero_title_hi' => 'सरकारी योजनाओं की जानकारी, अब और भी आसान',
        'hero_title_en' => 'Government Schemes Information, Made Easier',
        'hero_sub_hi' => 'अपनी ज़रूरत के हिसाब से योजना खोजें और अपना बेहतर कल बनाएं।',
        'hero_sub_en' => 'Find a scheme that suits your needs and build a better future.',
        'hero_image' => '',
        'show_stats' => '1',
        'footer_about_hi' => 'सरकारी योजनाओं की सरल और भरोसेमंद जानकारी, हिंदी और अंग्रेज़ी में।',
        'footer_about_en' => 'Simple, reliable information on government schemes in Hindi and English.',
        'footer_disc_hi' => 'जानकारी सामान्य है। आवेदन से पहले आधिकारिक वेबसाइट पर पात्रता और नवीनतम नियम ज़रूर जांच लें।',
        'footer_disc_en' => 'Information is general. Always check eligibility and latest rules on the official website before applying.',
        'copyright' => '© {year} e-Sarkari Yojna. All rights reserved.',
        'contact_email' => '',
        'social_facebook' => '', 'social_youtube' => '', 'social_instagram' => '',
        'social_x' => '', 'social_whatsapp' => '', 'social_telegram' => '',
        // Tracking & code injection
        'ga4_id' => '',
        'gtm_id' => '',
        'head_code' => '',
        'body_code' => '',
        'footer_code' => '',
        'track_on' => '1',
        'track_anonymize_ip' => '0',
        'track_retention_days' => '365',
        'geo_on' => '1',
    ];
}
function settings(bool $fresh = false): array {
    static $s;
    if ($s === null || $fresh) {
        $s = default_settings();
        try {
            foreach (q_all('SELECT k, v FROM settings') as $r) $s[$r['k']] = $r['v'];
        } catch (Throwable $e) { /* not installed yet */ }
    }
    return $s;
}
function save_settings(array $vals): void {
    $st = db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
    foreach ($vals as $k => $v) $st->execute([$k, (string)$v]);
    settings(true);
}

/* ---------- content ---------- */
function categories(): array {
    static $c;
    if ($c === null) $c = q_all('SELECT * FROM categories ORDER BY sort, id');
    return $c;
}
function categories_by_id(): array { return array_column(categories(), null, 'id'); }
function category_by_slug(string $slug): ?array {
    foreach (categories() as $c) if ($c['slug'] === $slug) return $c;
    return null;
}
function schemes(?int $categoryId = null): array {
    $sql = "SELECT * FROM posts WHERE type='yojna' AND status='published'";
    $p = [];
    if ($categoryId) { $sql .= ' AND category_id = ?'; $p[] = $categoryId; }
    return q_all($sql . ' ORDER BY sort, id', $p);
}
function find_post(string $type, string $slug): ?array {
    return q_one("SELECT * FROM posts WHERE type = ? AND slug = ? AND status = 'published'", [$type, $slug]);
}
function menu(string $location): array {
    return q_all('SELECT * FROM menus WHERE location = ? ORDER BY sort, id', [$location]);
}

/* ---------- helpers ---------- */
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function slugify(string $s): string {
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? substr($s, 0, 150) : 'item-' . bin2hex(random_bytes(3));
}
function valid_color(string $c, string $fallback): string {
    return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : $fallback;
}
function safe_url(string $u): string {
    $u = trim($u);
    if ($u === '' || preg_match('#^(https?://|/|\#|mailto:|tel:)#i', $u)) return $u;
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $u)) return '#'; // block javascript:, data: etc.
    return 'https://' . $u;
}
// Bilingual text: renders both languages; CSS shows one based on <html data-lang>.
function bi(string $hi, string $en = '', string $tag = 'span'): string {
    if ($en === '' || $en === $hi) return e($hi);
    return '<' . $tag . ' class="l-hi">' . e($hi) . '</' . $tag . '><' . $tag . ' class="l-en">' . e($en) . '</' . $tag . '>';
}
function bi_html(string $hi, string $en = ''): string {
    if (trim(strip_tags($en)) === '') return '<div>' . $hi . '</div>';
    return '<div class="l-hi">' . $hi . '</div><div class="l-en">' . $en . '</div>';
}
function client_ip(): string {
    // Trust proxy headers only when the request really comes from a local proxy (CloudPanel/Varnish/Cloudflare tunnel).
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (in_array($ip, ['127.0.0.1', '::1'], true)) {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $h) {
            if (!empty($_SERVER[$h])) {
                $cand = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($cand, FILTER_VALIDATE_IP)) return $cand;
            }
        }
    }
    return $ip;
}
function redirect(string $url): void { header('Location: ' . $url); exit; }
function now(): string { return date('Y-m-d H:i:s'); }

/* ---------- sessions, CSRF ---------- */
function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('esy_admin');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $secure]);
    session_start();
}
function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    if (!hash_equals(csrf_token(), (string)($_POST['_csrf'] ?? ''))) {
        http_response_code(400);
        exit('Session expired. Please go back, refresh the page and try again.');
    }
}

/* ---------- permissions ---------- */
function all_permissions(): array {
    return [
        'dashboard.view'   => 'Dashboard dekhna',
        'analytics.view'   => 'Analytics / tracking dekhna',
        'posts.edit'       => 'Yojna likhna aur edit karna',
        'posts.publish'    => 'Yojna publish / delete karna',
        'pages.manage'     => 'Pages (About, Privacy...) manage karna',
        'categories.manage'=> 'Categories manage karna',
        'media.manage'     => 'Images upload / delete karna',
        'menus.manage'     => 'Header / footer menu manage karna',
        'links.manage'     => 'Tracking links manage karna',
        'settings.manage'  => 'Site settings (logo, header, footer, colors)',
        'code.manage'      => 'Header/footer code, GA4, GTM (sirf trusted admin)',
        'users.manage'     => 'Users add / edit karna',
        'roles.manage'     => 'Roles aur permissions badalna',
    ];
}
function default_roles(): array {
    $all = array_keys(all_permissions());
    return [
        ['slug' => 'admin', 'name' => 'Administrator', 'permissions' => $all, 'is_system' => 1],
        ['slug' => 'editor', 'name' => 'Editor', 'permissions' => ['dashboard.view', 'analytics.view', 'posts.edit', 'posts.publish', 'pages.manage', 'categories.manage', 'media.manage', 'menus.manage', 'links.manage'], 'is_system' => 0],
        ['slug' => 'author', 'name' => 'Author', 'permissions' => ['dashboard.view', 'posts.edit', 'media.manage'], 'is_system' => 0],
        ['slug' => 'analyst', 'name' => 'Analyst', 'permissions' => ['dashboard.view', 'analytics.view'], 'is_system' => 0],
    ];
}

/* ---------- auth ---------- */
function current_user(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    start_session();
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $u = q_one("SELECT u.*, r.slug AS role_slug, r.name AS role_name, r.permissions FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.status = 'active'", [$_SESSION['uid']]);
        if ($u) $u['perms'] = json_decode($u['permissions'], true) ?: [];
    }
    return $u;
}
function can(string $perm): bool {
    $u = current_user();
    if (!$u) return false;
    if ($u['role_slug'] === 'admin') return true;
    return in_array($perm, $u['perms'], true);
}
function log_activity(string $action, string $details = ''): void {
    try {
        q('INSERT INTO activity_log (user_id, action, details, ip) VALUES (?, ?, ?, ?)', [current_user()['id'] ?? null, $action, mb_substr($details, 0, 500), client_ip()]);
    } catch (Throwable $e) {}
}

/* ---------- uploads ---------- */
function handle_upload(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return [null, 'Upload failed (code ' . ($file['error'] ?? '?') . ').'];
    if ($file['size'] > 5 * 1024 * 1024) return [null, 'File 5 MB se badi hai.'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg' => 1, 'jpeg' => 1, 'png' => 1, 'gif' => 1, 'webp' => 1, 'ico' => 1];
    if (!isset($allowed[$ext])) return [null, 'Sirf JPG, PNG, GIF, WEBP ya ICO image allowed hai.'];
    if ($ext !== 'ico' && @getimagesize($file['tmp_name']) === false) return [null, 'Ye valid image nahi hai.'];
    $sub = date('Y/m');
    $dir = UPLOAD_DIR . '/' . $sub;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return [null, 'uploads folder me likhne ki permission nahi hai.'];
    $base = slugify(pathinfo($file['name'], PATHINFO_FILENAME));
    $name = substr($base, 0, 40) . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) return [null, 'File save nahi ho payi.'];
    $url = UPLOAD_URL . "/$sub/$name";
    q('INSERT INTO media (path, name, size, uploaded_by) VALUES (?, ?, ?, ?)', [$url, mb_substr($file['name'], 0, 255), (int)$file['size'], current_user()['id'] ?? null]);
    return [$url, null];
}

require __DIR__ . '/tracking.php';
