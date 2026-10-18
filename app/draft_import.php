<?php
// Secure, draft-only scheme import helpers. Input articles are plain Markdown-like text,
// not arbitrary HTML. This makes automated research submissions safe to review in the CMS.
defined('ESY') or exit;

function draft_import_response(int $status, array $data): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function draft_import_https(): bool {
    return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function draft_import_url(string $url): bool {
    $parts = parse_url($url);
    return is_array($parts)
        && strtolower((string)($parts['scheme'] ?? '')) === 'https'
        && !empty($parts['host'])
        && !isset($parts['user']) && !isset($parts['pass'])
        && strlen($url) <= 500
        && !preg_match('/[\x00-\x20\x7f]/', $url);
}

function draft_import_official_url(string $url): bool {
    if (!draft_import_url($url)) return false;
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    return (bool)preg_match('/(^|\.)(gov\.in|nic\.in)$/', $host);
}

function draft_import_text(array $row, string $key, int $max, bool $required = false): string {
    $value = $row[$key] ?? '';
    if (!is_string($value)) throw new InvalidArgumentException("$key must be text");
    $value = trim($value);
    if (mb_strlen($value) > $max) throw new InvalidArgumentException("$key exceeds $max characters");
    if ($required && $value === '') throw new InvalidArgumentException("$key is required");
    return $value;
}

function draft_import_html(string $text): string {
    // Encode the entire supplied text, then allow only generated h2/h3, p, and ul/li.
    $out = [];
    $paragraph = [];
    $list = [];
    $flushP = static function () use (&$paragraph, &$out): void {
        if ($paragraph) $out[] = '<p>' . e(implode(' ', $paragraph)) . '</p>';
        $paragraph = [];
    };
    $flushL = static function () use (&$list, &$out): void {
        if ($list) $out[] = '<ul>' . implode('', array_map(static fn($t) => '<li>' . e($t) . '</li>', $list)) . '</ul>';
        $list = [];
    };
    foreach (preg_split('/\R/u', $text) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') { $flushP(); $flushL(); continue; }
        if (preg_match('/^(#{2,3})\s+(.+)$/u', $line, $m)) {
            $flushP(); $flushL();
            $tag = strlen($m[1]) === 2 ? 'h2' : 'h3';
            $out[] = "<$tag>" . e($m[2]) . "</$tag>";
        } elseif (preg_match('/^[-*]\s+(.+)$/u', $line, $m)) {
            $flushP();
            $list[] = $m[1];
        } else {
            $flushL();
            $paragraph[] = $line;
        }
    }
    $flushP(); $flushL();
    return implode("\n", $out);
}

function draft_import_validate(array $raw, array $categories): array {
    $slug = draft_import_text($raw, 'slug', 150, true);
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        throw new InvalidArgumentException('slug must contain lowercase letters, digits and hyphens');
    }
    $categorySlug = draft_import_text($raw, 'category_slug', 80, true);
    if (!isset($categories[$categorySlug])) throw new InvalidArgumentException('category_slug not found in CMS');

    $fields = [
        'title_hi' => 255, 'title_en' => 255,
        'summary_hi' => 1000, 'summary_en' => 1000,
        'elig_hi' => 1200, 'elig_en' => 1200,
        'content_hi_md' => 60000, 'content_en_md' => 60000,
        'seo_title' => 255, 'seo_desc' => 500,
        'seo_title_en' => 255, 'seo_desc_en' => 500,
        'keywords' => 500,
    ];
    $data = [];
    foreach ($fields as $key => $max) {
        $required = in_array($key, [
            'title_hi','title_en','summary_hi','summary_en','elig_hi','elig_en',
            'content_hi_md','content_en_md','seo_title','seo_desc','seo_title_en','seo_desc_en'
        ], true);
        $data[$key] = draft_import_text($raw, $key, $max, $required);
    }
    foreach (['content_hi_md','content_en_md'] as $key) {
        if (mb_strlen($data[$key]) < 800) throw new InvalidArgumentException("$key should contain at least 800 characters");
    }

    $sourceUrls = $raw['source_urls'] ?? null;
    if (!is_array($sourceUrls) || !$sourceUrls || count($sourceUrls) > 8 || array_is_list($sourceUrls) === false) {
        throw new InvalidArgumentException('source_urls must contain 1-8 official source links');
    }
    foreach ($sourceUrls as $url) {
        if (!is_string($url) || !draft_import_official_url($url)) {
            throw new InvalidArgumentException('Every source must be an HTTPS gov.in/nic.in URL');
        }
    }
    $sourceUrls = array_values(array_unique($sourceUrls));
    $officialUrl = draft_import_text($raw, 'official_url', 500, true);
    if (!draft_import_official_url($officialUrl)) throw new InvalidArgumentException('official_url must be an HTTPS gov.in/nic.in URL');

    $sourceHtml = '<h2>Official sources / आधिकारिक स्रोत</h2><ul>';
    foreach ($sourceUrls as $url) {
        $sourceHtml .= '<li><a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . e($url) . '</a></li>';
    }
    $sourceHtml .= '</ul><p>Independent informational portal, not a government agency. Verify the latest scheme rules before applying.</p>';

    $govLevel = ($raw['gov_level'] ?? 'central') === 'state' ? 'state' : 'central';
    $stateCode = strtoupper(trim((string)($raw['state_code'] ?? '')));
    if ($govLevel === 'state' && !array_key_exists($stateCode, scheme_states())) {
        throw new InvalidArgumentException('State schemes must provide a valid state_code');
    }
    if ($govLevel === 'central') $stateCode = '';

    return [
        'slug' => $slug,
        'category_id' => $categories[$categorySlug],
        'gov_level' => $govLevel, 'state_code' => $stateCode,
        'title_hi' => $data['title_hi'], 'title_en' => $data['title_en'],
        'summary_hi' => $data['summary_hi'], 'summary_en' => $data['summary_en'],
        'elig_hi' => $data['elig_hi'], 'elig_en' => $data['elig_en'],
        'content_hi' => draft_import_html($data['content_hi_md']) . "\n" . $sourceHtml,
        'content_en' => draft_import_html($data['content_en_md']) . "\n" . $sourceHtml,
        'seo_title' => $data['seo_title'], 'seo_title_en' => $data['seo_title_en'],
        'seo_desc' => $data['seo_desc'], 'seo_desc_en' => $data['seo_desc_en'],
        'keywords' => $data['keywords'], 'official_url' => $officialUrl,
    ];
}
