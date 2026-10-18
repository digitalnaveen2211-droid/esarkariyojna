<?php
// Pure validation tests: no database, HTTP, credentials or CMS mutations.
define('ESY', 1);
define('ROOT', dirname(__DIR__));
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
require ROOT . '/app/blog_draft_import.php';
function blog_expect(bool $value, string $label): void {
    if (!$value) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
}
function blog_reject(callable $fn, string $label): void {
    try { $fn(); } catch (InvalidArgumentException $e) { return; }
    blog_expect(false, $label);
}
$blocks = [
    ['type'=>'h2','text'=>'ब्याज सहायता <script>alert(1)</script>'],
    ['type'=>'p','text'=>'सुरक्षित & पाठ'],
    ['type'=>'list','items'=>['एक <iframe>','दो']],
    ['type'=>'table','headers'=>['विषय','लाभ'], 'rows'=>[['बैंक <script>','₹10,000 & विवरण']]]
];
$html = blog_bundle_render_blocks($blocks);
blog_expect(str_contains($html, '&lt;script&gt;') && !str_contains($html, '<script>'), 'Block HTML must be escaped');
blog_expect(str_contains($html, '<table>') && str_contains($html, '<th scope="col">'), 'Structured table rendered');
blog_expect(str_contains($html, '&lt;iframe&gt;'), 'List is safe');
$rendered = blog_bundle_render(['sections_hi'=>$blocks,'sources'=>['https://www.pib.gov.in/PressReleasePage.aspx?PRID=2225510']], 'hi', [
 ['type'=>'yojna','slug'=>'example-scheme','label_hi'=>'संबंधित योजना','label_en'=>'Related scheme']
]);
blog_expect(str_contains($rendered, 'href="/hi/yojna/example-scheme"'), 'Internal link correct');
blog_expect(str_contains($rendered, 'rel="noopener noreferrer"'), 'Official external links secured');
if (!class_exists('ZipArchive')) { fwrite(STDERR, "zip extension missing\n"); exit(1); }
$tmp = tempnam(sys_get_temp_dir(), 'esy-test-');
$zip = new ZipArchive();
$zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('blog_drafts.json', json_encode(['schema'=>'wrong','blogs'=>[]]));
$zip->addFromString('banners/../evil.jpg', 'not a real image');
$zip->close();
blog_reject(fn() => blog_bundle_inspect($tmp), 'Reject zip path traversal');
unlink($tmp);
echo "Blog draft ZIP validation tests passed\n";
