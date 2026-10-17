<?php
defined('ESY') or exit;
require __DIR__ . '/layout.php';
$S = settings();
$cats = categories_by_id();
$list = schemes();
$active = isset($cat) ? $cat['slug'] : 'all';
$featured = array_slice(array_values(array_filter($list, fn($s) => !empty($s['featured']))), 0, max(0, (int)($S['featured_count'] ?? 3)));
if (!empty($allPage)) {
    $title = tr('सभी सरकारी योजनाएं', 'All Government Schemes') . ' | ' . $S['site_name'];
    $desc = tr('सभी सरकारी योजनाओं की सूची: पात्रता, लाभ, दस्तावेज़ और आवेदन की सरल जानकारी।', 'List of all government schemes with eligibility, benefits, documents and how to apply.');
} elseif (isset($cat)) {
    $title = tr($cat['name_hi'] . ' से जुड़ी सरकारी योजनाएं', $cat['name_en'] . ' Government Schemes') . ' | ' . $S['site_name'];
    $desc = tr($cat['name_hi'] . ' श्रेणी की सभी सरकारी योजनाओं की सरल जानकारी: पात्रता, लाभ, दस्तावेज़ और आवेदन प्रक्रिया।',
               'Simple guides to all ' . $cat['name_en'] . ' government schemes: eligibility, benefits, documents and how to apply.');
} else {
    // Homepage SEO metadata belongs to CMS > Site Settings > SEO.
    // Use defaults only when a saved field is blank.
    $title = trim(tr($S['meta_title'], $S['meta_title_en']));
    if ($title === '') $title = tr('सरकारी योजनाएं 2026: पात्रता, लाभ और आवेदन की जानकारी', 'Government Schemes 2026: Eligibility, Benefits & Application');
    $desc = trim(tr($S['meta_desc'], $S['meta_desc_en']));
    if ($desc === '') $desc = tr(
        'पीएम किसान, आयुष्मान भारत, सुकन्या समृद्धि, मुद्रा और अन्य सरकारी योजनाओं की पात्रता, लाभ, दस्तावेज़ व आवेदन प्रक्रिया हिंदी में जानें।',
        'Explore government schemes in India: PM Kisan, Ayushman Bharat, Sukanya Samriddhi, Mudra and more. Check eligibility, benefits and application guidance.'
    );
}
layout_start($title, $desc, !empty($allPage) ? '/yojnayein' : ($active !== 'all' ? "/category/$active" : '/'), $S['hero_image']);
$seo = $active === 'all' && empty($allPage) ? tr($S['home_content_hi'], $S['home_content_en']) : '';

