<?php
// Creates the tables, default roles, the first admin user and imports the starter content.
defined('ESY') or exit;

function install_cms(array $in): array {
    $dsn = 'mysql:host=' . $in['db_host'] . ';port=' . (int)$in['db_port'] . ';dbname=' . $in['db_name'] . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, $in['db_user'], $in['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        return [false, 'Database se connect nahi ho paya: ' . $e->getMessage()];
    }
    foreach (array_filter(array_map('trim', explode(';', file_get_contents(ROOT . '/app/schema.sql')))) as $sql) $pdo->exec($sql);

    $cfg = [
        'db_host' => $in['db_host'], 'db_port' => (int)$in['db_port'], 'db_name' => $in['db_name'],
        'db_user' => $in['db_user'], 'db_pass' => $in['db_pass'],
    ];
    $php = "<?php\n// e-Sarkari Yojna CMS config. Keep this file private.\nreturn " . var_export($cfg, true) . ";\n";
    $written = null;
    foreach (config_candidates() as $f) {
        if (@file_put_contents($f, $php, LOCK_EX) !== false) { @chmod($f, 0600); $written = $f; break; }
    }
    if (!$written) return [false, 'Config file likhne ki permission nahi hai. ' . dirname(ROOT) . ' folder writable hona chahiye.'];

    $has = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($has === 0) {
        $roleIns = $pdo->prepare('INSERT IGNORE INTO roles (slug, name, permissions, is_system) VALUES (?,?,?,?)');
        foreach (default_roles() as $r) $roleIns->execute([$r['slug'], $r['name'], json_encode($r['permissions']), $r['is_system']]);
        $adminRole = $pdo->query("SELECT id FROM roles WHERE slug = 'admin'")->fetchColumn();
        $pdo->prepare('INSERT INTO users (name, email, pass_hash, role_id) VALUES (?,?,?,?)')
            ->execute([$in['admin_name'], strtolower($in['admin_email']), password_hash($in['admin_pass'], PASSWORD_DEFAULT), $adminRole]);
        $pdo->prepare("INSERT INTO settings (k, v) VALUES ('site_url', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)")->execute([rtrim($in['site_url'], '/')]);
        import_seed($pdo);
    }
    return [true, $written];
}

function import_seed(PDO $pdo): void {
    $seed = fn($n) => json_decode((string)@file_get_contents(ROOT . "/app/seed/$n.json"), true) ?: [];
    if ((int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() === 0) {
        $st = $pdo->prepare('INSERT INTO categories (slug, name_hi, name_en, icon, color, sort) VALUES (?,?,?,?,?,?)');
        foreach ($seed('categories') as $i => $c) $st->execute([$c['id'], $c['name_hi'], $c['name_en'], $c['icon'], $c['color'], $i]);
    }
    $catIds = $pdo->query('SELECT slug, id FROM categories')->fetchAll(PDO::FETCH_KEY_PAIR);
    if ((int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn() === 0) {
        $st = $pdo->prepare('INSERT INTO posts (type, slug, category_id, icon, title_hi, title_en, summary_hi, summary_en, elig_hi, elig_en, keywords, content_hi, content_en, official_url, image, status, featured, seo_title, seo_desc, sort)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($seed('schemes') as $i => $s) {
            $st->execute(['yojna', $s['slug'], $catIds[$s['category']] ?? null, $s['icon'] ?? '', $s['title_hi'], $s['title_en'] ?? '', $s['summary_hi'] ?? '', $s['summary_en'] ?? '',
                $s['elig_hi'] ?? '', $s['elig_en'] ?? '', $s['keywords'] ?? '', $s['content_hi'] ?? '', $s['content_en'] ?? '', $s['official_url'] ?? '', $s['image'] ?? '',
                $s['status'] === 'published' ? 'published' : 'draft', !empty($s['featured']) ? 1 : 0, $s['seo_title'] ?? '', $s['seo_desc'] ?? '', $s['order'] ?? $i]);
        }
        foreach ($seed('pages') as $i => $p) {
            $st->execute(['page', $p['slug'], null, '', $p['title_hi'], $p['title_en'] ?? '', '', '', '', '', '', $p['content_hi'] ?? '', $p['content_en'] ?? '', '', '',
                'published', 0, '', $p['seo_desc'] ?? '', $i]);
        }
    }
    if ((int)$pdo->query('SELECT COUNT(*) FROM menus')->fetchColumn() === 0) {
        $st = $pdo->prepare('INSERT INTO menus (location, label_hi, label_en, url, sort) VALUES (?,?,?,?,?)');
        $menus = [
            'header' => [['होम', 'Home', '/'], ['सभी योजनाएं', 'All Schemes', '/#yojna'], ['हमारे बारे में', 'About', '/page/about']],
            'footer' => [['हमारे बारे में', 'About Us', '/page/about'], ['प्राइवेसी पॉलिसी', 'Privacy Policy', '/page/privacy-policy'], ['साइटमैप', 'Sitemap', '/sitemap.xml']],
        ];
        foreach ($menus as $loc => $items) foreach ($items as $i => $m) $st->execute([$loc, $m[0], $m[1], $m[2], $i]);
    }
}
