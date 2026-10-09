<?php
// On-page SEO score (0-100) for a yojna or page, with a checklist of what to improve.
defined('ESY') or exit;

function seo_len(string $s): int { return mb_strlen(trim(html_entity_decode(strip_tags($s), ENT_QUOTES, 'UTF-8'))); }
function seo_words(string $html): int {
    $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES, 'UTF-8')));
    return $t === '' ? 0 : count(preg_split('/\s+/u', $t));
}

/** @return array{score:int, checks:array<int,array{ok:bool|null,label:string,tip:string,pts:int}>} */
function seo_analyze(array $p): array {
    $isYojna = ($p['type'] ?? 'yojna') === 'yojna';
    $title = trim((string)($p['seo_title'] ?? '')) ?: (string)($p['title_hi'] ?? '');
    $desc = trim((string)($p['seo_desc'] ?? '')) ?: ($isYojna ? (string)($p['summary_hi'] ?? '') : '');
    $content = (string)($p['content_hi'] ?? '');
    $words = seo_words($content);
    $tl = seo_len($title);
    $dl = seo_len($desc);
    $h2 = preg_match_all('/<h[23][\s>]/i', $content);
    $imgs = preg_match_all('/<img\b[^>]*>/i', $content, $im);
    $noAlt = 0;
    foreach ($im[0] ?? [] as $tag) if (!preg_match('/\balt\s*=\s*"[^"]+"/i', $tag)) $noAlt++;
    $links = preg_match_all('/<a\s[^>]*href=/i', $content);
    $slug = (string)($p['slug'] ?? '');
    $kw = trim((string)($p['keywords'] ?? ''));
    $firstKw = $kw !== '' ? mb_strtolower(preg_split('/[\s,]+/u', $kw)[0]) : '';
    $hay = mb_strtolower($title . ' ' . ($p['title_en'] ?? '') . ' ' . $slug);
    $hasEn = trim((string)($p['title_en'] ?? '')) !== '' && seo_words((string)($p['content_en'] ?? '')) > 50;

    $c = [];
    $add = function (?bool $ok, int $pts, string $label, string $tip) use (&$c) { $c[] = compact('ok', 'pts', 'label', 'tip'); };
    $add($tl >= 30 && $tl <= 65, 15, "SEO title: $tl characters", $tl < 30 ? 'Title thoda lamba karein (30-65 characters).' : ($tl > 65 ? 'Title chhota karein, Google 60-65 ke baad kaat deta hai.' : 'Badhiya.'));
    $add($dl >= 110 && $dl <= 165, 15, "Meta description: $dl characters", $dl === 0 ? 'Meta description likhein (120-160 characters).' : ($dl < 110 ? 'Description thoda aur likhein (120-160).' : ($dl > 165 ? 'Description 160 characters tak rakhein.' : 'Badhiya.')));
    $add($words >= 600 ? true : ($words >= 300 ? null : false), 20, "Content: $words shabd", $words < 300 ? 'Kam se kam 300 shabd, behtar 600+ shabd likhein.' : ($words < 600 ? 'Theek hai. 600+ shabd ho to aur achha rank karega.' : 'Badhiya.'));
    $add($h2 >= 2, 10, "Headings (H2/H3): $h2", $h2 < 2 ? 'Content ko 2-3 headings (H2) me baantein, jaise "Patrata", "Kaise apply karein".' : 'Badhiya.');
    $add(!empty($p['image']), 10, 'Featured image ' . (empty($p['image']) ? 'nahi hai' : 'lagi hai'), empty($p['image']) ? 'Ek featured image lagaiye (share karne par bhi dikhti hai).' : 'Badhiya.');
    $add(strlen($slug) > 0 && strlen($slug) <= 60 && !preg_match('/\d{4,}/', $slug), 5, 'URL slug: ' . ($slug ?: '—'), strlen($slug) > 60 ? 'URL chhota rakhein (60 characters tak).' : 'Badhiya.');
    $add($imgs === 0 ? true : $noAlt === 0, 5, $imgs ? "Content images: $imgs, bina alt text: $noAlt" : 'Content me images: 0', $noAlt ? 'Har image ka alt text likhein (image par click → description).' : 'Badhiya.');
    if ($isYojna) {
        $add(!empty($p['official_url']) || $links > 0, 5, 'Official / bahari link ' . (!empty($p['official_url']) || $links ? 'hai' : 'nahi hai'), 'Official website ka link daalein, bharosa badhta hai.');
        $add($kw === '' ? false : ($firstKw !== '' && mb_strpos($hay, $firstKw) !== false), 10, $kw === '' ? 'Keywords nahi daale' : "Pehla keyword \"$firstKw\" title/URL me " . (mb_strpos($hay, $firstKw) !== false ? 'hai' : 'nahi hai'), $kw === '' ? 'Search keywords daalein. Pehla keyword sabse zaroori wala ho.' : 'Pehla (main) keyword title ya URL me aana chahiye.');
    } else {
        $add($links > 0 || $words < 150, 5, "Content me links: $links", 'Kisi doosre page ya yojna ka link daalein.');
    }
    $add($hasEn, 5, 'English version ' . ($hasEn ? 'hai' : 'nahi hai'), $hasEn ? 'Badhiya.' : 'English title aur content bhi likhein, English search se bhi log aayenge.');

    $max = array_sum(array_column($c, 'pts'));
    $got = 0;
    foreach ($c as $x) $got += $x['ok'] === true ? $x['pts'] : ($x['ok'] === null ? intdiv($x['pts'], 2) : 0);
    return ['score' => (int)round($got * 100 / max(1, $max)), 'checks' => $c];
}

function seo_badge(int $score): string {
    $cls = $score >= 80 ? 'green' : ($score >= 50 ? 'amber' : 'red');
    return '<span class="seo-badge ' . $cls . '" title="SEO score">' . $score . '</span>';
}
