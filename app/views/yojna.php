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
// Sidebar: schemes from the same category first, then other schemes, so it is never empty.
$others = array_values(array_filter(schemes(), fn($s) => $s['slug'] !== $y['slug']));
usort($others, fn($a, $b) => (int)($b['category_id'] == $y['category_id']) <=> (int)($a['category_id'] == $y['category_id']));
$related = array_slice($others, 0, 6);
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
          <?php if ($y['elig_hi'] || $y['elig_en']): ?><p class="elig"><b><?= bi('पात्रता', 'Eligibility') ?>:</b> <?= bi($y['elig_hi'], $y['elig_en']) ?></p><?php endif; ?>
        </div>
      </header>
      <?php if (!empty($y['image'])): ?><img class="article-img" src="<?= e($y['image']) ?>" alt="<?= e(tr($y['title_hi'], $y['title_en'])) ?>"><?php endif; ?>
      <?php
      $shareUrl = rtrim($S['site_url'], '/') . lurl('/yojna/' . $y['slug']);
      $shareTitle = tr($y['title_hi'], $y['title_en']);
      $shareLinks = [
          'whatsapp' => 'https://wa.me/?text=' . rawurlencode($shareTitle . ' - ' . $shareUrl),
          'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($shareUrl),
          'x'        => 'https://twitter.com/intent/tweet?url=' . rawurlencode($shareUrl) . '&text=' . rawurlencode($shareTitle),
          'telegram' => 'https://t.me/share/url?url=' . rawurlencode($shareUrl) . '&text=' . rawurlencode($shareTitle),
      ];
      ?>
      <div class="share-box">
        <strong><?= bi('यह योजना शेयर करें', 'Share this scheme') ?></strong>
        <div class="share-row">
          <?php foreach ($shareLinks as $k => $href): ?>
            <a class="soc-<?= $k ?>" href="<?= e($href) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($k)) ?>" title="<?= e(ucfirst($k)) ?>"><?= social_icon($k) ?></a>
          <?php endforeach; ?>
          <button type="button" class="soc-native" data-share-native data-title="<?= e($shareTitle) ?>" data-url="<?= e($shareUrl) ?>" hidden aria-label="<?= bi('शेयर करें', 'Share') ?>" title="<?= bi('शेयर करें', 'Share') ?>">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg>
          </button>
          <button type="button" class="soc-copy" id="shareCopyBtn" data-url="<?= e($shareUrl) ?>" data-copied-label="<?= bi('लिंक कॉपी हो गया', 'Link copied') ?>" aria-label="<?= bi('लिंक कॉपी करें', 'Copy link') ?>" title="<?= bi('लिंक कॉपी करें', 'Copy link') ?>">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
          </button>
        </div>
      </div>
      <?= ad_slot('article_top') ?>
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
      <?= ad_slot('sidebar') ?>
      <?php if ($related): ?>
      <div class="side-box">
        <h3><?= bi('संबंधित योजनाएं', 'Related schemes') ?></h3>
        <ul class="rel-cards">
          <?php foreach ($related as $r): $rc = $cats[$r['category_id']] ?? ['color' => '#eef2ff', 'icon' => '📄', 'name_hi' => '', 'name_en' => '']; ?>
          <li><a href="<?= e(lurl('/yojna/' . $r['slug'])) ?>">
            <span class="ic" style="background:<?= e(valid_color($rc['color'], '#eef2ff')) ?>"><?= e($r['icon'] ?: $rc['icon']) ?></span>
            <span><b><?= bi($r['title_hi'], $r['title_en']) ?></b><small><?= bi($rc['name_hi'], $rc['name_en']) ?></small></span>
          </a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      <div class="side-sticky"><?= ad_slot('sidebar_bottom') ?></div>
    </aside>
  </div>
</main>
<?php layout_end();
