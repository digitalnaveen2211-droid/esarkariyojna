<?php
// Safe blog bundle reader and renderer: input JSON + 5 JPEGs, no arbitrary HTML.
defined('ESY') or exit;
require_once ROOT . '/app/draft_import.php';

function blog_bundle_inspect(string $filename): array {
    if (!class_exists('ZipArchive')) throw new RuntimeException('PHP zip extension required on VPS.');
    $zip = new ZipArchive();
    if ($zip->open($filename) !== true) throw new InvalidArgumentException('Invalid ZIP bundle.');
    try {
        if ($zip->numFiles < 2 || $zip->numFiles > 6) throw new InvalidArgumentException('Bundle must contain blog_drafts.json and 1-5 banners.');
        $contents = [];
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->statIndex($i);
            $name = (string)$entry['name'];
            if (!preg_match('#^(blog_drafts\.json|banners/[a-z0-9-]+\.jpg)$#D', $name) || isset($contents[$name])) {
                throw new InvalidArgumentException('Unexpected ZIP entry: ' . $name);
            }
            $len = (int)$entry['size'];
            if ($len < 1 || $len > 3000000) throw new InvalidArgumentException('ZIP entry size invalid: ' . $name);
            $total += $len;
            if ($total > 10000000) throw new InvalidArgumentException('Uncompressed ZIP exceeds 10 MB.');
            $data = $zip->getFromIndex($i);
            if (!is_string($data) || strlen($data) !== $len) throw new InvalidArgumentException('ZIP entry extraction failure.');
            $contents[$name] = $data;
        }
        if (!isset($contents['blog_drafts.json'])) throw new InvalidArgumentException('Missing blog_drafts.json.');
        $raw = json_decode($contents['blog_drafts.json'], true, 50, JSON_THROW_ON_ERROR);
        if (($raw['schema'] ?? '') !== 'esy-blog-drafts-v1') throw new InvalidArgumentException('Expected esy-blog-drafts-v1 JSON schema.');
        $blogs = $raw['blogs'] ?? null;
        if (!is_array($blogs) || !array_is_list($blogs) || count($blogs) < 1 || count($blogs) > 5 || count($contents) !== count($blogs) + 1) {
            throw new InvalidArgumentException('Need 1–5 articles and exactly one banner per article.');
        }
        $seen = [];
        $result = [];
        foreach ($blogs as $b) {
            if (!is_array($b)) throw new InvalidArgumentException('Each blog must be an object.');
            $slug = draft_import_text($b, 'slug', 150, true);
            if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || isset($seen[$slug])) throw new InvalidArgumentException('Invalid or duplicate blog slug: ' . $slug);
            $seen[$slug] = true;
            foreach (['title_hi'=>255,'title_en'=>255,'summary_hi'=>1000,'summary_en'=>1000,'seo_title'=>255,'seo_title_en'=>255,'seo_desc'=>500,'seo_desc_en'=>500,'banner_alt_hi'=>255,'banner_alt_en'=>255] as $k=>$max) {
                draft_import_text($b, $k, $max, true);
            }
            draft_import_text($b,'keywords',500);
            $banner = draft_import_text($b, 'banner', 220, true);
            if ($banner !== 'banners/' . $slug . '.jpg' || !isset($contents[$banner])) throw new InvalidArgumentException('Banner missing for ' . $slug);
            $size = getimagesizefromstring($contents[$banner]);
            if (!$size || $size['mime'] !== 'image/jpeg' || $size[0] < 800 || $size[1] < 450 || $size[0] > 5000 || $size[1] > 5000) {
                throw new InvalidArgumentException('Expected a valid 800px+ JPEG banner for ' . $slug);
            }
            $sources = $b['sources'] ?? [];
            if (!is_array($sources) || !array_is_list($sources) || !$sources || count($sources) > 8) throw new InvalidArgumentException('Supply 1–8 official source URLs.');
            foreach ($sources as $url) {
                if (!is_string($url) || !draft_import_url($url)) throw new InvalidArgumentException('Invalid HTTPS source URL.');
                $host = strtolower((string)parse_url($url, PHP_URL_HOST));
                if (!draft_import_official_url($url) && !preg_match('/(^|\.)brlps\.in$/', $host)) throw new InvalidArgumentException('Source not approved government or BRLPS host.');
            }
            foreach (['sections_hi','sections_en'] as $langKey) {
                if (!is_array($b[$langKey] ?? null) || !array_is_list($b[$langKey]) || count($b[$langKey]) < 10 || count($b[$langKey]) > 90) {
                    throw new InvalidArgumentException($langKey . ' needs 10–90 structured blocks.');
                }
                $visible = '';
                foreach ($b[$langKey] as $section) {
                    if (!is_array($section)) throw new InvalidArgumentException('Invalid content block');
                    $kind = $section['type'] ?? '';
                    if (!in_array($kind, ['h2','h3','p','list','table'], true)) throw new InvalidArgumentException('Unsupported content block type.');
                    if (in_array($kind, ['h2','h3','p'], true)) {
                        $visible .= draft_import_text($section, 'text', 5500, true) . ' ';
                    } elseif ($kind === 'list') {
                        if (!is_array($section['items'] ?? null) || count($section['items']) > 20) throw new InvalidArgumentException('Invalid list');
                        foreach ($section['items'] as $item) {
                            if (!is_string($item) || mb_strlen($item) > 1000) throw new InvalidArgumentException('Invalid list text');
                            $visible .= $item . ' ';
                        }
                    } else {
                        if (!is_array($section['headers'] ?? null) || !is_array($section['rows'] ?? null)
                            || count($section['headers']) < 2 || count($section['headers']) > 5 || count($section['rows']) > 25) {
                            throw new InvalidArgumentException('Invalid table');
                        }
                        foreach ([$section['headers'], ...$section['rows']] as $row) {
                            if (!is_array($row) || count($row) !== count($section['headers'])) throw new InvalidArgumentException('Table width mismatch.');
                            foreach ($row as $cell) {
                                if (!is_string($cell) || mb_strlen($cell) > 650) throw new InvalidArgumentException('Invalid table cell.');
                                $visible .= $cell . ' ';
                            }
                        }
                    }
                }
                if (mb_strlen($visible) < 1700) throw new InvalidArgumentException($langKey . ': article too short.');
            }
            $related = $b['related_posts'] ?? [];
            if (!is_array($related) || !array_is_list($related) || count($related) > 8) throw new InvalidArgumentException('Invalid related link list.');
            foreach ($related as $rel) {
                if (!is_array($rel) || !in_array($rel['type'] ?? '', ['yojna','blog'], true)) throw new InvalidArgumentException('Invalid related type.');
                $rslug = draft_import_text($rel,'slug',150,true);
                if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$rslug)) throw new InvalidArgumentException('Invalid related slug.');
                draft_import_text($rel,'label_hi',150,true);
                draft_import_text($rel,'label_en',150,true);
            }
            $result[] = $b;
        }
        return ['blogs'=>$result,'images'=>$contents];
    } finally { $zip->close(); }
}

