<?php
declare(strict_types=1);
// One-time reviewed research batch. CLI only; never publishes any scheme.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
require ROOT . '/app/draft_import.php';
if (!is_installed()) { fwrite(STDERR, "CMS not installed\n"); exit(1); }
$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check','--import'], true)) { fwrite(STDERR, "Use --check or --import\n"); exit(1); }
$file = ROOT . '/app/seed/researched_drafts_20261018.json';
$bundle = json_decode((string)file_get_contents($file), true, 40, JSON_THROW_ON_ERROR);
$rows = $bundle['drafts'] ?? [];
if (!is_array($rows) || count($rows) !== 19) throw new RuntimeException('Expected exactly 19 drafts');
$db = db();
$db->beginTransaction();
try {
    $cats = $db->query('SELECT slug, id FROM categories ORDER BY slug FOR UPDATE')->fetchAll(PDO::FETCH_KEY_PAIR);
    if (count($cats) !== 14) throw new RuntimeException('Expected 14 categories, found '.count($cats));
    $existing = $db->query("SELECT slug, title_hi, title_en, official_url, status FROM posts WHERE type='yojna' FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
    $inputSlugs = array_column($rows, 'slug');
    $matching = array_filter($existing, static fn($p) => in_array($p['slug'], $inputSlugs, true));
    if (count($matching) === 19 && count(array_unique(array_column($matching, 'slug'))) === 19) {
        foreach ($matching as $p) if ($p['status'] !== 'draft') throw new RuntimeException('Batch record is published: '.$p['slug']);
        $db->rollBack();
        echo "ALREADY DONE: 19 CMS records exist as drafts. No changes.\\n";
        exit(0);
    }

    $bySlug = []; $byTitle = []; $byUrl = [];
    foreach ($existing as $p) {
        $bySlug[mb_strtolower(trim($p['slug']))] = 1;
        foreach (['title_hi','title_en'] as $key) {
            $value = mb_strtolower(trim($p[$key]));
            if ($value !== '') $byTitle[$value] = 1;
        }
        // Do not block URL overlaps because multiple government sub-schemes may share official landing pages.
    }
    $clean = []; $used = [];
    foreach ($rows as $d) {
        if (!is_array($d)) throw new RuntimeException('Invalid candidate');
        $p = draft_import_validate($d, $cats);
        if (isset($used[$p['slug']])) throw new RuntimeException('Repeated batch slug: '.$p['slug']);
        $used[$p['slug']] = 1;
        if (isset($bySlug[mb_strtolower($p['slug'])])
          || isset($byTitle[mb_strtolower(trim($p['title_hi']))])
          || isset($byTitle[mb_strtolower(trim($p['title_en']))])) {
            throw new RuntimeException('Potential duplicate existing scheme/draft: '.$p['slug'].'; no inserts made.');
        }
        $clean[] = $p;
    }
    if ($mode === '--check') {
        $db->rollBack();
        echo "CHECK PASS: 19 validated, no exact slug/title conflicts; database unchanged.\n";
        exit(0);
    }
    $columns=['slug','category_id','gov_level','state_code','title_hi','title_en','summary_hi','summary_en','elig_hi','elig_en','content_hi','content_en','seo_title','seo_title_en','seo_desc','seo_desc_en','keywords','official_url'];
    $sql="INSERT INTO posts (type,".implode(',',$columns).",status,featured,created_at,updated_at) VALUES ('yojna',".implode(',',array_fill(0,count($columns),'?')).",'draft',0,NOW(),NOW())";
    $insert=$db->prepare($sql);
    foreach ($clean as $p) $insert->execute(array_map(static fn($key)=>$p[$key],$columns));
    $db->commit();
    echo "SUCCESS: inserted 19 yojna records as DRAFT only; published=0.\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    fwrite(STDERR, "NO INSERTS: ".$e->getMessage()."\n");
    exit(1);
}
