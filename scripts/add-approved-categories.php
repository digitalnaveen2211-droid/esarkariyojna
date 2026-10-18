<?php
declare(strict_types=1);
// Explicitly approved 2026-10-18 category expansion: guarded, idempotent, CLI only.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
if (!is_installed()) { fwrite(STDERR, "CMS not installed\n"); exit(1); }
$expectedExisting = ['kisan','swasthya','awas','mahila','vyapar','pension','shiksha','rozgar','bijli-urja'];
$newSlugs = ['samajik-kalyan','banking-bima','bal-vikas','ration-khadya','parivahan'];
$seed = json_decode((string) file_get_contents(ROOT . '/app/seed/categories.json'), true, 30, JSON_THROW_ON_ERROR);
$seo = json_decode((string) file_get_contents(ROOT . '/app/seed/category_seo.json'), true, 30, JSON_THROW_ON_ERROR);
$spec = [];
foreach ($seed as $category) {
    if (in_array($category['id'], $newSlugs, true)) $spec[$category['id']] = $category;
}
if (count($spec) !== 5) { fwrite(STDERR, "Seed category list incomplete\n"); exit(1); }
$pdo = db();
$pdo->beginTransaction();
try {
    $actual = $pdo->query('SELECT slug FROM categories ORDER BY slug FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN);
    $sorted = static function (array $v): array { sort($v, SORT_STRING); return $v; };
    if ($sorted($actual) === $sorted(array_merge($expectedExisting, $newSlugs))) {
        $pdo->commit(); echo "Already installed: 14 categories. No changes.\n"; exit(0);
    }
    if ($sorted($actual) !== $sorted($expectedExisting)) {
        throw new RuntimeException('Live category slugs do not match approved nine-category screenshot. No changes made.');
    }
    $insert = $pdo->prepare('INSERT INTO categories (slug,name_hi,name_en,icon,color,sort,seo_title_hi,seo_title_en,seo_desc_hi,seo_desc_en,content_hi,content_en) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($newSlugs as $i => $slug) {
        $c = $spec[$slug];
        $s = $seo[$slug] ?? null;
        if (!is_array($s)) throw new RuntimeException('Missing SEO data for '.$slug);
        $insert->execute([$slug,$c['name_hi'],$c['name_en'],$c['icon'],$c['color'],$i + 9,
            $s['seo_title_hi'],$s['seo_title_en'],$s['seo_desc_hi'],$s['seo_desc_en'],$s['content_hi'],$s['content_en']]);
    }
    $pdo->commit();
    echo "Installed five categories. Total expected: 14. No posts changed.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
