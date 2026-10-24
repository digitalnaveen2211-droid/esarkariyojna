<?php
defined('ESY') or exit;

/**
 * Reduce repeated OUTBOUND links in rendered blog content without hiding
 * distinct government sources or changing anything saved in the CMS.
 *
 * First clickable instance of each exact external URL remains. Later repeated
 * anchors retain all their existing inner HTML and text, but not the <a>.
 * Intentionally never rewrite links pointing to distinct destinations.
 */
function esy_dedupe_blog_external_links(string $html): string {
    if ($html === '' || stripos($html, '<a') === false) return $html;
    $seen = [];
    $pattern = '~(<a\b(?:(?:"[^"]*"|\'[^\']*\'|[^\'"<>])*)>)(.*?)(</a\s*>)~isu';
    $result = preg_replace_callback($pattern, static function (array $match) use (&$seen): string {
        $opening = $match[1];
        if (!preg_match('~\bhref\s*=\s*(["\'])(.*?)\1~isu', $opening, $attr)) {
            return $match[0];
        }
        $url = html_entity_decode(trim($attr[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!preg_match('~^https?://~i', $url)) return $match[0];
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if ($host === '' || in_array($host, ['esarkariyojna.com', 'www.esarkariyojna.com'], true)) return $match[0];
        // A linked image or button must not become a broken action.
        if (preg_match('~<(?:img|button|svg)\b~i', $match[2])) return $match[0];
        if (isset($seen[$url])) return $match[2]; // Preserve text and formatting.
        $seen[$url] = true;
        return $match[0];
    }, $html);
    return $result ?? $html;
}
