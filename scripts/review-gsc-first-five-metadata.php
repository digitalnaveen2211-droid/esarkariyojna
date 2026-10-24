<?php
declare(strict_types=1);
/**
 * Guarded one-time CMS SEO metadata review and optional manual update.
 * NEVER executed during website deployment or normal requests.
 *
 * Preview: php scripts/review-gsc-first-five-metadata.php
 * Apply:   php scripts/review-gsc-first-five-metadata.php --apply --confirm=GSC-FIRST-FIVE-2026-10-25
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
if (!is_installed()) { fwrite(STDERR, "CMS is not configured.\n"); exit(1); }

$proposals = [
    ['blog', 'sukanya-samriddhi-withdrawal-premature-closure-rules', 'en',
     'Sukanya Withdrawal Rules 2026: 50% Limit & Closure',
     'When can you withdraw from Sukanya Samriddhi? Understand the Class 10/age 18 education rule, 50% balance limit, marriage closure and documents.'],
    ['yojna', 'sarva-shiksha-abhiyan-samagra-shiksha', 'en',
     'Samagra Shiksha: SSA, RMSA & Teacher Education',
     'Samagra Shiksha combines SSA, RMSA and Teacher Education. Learn its school education scope, support from preschool to Class 12 and how it works.'],
    ['blog', 'ayushman-bharat-pm-jay-eligibility-2026', 'hi',
     'आयुष्मान कार्ड पात्रता 2026: किसे मिलेगा? 70+ नियम',
     'जानें आयुष्मान भारत PM-JAY में कौन पात्र है, परिवार की पात्रता कैसे जांचें और 70 वर्ष से अधिक उम्र के नागरिकों के लिए अलग नियम क्या हैं।'],
    ['blog', 'ayushman-vay-vandana-70-plus', 'en',
     'Ayushman Vay Vandana Card 2026: Eligibility & Apply',
     'Who qualifies for the Ayushman Vay Vandana Card at age 70+? Check Aadhaar eKYC, enrolment, ₹5 lakh family coverage and official application steps.'],
    ['yojna', 'nikshay-poshan-yojana', 'en',
     'Ni-kshay Poshan Yojana 2026: TB DBT & Payment Status',
     'Understand nutritional DBT support of ₹1,000 per month during notified TB treatment, Ni-kshay registration, bank details and payment-status checks.'],
];
$apply = in_array('--apply', $argv, true);
$confirm = in_array('--confirm=GSC-FIRST-FIVE-2026-10-25', $argv, true);
if ($apply && !$confirm) {
    fwrite(STDERR, "Refusing writes: explicit confirmation required. Preview first.\n");
    exit(2);
}
$records = [];
$columns = array_column(q_all('SHOW COLUMNS FROM posts'), 'Field');
foreach (['seo_title', 'seo_desc', 'seo_title_en', 'seo_desc_en'] as $col) {
    if (!in_array($col, $columns, true)) {
        fwrite(STDERR, "Missing CMS column {$col}; no data changed.\n");
        exit(2);
    }
}
foreach ($proposals as [$type, $slug, $locale, $title, $description]) {
    $post = q_one("SELECT id,type,slug,status,title_hi,title_en,seo_title,seo_desc,seo_title_en,seo_desc_en FROM posts WHERE type=? AND slug=? AND status='published'", [$type, $slug]);
    if (!$post) {
        fwrite(STDERR, "Missing published {$type}/{$slug}. Stopping before changes.\n");
        exit(2);
    }
    $titleColumn = $locale === 'en' ? 'seo_title_en' : 'seo_title';
    $descriptionColumn = $locale === 'en' ? 'seo_desc_en' : 'seo_desc';
    $records[] = ['post' => $post, 'locale' => $locale, 'titleColumn' => $titleColumn,
                  'descColumn' => $descriptionColumn, 'title' => $title, 'description' => $description];
}
foreach ($records as $r) {
    $p = $r['post'];
    echo "\n{$p['type']}/{$p['slug']} [{$r['locale']}]\n";
    echo "Current title: " . $p[$r['titleColumn']] . "\n";
    echo "Proposed title: " . $r['title'] . "\n";
    echo "Current description: " . $p[$r['descColumn']] . "\n";
    echo "Proposed description: " . $r['description'] . "\n";
}
if (!$apply) {
    echo "\nPREVIEW ONLY — no changes. Editorially review current content and proposed wording before manual apply.\n";
    exit(0);
}
// Backup exact original metadata first; abort if it cannot be securely written.
$backupPath = sys_get_temp_dir() . '/esy-gsc-first-five-metadata-' . gmdate('Ymd-His') . '-' . getmypid() . '.json';
$backup = [];
foreach ($records as $r) {
    $p = $r['post'];
    $backup[] = ['id' => (int)$p['id'], 'type' => $p['type'], 'slug' => $p['slug'],
                 'seo_title' => $p['seo_title'], 'seo_desc' => $p['seo_desc'],
                 'seo_title_en' => $p['seo_title_en'], 'seo_desc_en' => $p['seo_desc_en']];
}
$encoded = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
if (file_put_contents($backupPath, $encoded, LOCK_EX) === false || !chmod($backupPath, 0600)) {
    fwrite(STDERR, "Backup creation failed. No data changed.\n");
    exit(3);
}
echo "\nBackup created at {$backupPath}.\n";
try {
    db()->beginTransaction();
    foreach ($records as $r) {
        $p = $r['post'];
        $sql = "UPDATE posts SET {$r['titleColumn']}=?, {$r['descColumn']}=? WHERE id=? AND type=? AND slug=? AND status='published' AND {$r['titleColumn']}=? AND {$r['descColumn']}=?";
        $stmt = q($sql, [$r['title'], $r['description'], $p['id'], $p['type'], $p['slug'], $p[$r['titleColumn']], $p[$r['descColumn']]]);
        if ($stmt->rowCount() !== 1 && ($p[$r['titleColumn']] !== $r['title'] || $p[$r['descColumn']] !== $r['description'])) {
            throw new RuntimeException('Concurrent update or unchanged state for ' . $p['slug'] . '; rolling back.');
        }
    }
    db()->commit();
    echo "Five selected locale metadata records updated successfully. Verify live page sources and preserve backup.\n";
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    fwrite(STDERR, "Transaction rolled back: " . $e->getMessage() . "\n");
    exit(4);
}