if (!empty($allPage)) $featured = [];
$isHomepage = $active === 'all' && empty($allPage);
?>
<div class="<?= $isHomepage ? 'home-redesign' : 'home-listing' ?>">
<section class="hero"<?php if ($S['hero_image']): ?> style="--hero-img:url('<?= e($S['hero_image']) ?>')"<?php endif; ?>>
  <div class="wrap hero-in">
    <div class="hero-copy">
    <span class="pill"><?= bi($S['tagline_hi'], $S['tagline_en']) ?></span>
    <h1><?= !empty($allPage) ? bi('सभी योजनाएं', 'All Schemes') : (isset($cat) ? e(tr($cat['name_hi'] . ' योजनाएं', $cat['name_en'] . ' Schemes')) : e(tr($S['hero_title_hi'], $S['hero_title_en']))) ?></h1>
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
    <?php if ($isHomepage): ?>
    <aside class="hero-guide" aria-label="<?= e(tr('योजना जानकारी कैसे खोजें', 'How to explore schemes')) ?>">
      <div class="hero-guide-top">
        <span class="hero-guide-symbol" aria-hidden="true">✦</span>
        <span class="hero-guide-eyebrow"><?= bi('आपकी सहायता के लिए', 'Your scheme guide') ?></span>
        <h2><?= bi('सही योजना तक, आसान रास्ता', 'A simpler way to find schemes') ?></h2>
        <p><?= bi('योजना की जानकारी तीन आसान चरणों में समझें।', 'Understand scheme information in three simple steps.') ?></p>
      </div>
      <ol class="hero-guide-steps">
        <li><span class="hero-step-icon" aria-hidden="true">⌕</span><div><strong><?= bi('योजना खोजें', 'Find a scheme') ?></strong><small><?= bi('अपनी जरूरत के अनुसार खोजें', 'Search for what you need') ?></small></div><span class="hero-step-num">01</span></li>
        <li><span class="hero-step-icon" aria-hidden="true">✓</span><div><strong><?= bi('पात्रता और लाभ जानें', 'Check eligibility and benefits') ?></strong><small><?= bi('दस्तावेज़ और प्रक्रिया समझें', 'Review documents and process') ?></small></div><span class="hero-step-num">02</span></li>
        <li><span class="hero-step-icon" aria-hidden="true">↗</span><div><strong><?= bi('आधिकारिक स्रोत जांचें', 'Verify official information') ?></strong><small><?= bi('अंतिम नियम सरकारी पोर्टल पर देखें', 'Confirm the latest rules on official sites') ?></small></div><span class="hero-step-num">03</span></li>
      </ol>
      <div class="hero-guide-foot"><span aria-hidden="true">ⓘ</span><span><?= bi('स्वतंत्र सूचनात्मक वेबसाइट, सरकारी पोर्टल नहीं', 'Independent information site, not a government portal') ?></span></div>
    </aside>
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
    <div class="sec-head"><h2><?= bi('⭐ मुख्य योजनाएं', '⭐ Featured schemes') ?></h2>
      <?php if ($isHomepage): ?><a class="section-all-link" href="<?= e(lurl('/yojnayein')) ?>"><?= bi('सभी योजनाएं देखें →', 'Explore all schemes →') ?></a><?php endif; ?>
    </div>
    <div class="grid"><?php foreach ($featured as $s) echo scheme_card($s, $cats); ?></div>
  </section>
  <?= ad_slot('home_after_featured') ?>
  <?php endif; ?>
  <div class="sec-head">
    <h2><?= bi('सभी योजनाएं', 'All schemes') ?></h2>
    <span class="count" id="count" data-hi="{n} योजनाएं मिलीं" data-en="{n} schemes found"></span>
  </div>
  <div class="grid" id="grid" data-active="<?= e($active) ?>"<?= empty($allPage) && $active === 'all' ? ' data-limit="6"' : '' ?><?= !empty($allPage) ? ' data-page="12" data-step="8" data-nourl="1"' : '' ?>>
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
  <div class="more-row">
    <?php if (empty($allPage) && $active === 'all'): ?>
      <a class="btn more-btn" id="viewMore" href="<?= lurl('/yojnayein') ?>" hidden><?= bi('सभी योजनाएं देखें →', 'View all schemes →') ?></a>
    <?php endif; ?>
    <?php if (!empty($allPage)): ?>
      <button type="button" class="btn more-btn" id="loadMore" hidden><?= bi('और योजनाएं देखें (Load more)', 'Load more') ?></button>
    <?php endif; ?>
  </div>
  <?= ad_slot('home_after_list') ?>
  <?php if (trim(strip_tags($seo)) !== ''): ?>
  <section class="home-seo prose" aria-label="<?= e(tr('सरकारी योजनाओं के बारे में', 'About government schemes')) ?>">
    <?= $seo ?>
  </section>
  <?php endif; ?>
  <?php if ($active === 'all' && empty($allPage)): ?>
    <?php
      // Use the same published blog source/order as the blog listing,
      // but fetch only the latest three posts for the homepage.
      $latestBlogs = q_all("SELECT * FROM posts WHERE type = 'blog' AND status = 'published' ORDER BY created_at DESC, id DESC LIMIT 3");
    ?>
    <?php if ($latestBlogs): ?>
    <section class="home-latest-blogs" aria-labelledby="home-latest-blogs-title">
      <div class="sec-head home-blogs-head">
        <h2 id="home-latest-blogs-title"><?= bi('नवीनतम ब्लॉग', 'Latest blog posts') ?></h2>
        <a class="home-blogs-all" href="<?= e(lurl('/blog')) ?>"><?= bi('सभी ब्लॉग देखें →', 'View all blogs →') ?></a>
      </div>
      <div class="grid blog-grid">
        <?php $blogCats = array_column(blog_categories(), null, 'id'); ?>
        <?php foreach ($latestBlogs as $blog) echo blog_card($blog, $blogCats); ?>
      </div>
    </section>
    <?php endif; ?>
  <?php endif; ?>
</main>
</div>
<?php layout_end();
