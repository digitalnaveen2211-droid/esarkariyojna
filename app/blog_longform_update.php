<?php
// Safe extension-only patch for already-published bilingual blogs.
defined('ESY') or exit;
require_once ROOT . '/app/draft_import.php';
require_once ROOT . '/app/blog_draft_import.php';
function longform_patch_validate(array $raw): array {
    if (($raw['schema'] ?? '') !== 'esy-blog-longform-v1') throw new InvalidArgumentException('Expected esy-blog-longform-v1.');
    $items = $raw['updates'] ?? null;
    if (!is_array($items) || !array_is_list($items) || count($items) < 1 || count($items) > 5) {
        throw new InvalidArgumentException('Expected 1–5 blog updates.');
    }
    $out=[];$seen=[];
    foreach ($items as $idx=>$item) {
        if (!is_array($item)) throw new InvalidArgumentException('Invalid entry.');
        $slug=draft_import_text($item,'slug',150,true);
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug)||isset($seen[$slug])) throw new InvalidArgumentException('Invalid or duplicate slug: '.$slug);
        $seen[$slug]=1;
        foreach (['hi','en'] as $lang) {
            $blocks=$item['sections_'.$lang] ?? null;
            if (!is_array($blocks)||!array_is_list($blocks)||count($blocks)<12||count($blocks)>65) {
                throw new InvalidArgumentException('Need 12–65 expansion blocks in '.$lang.' for '.$slug);
            }
            $count=0;
            foreach ($blocks as $section) {
                if (!is_array($section)||!in_array($section['type']??null,['h2','h3','p','list','table'],true)) throw new InvalidArgumentException('Invalid section type.');
                $type=$section['type'];
                if (in_array($type,['h2','h3','p'],true)) $count+=mb_strlen(draft_import_text($section,'text',6000,true));
                elseif ($type==='list') {
                    if (!is_array($section['items']??null)||!array_is_list($section['items'])||!$section['items']||count($section['items'])>30) throw new InvalidArgumentException('Invalid list.');
                    foreach ($section['items'] as $it) {if (!is_string($it)||mb_strlen($it)>700)throw new InvalidArgumentException('Invalid list element.');$count+=mb_strlen($it);}
                } else {
                    $heads=$section['headers']??null;$rows=$section['rows']??null;
                    if(!is_array($heads)||!array_is_list($heads)||count($heads)<2||count($heads)>5||!is_array($rows)||!array_is_list($rows)||count($rows)>25)throw new InvalidArgumentException('Invalid comparison table.');
                    foreach ([$heads,...$rows] as $row) {
                        if(!is_array($row)||!array_is_list($row)||count($row)!==count($heads))throw new InvalidArgumentException('Invalid table row.');
                        foreach($row as $v){if(!is_string($v)||mb_strlen($v)>600)throw new InvalidArgumentException('Invalid table text.');$count+=mb_strlen($v);}
                    }
                }
            }
            if($count<6000||$count>65000) throw new InvalidArgumentException('Expansion too short/long: '.$slug.' '.$lang.' (need substantial useful content).');
        }
        $sources=$item['source_urls']??[];
        if(!is_array($sources)||!array_is_list($sources)||!$sources||count($sources)>8)throw new InvalidArgumentException('Provide official sources.');
        foreach($sources as $url){
            if(!is_string($url)||!draft_import_url($url)) throw new InvalidArgumentException('Invalid HTTPS source.');
            $host=strtolower((string)parse_url($url,PHP_URL_HOST));
            if(!draft_import_official_url($url)&&!preg_match('/(^|\.)brlps\.in$/',$host))throw new InvalidArgumentException('Source must be gov.in, nic.in or brlps.in.');
        }
        $out[]=$item;
    }
    return $out;
}
function longform_patch_render(array $item,string $lang):string {
    $body=blog_bundle_render_blocks($item['sections_'.$lang]);
    $li=[];
    foreach($item['source_urls'] as $url) $li[]='<li><a href="'.e($url).'" target="_blank" rel="noopener noreferrer">'.e($url).'</a></li>';
    $body.="\n<h2>".($lang==='hi'?'तथ्य जांच और आधिकारिक संदर्भ':'Fact-check and official references')."</h2><ul>".implode('',$li)."</ul>";
    return "\n<!-- esy-longform-v1-start -->\n".$body."\n<!-- esy-longform-v1-end -->";
}
function longform_patch_merge(string $existing,string $generated):string {
    $start='<!-- esy-longform-v1-start -->';$end='<!-- esy-longform-v1-end -->';
    $a=strpos($existing,$start);
    if($a===false) {
        // Imported blogs end with related links, government sources and the
        // editorial disclaimer. Keep these navigation/trust elements at the end
        // rather than placing long-form content after the final disclaimer.
        foreach (['<h2>संबंधित लेख और योजनाएं</h2>','<h2>Related guides and schemes</h2>',
                  '<h2>आधिकारिक स्रोत</h2>','<h2>Official sources</h2>'] as $heading) {
            $pos=strpos($existing,$heading);
            if($pos!==false) return rtrim(substr($existing,0,$pos))."\n".$generated."\n".substr($existing,$pos);
        }
        return rtrim($existing)."\n".$generated;
    }
    $b=strpos($existing,$end,$a);
    if($b===false)throw new InvalidArgumentException('Existing long-form section has a broken end marker. Fix manually.');
    return rtrim(substr($existing,0,$a))."\n".$generated.substr($existing,$b+strlen($end));
}
function longform_patch_rows(array $items):array {
    $results=[];
    foreach($items as $item){
        $p=q_one("SELECT id,slug,status,type,title_hi,title_en,content_hi,content_en,image,seo_title,seo_title_en,seo_desc,seo_desc_en,updated_at FROM posts WHERE type='blog' AND slug=? LIMIT 1",[$item['slug']]);
        if(!$p||$p['status']!=='published')throw new InvalidArgumentException('Published blog not found: '.$item['slug']);
        if(trim((string)$p['content_hi'])===''||trim((string)$p['content_en'])==='')throw new InvalidArgumentException('Both published language versions required: '.$item['slug']);
        $results[$item['slug']]=$p;
    }
    return $results;
}
