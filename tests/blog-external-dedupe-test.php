<?php
define('ESY', 1);
require dirname(__DIR__) . '/app/blog_external_links.php';
function check(string $name, bool $ok): void {
    if (!$ok) { fwrite(STDERR, "FAIL {$name}\n"); exit(1); }
    echo "PASS {$name}\n";
}
$in = '<p>Visit <a href="https://pmkisan.gov.in/">official portal</a>.</p>'
    . '<p>See <a href="https://pmkisan.gov.in/"><strong>PM Kisan official site</strong></a>.</p>'
    . '<p>And <a href="https://pmkisan.gov.in/BeneficiaryStatus_New.aspx">check payment status</a>.</p>'
    . '<p><a href="/en/yojna/pradhan-mantri-kisan-samman-nidhi-yojana">internal guide</a></p>';
$out = esy_dedupe_blog_external_links($in);
check('reduces exact duplicate only', substr_count($out, 'href="https://pmkisan.gov.in/"') === 1);
check('preserves anchor text and nested formatting', str_contains($out, '<strong>PM Kisan official site</strong>'));
check('preserves distinct official action', str_contains($out, 'href="https://pmkisan.gov.in/BeneficiaryStatus_New.aspx"'));
check('preserves internal', str_contains($out, 'href="/en/yojna/pradhan-mantri-kisan-samman-nidhi-yojana"'));
check('preserves surrounding markup', substr_count($out, '<p>') === 4);
$withEscaped = '<p><a href="https://example.gov.in/?a=1&amp;b=2">A</a>'
             . '<a href="https://example.gov.in/?a=1&amp;b=2">B</a></p>';
check('recognizes entity-escaped duplicate URL',
    substr_count(esy_dedupe_blog_external_links($withEscaped), '<a ') === 1);
check('leaves original source unchanged', substr_count($in, '<a ') === 4);
echo "All external-link dedupe tests passed.\n";
