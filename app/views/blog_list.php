<?php
defined('ESY') or exit;
require __DIR__ . '/layout.php';
$S = settings();
$bcat = $bcat ?? null;
$posts = blog_posts($bcat ? blog_cat_ids_with_children((int)$bcat['id']) : []);
$cats = array_column(blog_categories(), null, 'id');
$top = array_values(array_filter(blog_categories(), fn($c) => empty($c['parent_id'])));
$subs = $bcat ? array_values(array_filter(blog_categories(), fn($c) => (int)$c['parent_id'] === (int)$bcat['id'])) : [];
$parent = $bcat && $bcat['parent_id'] ? ($cats[$bcat['parent_id']] ?? null) : null;

if ($bcat) {
    $title = tr($bcat['name_hi'], $bcat['name_en']) . ' — ' . tr('ब्लॉग', 'Blog') . ' | ' . $S['site_name'];
    $desc = trim(strip_tags(tr($bcat['description_hi'], $bcat['description_en']))) ?: tr($bcat['name_hi'] . ' से जुड़े सभी लेख और ताज़ा जानकारी।', 'All articles and latest updates about ' . $bcat['name_en'] . '.');
} else {
    $title = tr('ब्लॉग: सरकारी योजनाओं की ताज़ा खबरें और गाइड', 'Blog: Government scheme news and guides') . ' | ' . $S['site_name'];
    $desc = tr('सरकारी योजनाओं से जुड़ी ताज़ा खबरें, अपडेट, आवेदन गाइड और उपयोगी लेख।', 'Latest news, updates, application guides and useful articles on government schemes.');
}
layout_start($title, $desc, $bcat ? '/blog/category/' . $bcat['slug'] : '/blog');
?>
<section class="hero hero-sm">
  <div class="wrap hero-in">
    <nav class="crumbs"><a href="<?= lurl('/') ?>"><?= bi('होम', 'Home') ?></a> › <a href="<?= lurl('/blog') ?>"><?= bi('ब्लॉग', 'Blog') ?></a><?php if ($parent): ?> › <a href="<?= e(lurl('/blog/category/' . $parent['slug'])) ?>"><?= bi($parent['name_hi'], $parent['name_en']) ?></a><?php endif; ?><?php if ($bcat): ?> › <span><?= bi($bcat['name_hi'], $bcat['name_en']) ?></span><?php endif; ?></nav>
    <h1><?= $bcat ? bi($bcat['name_hi'], $bcat['name_en']) : bi('ब्लॉग', 'Blog') ?></h1>
    <?php if ($bcat && trim(strip_tags(tr($bcat['description_hi'], $bcat['description_en'])))): ?><p class="hero-sub"><?= e(tr($bcat['description_hi'], $bcat['description_en'])) ?></p>
    <?php elseif (!$bcat): ?><p class="hero-sub"><?= bi('सरकारी योजनाओं की ताज़ा खबरें, अपडेट और आसान गाइड', 'Latest news, updates and easy guides on government schemes') ?></p><?php endif; ?>
    <form class="search" id="searchForm" role="search" onsubmit="return false">
      <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M10 2a8 8 0 0 1 6.32 12.9l5.39 5.4-1.41 1.4-5.4-5.39A8 8 0 1 1 10 2zm0 2a6 6 0 1 0 0 12 6 6 0 0 0 0-12z"/></svg>
      <input id="q" type="search" autocomplete="off" aria-label="<?= e(tr('लेख खोजें', 'Search articles')) ?>" placeholder="<?= e(tr('लेख खोजें...', 'Search articles...')) ?>">
      <button type="submit" class="btn"><?= bi('खोजें', 'Search') ?></button>
    </form>
  </div>
</section>

<?php if ($top): ?>
<nav class="wrap bcats" aria-label="<?= e(tr('ब्लॉग श्रेणियां', 'Blog categories')) ?>">
  <a class="bcat<?= !$bcat ? ' on' : '' ?>" href="<?= lurl('/blog') ?>"><?= bi('सभी', 'All') ?></a>
  <?php foreach ($top as $c): $on = $bcat && ((int)$bcat['id'] === (int)$c['id'] || (int)$bcat['parent_id'] === (int)$c['id']); ?>
    <a class="bcat<?= $on ? ' on' : '' ?>" href="<?= e(lurl('/blog/category/' . $c['slug'])) ?>"><?= bi($c['name_hi'], $c['name_en']) ?></a>
  <?php endforeach; ?>
</nav>
<?php if ($subs): ?>
<nav class="wrap bcats sub" aria-label="<?= e(tr('उप-श्रेणियां', 'Sub categories')) ?>">
  <?php foreach ($subs as $c): ?><a class="bcat" href="<?= e(lurl('/blog/category/' . $c['slug'])) ?>">↳ <?= bi($c['name_hi'], $c['name_en']) ?></a><?php endforeach; ?>
</nav>
<?php endif; ?>
<?php endif; ?>

<main class="wrap" id="blog">
  <div class="sec-head">
    <h2><?= $bcat ? bi($bcat['name_hi'] . ' के लेख', $bcat['name_en'] . ' articles') : bi('सभी लेख', 'All articles') ?></h2>
    <span class="count" id="count" data-hi="{n} लेख" data-en="{n} articles"></span>
  </div>
  <div class="grid blog-grid" id="grid" data-active="all" data-page="12" data-step="8" data-nourl="1">
    <?php foreach ($posts as $bp) echo blog_card($bp, $cats); ?>
  </div>
  <div class="empty" id="empty" hidden>
    <div class="empty-ic">📝</div>
    <h3><?= bi('अभी कोई लेख नहीं है', 'No articles yet') ?></h3>
    <p><?= bi('जल्द ही नए लेख आएंगे।', 'New articles are coming soon.') ?></p>
    <a class="btn" href="<?= lurl('/blog') ?>"><?= bi('सभी लेख देखें', 'See all articles') ?></a>
  </div>
  <div class="more-row"><button type="button" class="btn more-btn" id="loadMore" hidden><?= bi('और लेख देखें (Load more)', 'Load more') ?></button></div>
</main>
<?php layout_end();
