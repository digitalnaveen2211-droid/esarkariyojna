<?php
defined('ESY') or exit;
require __DIR__ . '/layout.php';
$S = settings();
$cats = categories_by_id();
$c = $cats[$y['category_id']] ?? ['slug' => '', 'name_hi' => '', 'name_en' => '', 'icon' => '📄', 'color' => '#eef2ff'];
$title = ($y['seo_title'] ?: $y['title_hi'] . ' | ' . $S['site_name']);
$desc = $y['seo_desc'] ?: $y['summary_hi'];
layout_start($title, $desc, '/yojna/' . $y['slug'], $y['image'] ?? '');
$related = $y['category_id'] ? array_slice(array_values(array_filter(schemes((int)$y['category_id']), fn($s) => $s['slug'] !== $y['slug'])), 0, 4) : [];
$officialHost = $y['official_url'] ? parse_url($y['official_url'], PHP_URL_HOST) : '';
?>
<main class="wrap article-wrap">
  <nav class="crumbs"><a href="/"><?= bi('होम', 'Home') ?></a> › <a href="/category/<?= e($c['slug']) ?>"><?= bi($c['name_hi'], $c['name_en']) ?></a> › <span><?= bi($y['title_hi'], $y['title_en']) ?></span></nav>
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
      <?php if (!empty($y['image'])): ?><img class="article-img" src="<?= e($y['image']) ?>" alt="<?= e($y['title_hi']) ?>"><?php endif; ?>
      <div class="note"><?= bi('यह एक सरकारी वेबसाइट नहीं है। आवेदन या भुगतान के लिए केवल आधिकारिक वेबसाइट का उपयोग करें।', 'This is not a government website. Use only the official website to apply or pay.') ?></div>
      <div class="prose"><?= bi_html($y['content_hi'], $y['content_en']) ?></div>
      <?php if ($y['official_url']): ?>
      <div class="official">
        <strong><?= bi('आधिकारिक स्रोत और लिंक', 'Official source and link') ?></strong>
        <p><?= bi('अंतिम और सही जानकारी के लिए हमेशा आधिकारिक वेबसाइट देखें।', 'Always check the official website for final and accurate information.') ?></p>
        <a class="btn" href="<?= e(safe_url($y['official_url'])) ?>" target="_blank" rel="noopener noreferrer"><?= bi('आधिकारिक वेबसाइट देखें', 'Visit official website') ?>: <?= e($officialHost) ?> ↗</a>
      </div>
      <?php endif; ?>
      <p class="updated"><?= bi('अंतिम अपडेट', 'Last updated') ?>: <?= e(date('d M Y', strtotime($y['updated_at']))) ?></p>
    </article>
    <aside class="side">
      <div class="side-box">
        <h3><?= bi('संक्षेप में', 'At a glance') ?></h3>
        <dl>
          <dt><?= bi('श्रेणी', 'Category') ?></dt><dd><?= bi($c['name_hi'], $c['name_en']) ?></dd>
          <?php if ($y['elig_hi']): ?><dt><?= bi('पात्रता', 'Eligibility') ?></dt><dd><?= bi($y['elig_hi'], $y['elig_en']) ?></dd><?php endif; ?>
          <?php if ($officialHost): ?><dt><?= bi('आधिकारिक साइट', 'Official site') ?></dt><dd><a href="<?= e(safe_url($y['official_url'])) ?>" target="_blank" rel="noopener noreferrer"><?= e($officialHost) ?></a></dd><?php endif; ?>
        </dl>
      </div>
      <?php if ($related): ?>
      <div class="side-box">
        <h3><?= bi('मिलती-जुलती योजनाएं', 'Related schemes') ?></h3>
        <ul class="rel"><?php foreach ($related as $r): ?><li><a href="/yojna/<?= e($r['slug']) ?>"><?= e($r['icon']) ?> <?= bi($r['title_hi'], $r['title_en']) ?></a></li><?php endforeach; ?></ul>
      </div>
      <?php endif; ?>
    </aside>
  </div>
</main>
<?php layout_end();
