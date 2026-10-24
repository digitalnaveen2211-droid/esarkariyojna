<?php
// Run: php tests/blog-inline-anchor-test.php
define('ESY', 1);
define('LANG', 'en');
function lang() { return LANG; }
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function lurl($path) { return '/en' . $path; }
function tr($hi,$en) { return $en; }
function has_english($post) { return trim((string)$post['content_en']) !== ''; }
function post_path($type,$slug) { return '/' . ($type === 'yojna' ? 'yojna' : 'blog') . '/' . $slug; }
function find_post($type,$slug) {
    if ($slug === 'pm-kisan-23rd-installment-physical-verification') return null; // Missing target.
    return ['type'=>$type,'slug'=>$slug,'title_hi'=>'शीर्षक','title_en'=>'Title','content_en'=>'Present'];
}
require dirname(__DIR__) . '/app/seo_editorial_links.php';
function check_case($name,$condition) {
    if (!$condition) { fwrite(STDERR, 'FAIL ' . $name . "\n"); exit(1); }
    echo 'OK ' . $name . "\n";
}
$slug = 'pm-kisan-ekyc-done-payment-pending';
$html = '<h2>Beneficiary list</h2><p>Check the beneficiary list before you apply. <strong>PM Kisan scheme</strong> has detailed rules.</p><p>More information about payment pending and beneficiary list.</p>';
$out = esy_blog_inline_links($slug,$html);
check_case('preserves heading',str_contains($out,'<h2>Beneficiary list</h2>'));
check_case('links existing text',str_contains($out,'<a href="/en/blog/pm-kisan-beneficiary-list-village-check">beneficiary list</a>'));
check_case('leaves original formatting',str_contains($out,'<strong>'));
check_case('maximum three links',substr_count($out,'<a href=') <= 3);
check_case('no nested links',!preg_match('~<a\\b[^>]*>[^<]*<a\\b~',$out));
check_case('no links for unrelated text',esy_blog_inline_links($slug,'<p>No matching subject here.</p>') === '<p>No matching subject here.</p>');
$existing = '<p><a href="/en/blog/pm-kisan-beneficiary-list-village-check">beneficiary list</a> and beneficiary list are useful.</p>';
$existingOut = esy_blog_inline_links($slug,$existing);
check_case('respects existing editorial anchor',substr_count($existingOut,'href="/en/blog/pm-kisan-beneficiary-list-village-check"') === 1);
check_case('no unrelated section',!str_contains($out,'Related reading'));
echo "All inline-anchor tests passed.\n";
