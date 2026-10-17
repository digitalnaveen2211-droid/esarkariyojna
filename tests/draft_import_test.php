<?php
// Run: php tests/draft_import_test.php
// No database or CMS credentials needed.
define('ESY', 1);
function e($x): string { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
require dirname(__DIR__) . '/app/draft_import.php';

function expect(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAILED: $message\n"); exit(1); }
}
function expect_invalid(callable $f, string $message): void {
    try { $f(); } catch (InvalidArgumentException $e) { return; }
    expect(false, $message);
}
$categoryIds = ['kisan' => 1];
$fill = static fn(string $s, int $n) => str_repeat($s, $n);
$base = [
    'slug' => 'test-central-scheme',
    'category_slug' => 'kisan',
    'title_hi' => 'उदाहरण योजना',
    'title_en' => 'Example central scheme',
    'summary_hi' => 'एक सरकारी योजना की जानकारी',
    'summary_en' => 'An example informational scheme summary',
    'elig_hi' => 'आधिकारिक पात्रता लागू',
    'elig_en' => 'Official eligibility applies',
    'content_hi_md' => "## परिचय\n" . $fill('प्रक्रिया और पात्रता। ', 80),
    'content_en_md' => "## Overview\n" . $fill('Process and eligibility description. ', 40),
    'seo_title' => 'योजना पात्रता',
    'seo_title_en' => 'Scheme eligibility',
    'seo_desc' => 'पात्रता और आवेदन',
    'seo_desc_en' => 'Eligibility and application details',
    'official_url' => 'https://example.gov.in/scheme',
    'source_urls' => ['https://example.gov.in/scheme'],
];
$valid = draft_import_validate($base, $categoryIds);
expect($valid['slug'] === $base['slug'], 'Accept a valid bilingual scheme');
expect(str_contains($valid['content_en'], 'href="https://example.gov.in/scheme"'), 'Generate actual clickable official source');
expect(str_contains($valid['content_hi'], '<h2>परिचय</h2>'), 'Render headings');
expect(!str_contains(draft_import_html('<script>alert(1)</script>'), '<script>'), 'Escape script tags');
expect(!str_contains(draft_import_html('<img src=x onerror=alert(1)>'), '<img'), 'Escape malicious HTML');
expect(!draft_import_official_url('https://example.gov.in.evil.com/'), 'Reject official looking malicious host');
expect(!draft_import_official_url('http://example.gov.in/'), 'Reject HTTP');
expect(!draft_import_official_url('https://user:pass@example.gov.in/'), 'Reject credentials in URL');
expect(draft_import_official_url('https://pmkisan.gov.in/'), 'Accept legitimate government domain');
expect_invalid(fn() => draft_import_validate($base + [], []), 'Reject missing category');
expect_invalid(fn() => draft_import_validate(array_replace($base, ['slug'=>'TEST']), $categoryIds), 'Reject invalid slug');
expect_invalid(fn() => draft_import_validate(array_replace($base, ['source_urls'=>['https://evil.com/']]), $categoryIds), 'Reject nonofficial source');
expect_invalid(fn() => draft_import_validate(array_replace($base, ['source_urls'=>[['bad'=>'type']]]), $categoryIds), 'Reject nonstring source');
expect_invalid(fn() => draft_import_validate(array_replace($base, ['content_en_md'=>'Too short']), $categoryIds), 'Reject shallow content');
expect_invalid(fn() => draft_import_validate(array_replace($base, ['title_en'=>'']), $categoryIds), 'Reject missing English title');
echo "Draft import validation tests passed\n";
