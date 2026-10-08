<?php
defined('ESY') or exit;
require __DIR__ . '/layout.php';
$S = settings();
$cats = categories_by_id();
$list = schemes();
$active = isset($cat) ? $cat['slug'] : 'all';
$featured = array_slice(array_values(array_filter($list, fn($s) => !empty($s['featured']))), 0, max(0, (int)($S['featured_count'] ?? 3)));
if (isset($cat)) {
    $title = tr($cat['name_hi'] . ' से जुड़ी सरकारी योजनाएं', $cat['name_en'] . ' Government Schemes') . ' | ' . $S['site_name'];
    $desc = tr($cat['name_hi'] . ' श्रेणी की सभी सरकारी योजनाओं की सरल जानकारी: पात्रता, लाभ, दस्तावेज़ और आवेदन प्रक्रिया।',
               'Simple guides to all ' . $cat['name_en'] . ' government schemes: eligibility, benefits, documents and how to apply.');
} else {
    $title = tr($S['meta_title'], $S['meta_title_en']);
    $desc = tr($S['meta_desc'], $S['meta_desc_en']);
}
layout_start($title, $desc, $active !== 'all' ? "/category/$active" : '/', $S['hero_image']);
$seo = $active === 'all' ? tr($S['home_content_hi'], $S['home_content_en']) : '';
?>
<section class="hero"<?php if ($S['hero_image']): ?> style="--hero-img:url('<?= e($S['hero_image']) ?>')"<?php endif; ?>>
  <div class="wrap hero-in">
    <span class="pill"><?= bi($S['tagline_hi'], $S['tagline_en']) ?></span>
    <h1><?= isset($cat) ? e(tr($cat['name_hi'] . ' योजनाएं', $cat['name_en'] . ' Schemes')) : bi($S['hero_title_hi'], $S['hero_title_en']) ?></h1>
    <p class="hero-sub"><?= bi($S['hero_sub_hi'], $S['hero_sub_en']) ?></p>
    <form class="search" id="searchForm" role="search" onsubmit="return false">
      <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M10 2a8 8 0 0 1 6.32 12.9l5.39 5.4-1.41 1.4-5.4-5.39A8 8 0 1 1 10 2zm0 2a6 6 0 1 0 0 12 6 6 0 0 0 0-12z"/></svg>
      <input id="q" type="search" autocomplete="off" aria-label="<?= e(tr('योजना खोजें', 'Search schemes')) ?>" placeholder="<?= e(tr('कोई योजना, श्रेणी या कीवर्ड खोजें...', 'Search scheme, category or keyword...')) ?>">
      <button type="submit" class="btn"><?= bi('खोजें', 'Search') ?></button>
    </form>
    <?php if (!empty($S['show_stats'])): ?>
    <div class="stats">
      <div><b><?= count($list) ?>+</b><span><?= bi('योजनाएं', 'Schemes') ?></span></div>
      <div><b><?= count($cats) ?></b><span><?= bi('श्रेणियां', 'Categories') ?></span></div>
      <div><b>2</b><span><?= bi('भाषाएं', 'Languages') ?></span></div>
    </div>
    <?php endif; ?>
  </div>
</section>

<nav class="wrap cats" id="cats" aria-label="<?= e(tr('श्रेणियां', 'Categories')) ?>">
  <button type="button" class="cat<?= $active === 'all' ? ' on' : '' ?>" data-cat="all"><span>✨</span><?= bi('सभी', 'All') ?></button>
  <?php foreach ($cats as $c): ?>
    <button type="button" class="cat<?= $active === $c['slug'] ? ' on' : '' ?>" data-cat="<?= e($c['slug']) ?>" style="--cbg:<?= e(valid_color($c['color'], '#eef2ff')) ?>"><span><?= e($c['icon']) ?></span><?= bi($c['name_hi'], $c['name_en']) ?></button>
  <?php endforeach; ?>
</nav>

<main class="wrap" id="yojna">
  <?= ad_slot('home_top') ?>
  <?php if ($featured && $active === 'all'): ?>
  <section class="featured">
    <div class="sec-head"><h2><?= bi('⭐ मुख्य योजनाएं', '⭐ Featured schemes') ?></h2></div>
    <div class="grid"><?php foreach ($featured as $s) echo scheme_card($s, $cats); ?></div>
  </section>
  <?= ad_slot('home_after_featured') ?>
  <?php endif; ?>
  <div class="sec-head">
    <h2><?= bi('सभी योजनाएं', 'All schemes') ?></h2>
    <span class="count" id="count" data-hi="{n} योजनाएं मिलीं" data-en="{n} schemes found"></span>
  </div>
  <div class="grid" id="grid" data-active="<?= e($active) ?>">
    <?php foreach ($list as $i => $s) {
        echo scheme_card($s, $cats);
        if (($i + 1) % 6 === 0 && $i + 1 < count($list)) echo ad_slot('home_in_list');
    } ?>
  </div>
  <div class="empty" id="empty" hidden>
    <div class="empty-ic">🔍</div>
    <h3><?= bi('कोई योजना नहीं मिली!', 'No scheme found!') ?></h3>
    <p><?= bi('आपके सर्च या फ़िल्टर से कोई योजना मेल नहीं खाई।', 'No scheme matches your search or filter.') ?></p>
    <button type="button" class="btn" id="showAll"><?= bi('सब दिखाएं', 'Show all') ?></button>
  </div>
  <?= ad_slot('home_after_list') ?>
  <?php if (trim(strip_tags($seo)) !== ''): ?>
  <section class="home-seo prose" aria-label="<?= e(tr('सरकारी योजनाओं के बारे में', 'About government schemes')) ?>">
    <?= $seo ?>
  </section>
  <?php endif; ?>
</main>
<?php layout_end();
