<?php
defined('ESY') or exit;
require __DIR__ . '/layout.php';
$S = settings();
$cats = categories_by_id();
$list = schemes();
$active = isset($cat) ? $cat['slug'] : 'all';
$featured = array_values(array_filter($list, fn($s) => !empty($s['featured'])));
$title = isset($cat) ? $cat['name_hi'] . ' / ' . $cat['name_en'] . ' योजनाएं | ' . $S['site_name'] : $S['meta_title'];
layout_start($title, $S['meta_desc'], $active !== 'all' ? "/category/$active" : '/');
?>
<section class="hero"<?php if ($S['hero_image']): ?> style="--hero-img:url('<?= e($S['hero_image']) ?>')"<?php endif; ?>>
  <div class="wrap hero-in">
    <span class="pill"><?= bi($S['tagline_hi'], $S['tagline_en']) ?></span>
    <h1><?= bi($S['hero_title_hi'], $S['hero_title_en']) ?></h1>
    <p class="hero-sub"><?= bi($S['hero_sub_hi'], $S['hero_sub_en']) ?></p>
    <form class="search" id="searchForm" role="search" onsubmit="return false">
      <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M10 2a8 8 0 0 1 6.32 12.9l5.39 5.4-1.41 1.4-5.4-5.39A8 8 0 1 1 10 2zm0 2a6 6 0 1 0 0 12 6 6 0 0 0 0-12z"/></svg>
      <input id="q" type="search" autocomplete="off" aria-label="Search" data-ph-hi="कोई योजना, श्रेणी या कीवर्ड खोजें..." data-ph-en="Search scheme, category or keyword...">
      <button type="submit" class="btn"><?= bi('खोजें', 'Search') ?></button>
    </form>
    <p class="hint"><?= bi('हिंदी में लिखेंगे तो जानकारी हिंदी में, अंग्रेज़ी में लिखेंगे तो अंग्रेज़ी में दिखेगी।', 'Type in Hindi to see Hindi, or in English to see English.') ?></p>
    <?php if (!empty($S['show_stats'])): ?>
    <div class="stats">
      <div><b><?= count($list) ?>+</b><span><?= bi('योजनाएं', 'Schemes') ?></span></div>
      <div><b><?= count($cats) ?></b><span><?= bi('श्रेणियां', 'Categories') ?></span></div>
      <div><b>2</b><span><?= bi('भाषाएं', 'Languages') ?></span></div>
    </div>
    <?php endif; ?>
  </div>
</section>

<nav class="wrap cats" id="cats" aria-label="Categories">
  <button type="button" class="cat<?= $active === 'all' ? ' on' : '' ?>" data-cat="all"><span>✨</span><?= bi('सभी', 'All') ?></button>
  <?php foreach ($cats as $c): ?>
    <button type="button" class="cat<?= $active === $c['slug'] ? ' on' : '' ?>" data-cat="<?= e($c['slug']) ?>" style="--cbg:<?= e(valid_color($c['color'], '#eef2ff')) ?>"><span><?= e($c['icon']) ?></span><?= bi($c['name_hi'], $c['name_en']) ?></button>
  <?php endforeach; ?>
</nav>

<main class="wrap" id="yojna">
  <?php if ($featured && $active === 'all'): ?>
  <section class="featured">
    <div class="sec-head"><h2><?= bi('⭐ मुख्य योजनाएं', '⭐ Featured schemes') ?></h2></div>
    <div class="grid"><?php foreach ($featured as $s) echo scheme_card($s, $cats); ?></div>
  </section>
  <?php endif; ?>
  <div class="sec-head">
    <h2><?= bi('सभी योजनाएं', 'All schemes') ?></h2>
    <span class="count" id="count" data-hi="{n} योजनाएं मिलीं" data-en="{n} schemes found"></span>
  </div>
  <div class="grid" id="grid" data-active="<?= e($active) ?>"><?php foreach ($list as $s) echo scheme_card($s, $cats); ?></div>
  <div class="empty" id="empty" hidden>
    <div class="empty-ic">🔍</div>
    <h3><?= bi('कोई योजना नहीं मिली!', 'No scheme found!') ?></h3>
    <p><?= bi('आपके सर्च या फ़िल्टर से कोई योजना मेल नहीं खाई।', 'No scheme matches your search or filter.') ?></p>
    <button type="button" class="btn" id="showAll"><?= bi('सब दिखाएं', 'Show all') ?></button>
  </div>
</main>
<?php layout_end();