function blog_bundle_render_blocks(array $blocks): string {
    $out = [];
    foreach ($blocks as $block) {
        $type = $block['type'];
        if ($type === 'h2' || $type === 'h3' || $type === 'p') {
            $out[] = '<' . $type . '>' . e($block['text']) . '</' . $type . '>';
        } elseif ($type === 'list') {
            $out[] = '<ul>' . implode('', array_map(static fn($v) => '<li>' . e($v) . '</li>', $block['items'])) . '</ul>';
        } elseif ($type === 'table') {
            $heads = implode('', array_map(static fn($v) => '<th scope="col">' . e($v) . '</th>', $block['headers']));
            $rows = '';
            foreach ($block['rows'] as $row) $rows .= '<tr>' . implode('', array_map(static fn($v) => '<td>' . e($v) . '</td>', $row)) . '</tr>';
            $out[] = '<div class="table-responsive"><table><thead><tr>' . $heads . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
        }
    }
    return implode("\n", $out);
}

function blog_bundle_render(array $blog, string $lang, array $validRelated): string {
    $html = blog_bundle_render_blocks($blog['sections_' . $lang]);
    $links = [];
    foreach ($validRelated as $r) {
        $path = '/' . $lang . '/' . ($r['type'] === 'blog' ? 'blog' : 'yojna') . '/' . $r['slug'];
        $label = $r[$lang === 'hi' ? 'label_hi' : 'label_en'];
        $links[] = '<li><a href="' . e($path) . '">' . e($label) . '</a></li>';
    }
    if ($links) $html .= "\n<h2>" . ($lang === 'hi' ? 'संबंधित लेख और योजनाएं' : 'Related guides and schemes') . "</h2><ul>" . implode('', $links) . '</ul>';
    $src = [];
    foreach ($blog['sources'] as $url) $src[] = '<li><a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . e($url) . '</a></li>';
    $html .= "\n<h2>" . ($lang === 'hi' ? 'आधिकारिक स्रोत' : 'Official sources') . "</h2><ul>" . implode('', $src) . '</ul>';
    $html .= '<p>' . ($lang === 'hi' ? 'यह स्वतंत्र सूचना पोर्टल है, सरकारी वेबसाइट नहीं। आवेदन या लाभ से पहले नवीनतम सरकारी सूचना जांचें।' : 'This is an independent information portal, not a government website. Verify current official rules before applying.') . '</p>';
    return $html;
}
