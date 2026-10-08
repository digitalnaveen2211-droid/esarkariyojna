<?php
defined('ESY') or exit;
require __DIR__ . '/layout.php';
$S = settings();
$cats = categories_by_id();
$c = $cats[$y['category_id']] ?? ['slug' => '', 'name_hi' => '', 'name_en' => '', 'icon' => '📄', 'color' => '#eef2ff'];
if (lang() === 'en') {
    $title = ($y['seo_title_en'] ?? '') ?: tr($y['title_hi'], $y['title_en']) . ' | ' . $S['site_name'];
    $desc = ($y['seo_desc_en'] ?? '') ?: tr($y['summary_hi'], $y['summary_en']);
} else {
    $title = $y['seo_title'] ?: $y['title_hi'] . ' | ' . $S['site_name'];
    $desc = $y['seo_desc'] ?: $y['summary_hi'];
}
layout_start($title, $desc, '/yojna/' . $y['slug'], $y['image'] ?? '', has_english($y));
$related = $y['category_id'] ? array_slice(array_values(array_filter(schemes((int)$y['category_id']), fn($s) => $s['slug'] !== $y['slug'])), 0, 4) : [];
$officialHost = $y['official_url'] ? parse_url($y['official_url'], PHP_URL_HOST) : '';

// Body with an ad after the middle paragraph (only when the article is long enough).
$body = bi_html($y['content_hi'], $y['content_en']);
$mid = ad_slot('article_middle');
if ($mid !== '') {
    $parts = preg_split('#(</p>|</ul>|</ol>|</table>)#i', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
    $blocks = intdiv(count($parts), 2);
    if ($blocks >= 4) {
        $at = 2 * intdiv($blocks, 2) - 1; // index of the middle closing tag
        $parts[$at] .= $mid;
        $body = implode('', $parts);
    }
}
$crumbs = [['@type' => 'ListItem', 'position' => 1, 'name' => tr('होम', 'Home'), 'item' => rtrim($S['site_url'], '/') . lurl('/')]];
if ($c['slug']) $crumbs[] = ['@type' => 'ListItem', 'position' => 2, 'name' => tr($c['name_hi'], $c['name_en']), 'item' => rtrim($S['site_url'], '/') . lurl('/category/' . $c['slug'])];
$crumbs[] = ['@type' => 'ListItem', 'position' => count($crumbs) + 1, 'name' => tr($y['title_hi'], $y['title_en'])];
?>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        ['@type' => 'Article', 'headline' => mb_substr(tr($y['title_hi'], $y['title_en']), 0, 110), 'description' => $desc, 'inLanguage' => lang(),
         'dateModified' => date('c', strtotime($y['updated_at'])), 'datePublished' => date('c', strtotime($y['created_at'])),
         'mainEntityOfPage' => rtrim($S['site_url'], '/') . lurl('/yojna/' . $y['slug']),
         'publisher' => ['@type' => 'Organization', 'name' => $S['site_name']]],
        ['@type' => 'BreadcrumbList', 'itemListElement' => $crumbs],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<main class="wrap article-wrap">
  <nav class="crumbs"><a href="<?= lurl('/') ?>"><?= bi('होम', 'Home') ?></a> › <a href="<?= lurl('/category/' . e($c['slug'])) ?>"><?= bi($c['name_hi'], $c['name_en']) ?></a> › <span><?= bi($y['title_hi'], $y['title_en']) ?></span></nav>
  <div class="article-grid">
    <article class="article">
      <header class="article-head">
        <div class="ic big" style="background:<?= e(valid_color($c['color'], '#eef2ff')) ?>"><?= e($y['icon'] ?: $c['icon']) ?></div>
        <div>
          <span class="tag"><?= bi($c['name_hi'], $c['name_en']) ?></span>
          <h1><?= bi($y['title_hi'], $y['title_en']) ?></h1>
          <p class="lead"><?= bi($y['summary_hi'], $y['summary_en']) ?></p>
        </div>
      </header>
      <?php if (!empty($y['image'])): ?><img class="article-img" src="<?= e($y['image']) ?>" alt="<?= e(tr($y['title_hi'], $y['title_en'])) ?>"><?php endif; ?>
      <?= ad_slot('article_top') ?>
      <div class="note"><?= bi('यह एक सरकारी वेबसाइट नहीं है। आवेदन या भुगतान के लिए केवल आधिकारिक वेबसाइट का उपयोग करें।', 'This is not a government website. Use only the official website to apply or pay.') ?></div>
      <div class="prose"><?= $body ?></div>
      <?php if ($y['official_url']): ?>
      <div class="official">
        <strong><?= bi('आधिकारिक स्रोत और लिंक', 'Official source and link') ?></strong>
        <p><?= bi('अंतिम और सही जानकारी के लिए हमेशा आधिकारिक वेबसाइट देखें।', 'Always check the official website for final and accurate information.') ?></p>
        <a class="btn" href="<?= e(safe_url($y['official_url'])) ?>" target="_blank" rel="noopener noreferrer"><?= bi('आधिकारिक वेबसाइट देखें', 'Visit official website') ?>: <?= e($officialHost) ?> ↗</a>
      </div>
      <?php endif; ?>
      <?= ad_slot('article_bottom') ?>
      <p class="updated"><?= bi('अंतिम अपडेट', 'Last updated') ?>: <?= e(date('d M Y', strtotime($y['updated_at']))) ?></p>
    </article>
    <aside class="side">
      <div class="side-box">
        <h3><?= bi('संक्षेप में', 'At a glance') ?></h3>
        <dl>
          <dt><?= bi('श्रेणी', 'Category') ?></dt><dd><?= bi($c['name_hi'], $c['name_en']) ?></dd>
          <?php if ($y['elig_hi'] || $y['elig_en']): ?><dt><?= bi('पात्रता', 'Eligibility') ?></dt><dd><?= bi($y['elig_hi'], $y['elig_en']) ?></dd><?php endif; ?>
          <?php if ($officialHost): ?><dt><?= bi('आधिकारिक साइट', 'Official site') ?></dt><dd><a href="<?= e(safe_url($y['official_url'])) ?>" target="_blank" rel="noopener noreferrer"><?= e($officialHost) ?></a></dd><?php endif; ?>
        </dl>
      </div>
      <?= ad_slot('sidebar') ?>
      <?php if ($related): ?>
      <div class="side-box">
        <h3><?= bi('मिलती-जुलती योजनाएं', 'Related schemes') ?></h3>
        <ul class="rel"><?php foreach ($related as $r): ?><li><a href="<?= e(lurl('/yojna/' . $r['slug'])) ?>"><?= e($r['icon']) ?> <?= bi($r['title_hi'], $r['title_en']) ?></a></li><?php endforeach; ?></ul>
      </div>
      <?php endif; ?>
    </aside>
  </div>
</main>
<?php layout_end();
