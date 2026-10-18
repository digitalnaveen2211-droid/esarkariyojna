<?php
defined('ESY') or exit;
require __DIR__ . '/layout.php';
$S = settings();
$categories = categories();
$cats = categories_by_id();
$states = scheme_states();
$allSchemes = schemes(); // Published records only.
$activeCategory = isset($cat) ? (string)$cat['slug'] : 'all';
$isHomepage = $activeCategory === 'all' && empty($allPage);
$isDirectory = !empty($allPage);
$inCategory = isset($cat);
$centralSchemes = array_values(array_filter($allSchemes, static fn($s) => ($s['gov_level'] ?? 'central') !== 'state'));
$stateSchemes = array_values(array_filter($allSchemes, static fn($s) => ($s['gov_level'] ?? 'central') === 'state'));
$latestSchemes = $allSchemes;
usort($latestSchemes, static fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
$popularSchemes = $allSchemes;
usort($popularSchemes, static fn($a, $b) => ((int)($b['featured'] ?? 0) <=> (int)($a['featured'] ?? 0)) ?: ((int)$a['sort'] <=> (int)$b['sort']));
$blogLatest = $isHomepage ? q_all("SELECT * FROM posts WHERE type='blog' AND status='published' ORDER BY created_at DESC, id DESC LIMIT 3") : [];
$catCounts = []; $stateCounts = [];
foreach ($allSchemes as $scheme) {
    $cid = (int)($scheme['category_id'] ?? 0);
    $catCounts[$cid] = ($catCounts[$cid] ?? 0) + 1;
    if (($scheme['gov_level'] ?? 'central') === 'state' && ($scheme['state_code'] ?? '') !== '') {
        $key = $scheme['state_code'];
        $stateCounts[$key] = ($stateCounts[$key] ?? 0) + 1;
    }
}
if ($isDirectory) {
    $title = tr('सभी सरकारी योजनाएं: केंद्र व राज्य सरकार', 'All Central and State Government Schemes') . ' | ' . $S['site_name'];
    $desc = tr('केंद्र और राज्य सरकार की सभी प्रकाशित योजनाएं खोजें। पात्रता, लाभ, दस्तावेज और आवेदन की जानकारी देखें।',
        'Find all published Central and State schemes with eligibility, benefits, documents and application information.');
} elseif ($inCategory) {
    $title = trim(tr($cat['seo_title_hi'] ?? '', $cat['seo_title_en'] ?? '')) ?: tr($cat['name_hi'] . ' योजनाएं', $cat['name_en'] . ' Schemes');
    $desc = trim(tr($cat['seo_desc_hi'] ?? '', $cat['seo_desc_en'] ?? '')) ?: tr($cat['name_hi'] . ' योजनाओं की जानकारी और आवेदन प्रक्रिया।', 'Eligibility and application guidance for ' . $cat['name_en'] . ' schemes.');
} else {
    $title = trim(tr($S['meta_title'] ?? '', $S['meta_title_en'] ?? '')) ?: tr('भारत की सरकारी योजनाएं: पात्रता, लाभ और आवेदन', 'Government Schemes in India: Eligibility, Benefits and Apply');
    $desc = trim(tr($S['meta_desc'] ?? '', $S['meta_desc_en'] ?? '')) ?: tr('केंद्र और राज्य सरकार की योजनाओं की पूरी जानकारी।', 'Independent information about Central and State government schemes in India.');
}
layout_start($title, $desc, $isDirectory ? '/yojnayein' : ($inCategory ? '/category/' . $activeCategory : '/'), $S['hero_image'] ?? '');
$homeSeo = $isHomepage ? tr($S['home_content_hi'] ?? '', $S['home_content_en'] ?? '') : '';

if (!function_exists('esy_home_card')) {
    function esy_home_card(array $scheme, array $cats, array $states, string $modifier = ''): void {
        // The first image card is often visible early; do not defer its download.
        static $renderedCards = 0;
        $eagerImage = $renderedCards++ === 0;
        $id = (int)($scheme['category_id'] ?? 0);
        $category = $cats[$id] ?? ['slug'=>'','name_hi'=>'','name_en'=>'','icon'=>'📋','color'=>'#eef2ff'];
        $level = ($scheme['gov_level'] ?? 'central') === 'state' ? 'state' : 'central';
        $stateCode = $level === 'state' ? (string)($scheme['state_code'] ?? '') : '';
        $stateLabel = $stateCode !== '' && isset($states[$stateCode]) ? $states[$stateCode] : ['', ''];
        $image = trim((string)($scheme['image'] ?? ''));
        $summary = trim((string)tr($scheme['summary_hi'] ?? '', $scheme['summary_en'] ?? ''));
        $search = mb_strtolower(implode(' ', [$scheme['title_hi'] ?? '', $scheme['title_en'] ?? '', $scheme['summary_hi'] ?? '', $scheme['summary_en'] ?? '', $category['name_hi'], $category['name_en'], $stateLabel[0], $stateLabel[1]]), 'UTF-8');
        $label = $level === 'central' ? tr('केंद्र सरकार', 'Central') : tr(($stateLabel[0] ?: 'राज्य सरकार'), ($stateLabel[1] ?: 'State'));
        ?>
        <article class="esy-scheme-card <?= e($modifier) ?> card"
            data-cat="<?= e($category['slug'] ?? '') ?>"
            data-gov="<?= e($level) ?>"
            data-state="<?= e($stateCode) ?>"
            data-search="<?= e($search) ?>">
          <a class="esy-card-photo<?= $image ? '' : ' esy-no-photo' ?>" href="<?= e(lurl('/yojna/' . $scheme['slug'])) ?>" aria-label="<?= e(tr($scheme['title_hi'], $scheme['title_en'])) ?>">
            <?php if ($image): ?><img src="<?= e(esy_card_thumb($image)) ?>" alt="<?= e(tr($scheme['title_hi'], $scheme['title_en'])) ?>" loading="<?= $eagerImage ? 'eager' : 'lazy' ?>"<?= $eagerImage ? ' fetchpriority="high"' : '' ?> decoding="async" width="640" height="360"><?php else: ?>
              <span class="esy-photo-symbol" aria-hidden="true"><?= e($scheme['icon'] ?: $category['icon'] ?: '📋') ?></span>
            <?php endif; ?>
            <span class="esy-card-scope <?= $level ?>"><?= $level === 'central' ? '🏛' : '📍' ?> <?= e($label) ?></span>
          </a>
          <div class="esy-card-inner">
            <h3><a href="<?= e(lurl('/yojna/' . $scheme['slug'])) ?>"><?= e(tr($scheme['title_hi'], $scheme['title_en'])) ?></a></h3>
            <p><?= e(mb_strimwidth($summary, 0, 130, '…', 'UTF-8')) ?></p>
            <div class="esy-card-meta"><span><?= e($category['icon'] ?: '📋') ?> <?= e(tr($category['name_hi'], $category['name_en'])) ?></span></div>
            <a class="esy-card-cta" href="<?= e(lurl('/yojna/' . $scheme['slug'])) ?>"><?= bi('विवरण देखें', 'View details') ?> <span aria-hidden="true">→</span></a>
          </div>
        </article>
        <?php
    }
}
?>
<div class="esy-portal <?= $isHomepage ? 'esy-portal-home' : 'esy-portal-directory' ?>">
  <section class="esy-portal-hero esy-portal-hero-classic">
    <div class="wrap esy-hero-layout">
      <div class="esy-hero-content">
        <span class="esy-eyebrow"><?= e(tr($S['tagline_hi'] ?? 'आपकी योजना, आपकी पहचान', $S['tagline_en'] ?? 'Your scheme, your future')) ?></span>
        <h1><?= $isDirectory ? bi('सभी सरकारी योजनाएं', 'Explore All Government Schemes') :
            ($inCategory ? e(tr($cat['name_hi'] . ' योजनाएं', $cat['name_en'] . ' Schemes')) :
            e(tr($S['hero_title_hi'] ?? 'सरकारी योजनाओं की सही जानकारी', $S['hero_title_en'] ?? 'Government Schemes Explained'))) ?></h1>
        <p><?= $inCategory ? e(tr($cat['name_hi'] . ' श्रेणी में प्रकाशित योजनाएं खोजें।', 'Explore published schemes in this category.')) :
             e(tr($S['hero_sub_hi'] ?? 'केंद्र और राज्य की योजनाओं की पात्रता, लाभ और आवेदन की जानकारी।', $S['hero_sub_en'] ?? 'Eligibility, benefits and application information for government schemes.')) ?></p>
        <form class="esy-search" id="searchForm" role="search" onsubmit="return false">
          <span aria-hidden="true">⌕</span>
          <input id="q" type="search" autocomplete="off" aria-label="<?= e(tr('योजना का नाम खोजें', 'Search schemes')) ?>" placeholder="<?= e(tr('किसी सरकारी योजना का नाम लिखें...', 'Enter a scheme name...')) ?>">
          <button type="submit"><?= bi('खोजें', 'Search') ?></button>
        </form>
        <div class="esy-hero-stats" aria-label="<?= e(tr('पोर्टल आंकड़े', 'Portal figures')) ?>">
          <div><b><?= count($allSchemes) ?></b><span><?= bi('प्रकाशित योजनाएं', 'Published schemes') ?></span></div>
          <div><b><?= count($centralSchemes) ?></b><span><?= bi('केंद्र सरकार', 'Central schemes') ?></span></div>
          <div><b><?= count($stateSchemes) ?></b><span><?= bi('राज्य सरकार', 'State schemes') ?></span></div>
          <div><b><?= count($categories) ?></b><span><?= bi('श्रेणियां', 'Categories') ?></span></div>
        </div>
      </div>
      <div class="esy-hero-category-panel" aria-label="<?= e(tr('प्रमुख योजना श्रेणियां', 'Key scheme categories')) ?>">
        <?php foreach (array_slice($categories, 0, 6) as $category): ?>
          <a class="esy-hero-category" href="<?= e(lurl('/category/' . $category['slug'])) ?>"
             style="--tile-bg:<?= e(valid_color($category['color'] ?? '', '#eef4ff')) ?>">
            <span class="esy-hero-category-icon" aria-hidden="true"><?= e($category['icon'] ?: '📋') ?></span>
            <strong><?= e(tr($category['name_hi'], $category['name_en'])) ?></strong>
          </a>
        <?php endforeach; ?>
        <?php if (count($categories) < 6): ?>
          <a class="esy-hero-category" href="<?= e(lurl('/yojnayein')) ?>">
            <span class="esy-hero-category-icon" aria-hidden="true">▦</span>
            <strong><?= bi('सभी योजनाएं', 'All schemes') ?></strong>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <div class="wrap esy-content">
    <?php if ($isHomepage): ?>
    <section class="esy-topic-section" aria-label="<?= e(tr('योजना श्रेणियां', 'Scheme categories')) ?>">
      <div class="esy-section-heading"><div><span class="esy-kicker"><?= bi('अपनी जरूरत चुनें', 'Explore by need') ?></span><h2><?= bi('श्रेणी के अनुसार योजनाएं', 'Browse by category') ?></h2></div><a href="<?= e(lurl('/yojnayein')) ?>"><?= bi('सभी योजनाएं', 'All schemes') ?> →</a></div>
      <div class="esy-rail-controls" aria-label="<?= e(tr('श्रेणियां स्क्रॉल करें', 'Scroll categories')) ?>">
        <button type="button" data-rail-target="esy-category-rail" data-rail-direction="-1" aria-label="<?= e(tr('पिछली श्रेणियां', 'Previous categories')) ?>">‹</button>
        <button type="button" data-rail-target="esy-category-rail" data-rail-direction="1" aria-label="<?= e(tr('अगली श्रेणियां', 'Next categories')) ?>">›</button>
      </div>
      <div class="esy-topic-grid esy-horizontal-rail" id="esy-category-rail" tabindex="0" aria-label="<?= e(tr('सभी योजना श्रेणियां — क्षैतिज सूची', 'All scheme categories — horizontal list')) ?>">
        <a class="esy-topic" href="<?= e(lurl('/yojnayein')) ?>"><span>▦</span><b><?= bi('सभी योजनाएं', 'All schemes') ?></b><small><?= count($allSchemes) ?> <?= bi('योजनाएं', 'schemes') ?></small></a>
        <?php foreach ($categories as $category): ?>
          <a class="esy-topic" href="<?= e(lurl('/category/' . $category['slug'])) ?>"><span><?= e($category['icon'] ?: '📋') ?></span><b><?= e(tr($category['name_hi'], $category['name_en'])) ?></b><small><?= (int)($catCounts[(int)$category['id']] ?? 0) ?> <?= bi('योजनाएं', 'schemes') ?></small></a>
        <?php endforeach; ?>
      </div>
    </section>

    <?php if ($latestSchemes): ?>
    <section class="esy-section" id="latest">
      <div class="esy-section-heading"><div><span class="esy-kicker">✦ <?= bi('नई जानकारी', 'New information') ?></span><h2><?= bi('नवीनतम योजनाएं', 'Latest schemes') ?></h2></div><a href="<?= e(lurl('/yojnayein')) ?>"><?= bi('सभी देखें', 'View all') ?> →</a></div>
      <div class="esy-scheme-grid"><?php foreach (array_slice($latestSchemes, 0, 4) as $scheme) esy_home_card($scheme, $cats, $states); ?></div>
    </section>
    <?php endif; ?>

    <?php $featuredHome = array_values(array_filter($popularSchemes, static fn($scheme) => !empty($scheme['featured']))); ?>
    <?php if ($featuredHome): ?>
    <section class="esy-section esy-popular">
      <div class="esy-section-heading"><div><span class="esy-kicker">★ <?= bi('लोकप्रिय', 'Popular') ?></span><h2><?= bi('मुख्य सरकारी योजनाएं', 'Featured government schemes') ?></h2></div><a href="<?= e(lurl('/yojnayein')) ?>"><?= bi('सभी योजनाएं', 'All schemes') ?> →</a></div>
      <div class="esy-scheme-grid"><?php foreach (array_slice($featuredHome, 0, 4) as $scheme) esy_home_card($scheme, $cats, $states); ?></div>
    </section>
    <?php endif; ?>

    <section class="esy-section">
      <div class="esy-section-heading"><div><span class="esy-kicker">📍 <?= bi('भारत के सभी राज्य और केंद्रशासित प्रदेश', 'States and union territories') ?></span><h2><?= bi('राज्य के अनुसार योजनाएं', 'Find schemes by state') ?></h2></div><a href="<?= e(lurl('/yojnayein')) ?>"><?= bi('पूरी सूची देखें', 'Browse directory') ?> →</a></div>
      <div class="esy-rail-controls" aria-label="<?= e(tr('राज्यों को स्क्रॉल करें', 'Scroll states')) ?>">
        <button type="button" data-rail-target="esy-state-scroll" data-rail-direction="-1" aria-label="<?= e(tr('पिछले राज्य', 'Previous states')) ?>">‹</button>
        <button type="button" data-rail-target="esy-state-scroll" data-rail-direction="1" aria-label="<?= e(tr('अगले राज्य', 'Next states')) ?>">›</button>
      </div>
      <div class="esy-state-rail esy-horizontal-rail" id="esy-state-scroll" tabindex="0" aria-label="<?= e(tr('राज्यों की क्षैतिज सूची', 'Horizontal list of states')) ?>">
        <?php foreach ($states as $code => $names): if (empty($stateCounts[$code])) continue; ?>
        <a class="esy-state-pill" href="<?= e(lurl('/yojnayein')) ?>?state=<?= e($code) ?>"><span>📍</span><b><?= e(lang() === 'en' ? $names[1] : $names[0]) ?></b><small><?= (int)$stateCounts[$code] ?> <?= bi('योजनाएं', 'schemes') ?></small></a>
        <?php endforeach; ?>
        <?php if (!$stateCounts): ?><span class="esy-muted"><?= bi('राज्यवार योजनाएं जल्द यहां दिखाई देंगी।', 'State schemes will appear as they are added.') ?></span><?php endif; ?>
      </div>
    </section>

    <div class="esy-two-up">
      <section class="esy-section esy-level-panel"><div class="esy-section-heading"><h2>🏛 <?= bi('केंद्र सरकार की योजनाएं', 'Central Government schemes') ?></h2><a href="<?= e(lurl('/yojnayein')) ?>?gov=central"><?= bi('सभी देखें', 'View all') ?> →</a></div>
        <div class="esy-mini-list"><?php foreach (array_slice($centralSchemes, 0, 7) as $scheme): ?><a href="<?= e(lurl('/yojna/' . $scheme['slug'])) ?>"><span class="esy-mini-icon"><?= e($scheme['icon'] ?: '🏛') ?></span><b><?= e(tr($scheme['title_hi'], $scheme['title_en'])) ?></b><span>→</span></a><?php endforeach; ?></div>
      </section>
      <section class="esy-section esy-level-panel"><div class="esy-section-heading"><h2>📍 <?= bi('राज्य सरकार की योजनाएं', 'State Government schemes') ?></h2><a href="<?= e(lurl('/yojnayein')) ?>?gov=state"><?= bi('सभी देखें', 'View all') ?> →</a></div>
        <div class="esy-mini-list"><?php foreach (array_slice($stateSchemes, 0, 7) as $scheme): ?><a href="<?= e(lurl('/yojna/' . $scheme['slug'])) ?>"><span class="esy-mini-icon"><?= e($scheme['icon'] ?: '📍') ?></span><b><?= e(tr($scheme['title_hi'], $scheme['title_en'])) ?></b><span>→</span></a><?php endforeach; ?>
        <?php if (!$stateSchemes): ?><p><?= bi('जल्द ही और राज्य योजनाएं जोड़ेंगे।', 'More state schemes will be added.') ?></p><?php endif; ?></div>
      </section>
    </div>
    <?php endif; ?>

    <section class="esy-section esy-directory-section" id="yojna">
      <div class="esy-section-heading"><div><span class="esy-kicker"><?= bi('विस्तृत सूची', 'Full directory') ?></span><h2><?= $inCategory ? e(tr($cat['name_hi'], $cat['name_en'])) : bi('सभी प्रकाशित योजनाएं', 'All published schemes') ?></h2></div>
        <span class="esy-found" id="count" data-hi="{n} योजनाएं मिलीं" data-en="{n} schemes found"></span></div>
      <div class="esy-filters" aria-label="<?= e(tr('योजना फ़िल्टर', 'Scheme filters')) ?>">
        <div class="esy-level-tabs" role="group" aria-label="<?= e(tr('सरकार का स्तर', 'Government level')) ?>">
          <button type="button" data-level="all" class="on"><?= bi('सभी', 'All') ?></button>
          <button type="button" data-level="central"><?= bi('केंद्र सरकार', 'Central') ?></button>
          <button type="button" data-level="state"><?= bi('राज्य सरकार', 'State') ?></button>
        </div>
        <label class="esy-filter-label"><?= bi('राज्य चुनें', 'Select state') ?>
          <select id="stateFilter"><option value=""><?= bi('सभी राज्य', 'All states') ?></option>
          <?php foreach ($states as $code => $names): ?><option value="<?= e($code) ?>"><?= e(lang() === 'en' ? $names[1] : $names[0]) ?></option><?php endforeach; ?>
          </select></label>
        <label class="esy-filter-label"><?= bi('श्रेणी', 'Category') ?>
          <select id="categoryFilter">
            <option value="all"><?= bi('सभी श्रेणियां', 'All categories') ?></option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= e($category['slug']) ?>"<?= $activeCategory === $category['slug'] ? ' selected' : '' ?>><?= e(tr($category['name_hi'], $category['name_en'])) ?></option>
            <?php endforeach; ?>
          </select></label>
      </div>
      <div class="esy-scheme-grid" id="grid" data-active="<?= e($activeCategory) ?>" data-page="<?= $isHomepage ? '18' : '24' ?>" data-step="12" data-nourl="1">
        <?php foreach ($allSchemes as $scheme): if ($inCategory && (int)$scheme['category_id'] !== (int)$cat['id']) continue; esy_home_card($scheme, $cats, $states); endforeach; ?>
      </div>
      <div class="empty" id="empty" hidden><h3><?= bi('कोई योजना नहीं मिली', 'No schemes found') ?></h3><p><?= bi('दूसरा फ़िल्टर या नाम चुनकर देखें।', 'Try changing your filters or search term.') ?></p><button type="button" id="showAll" class="esy-reset"><?= bi('फ़िल्टर हटाएं', 'Reset filters') ?></button></div>
      <div class="esy-more-row"><button type="button" id="loadMore" class="esy-more-btn" hidden><?= bi('और योजनाएं देखें', 'Load more schemes') ?> ↓</button></div>
    </section>

    <?= ad_slot('home_after_list') ?>

    <?php if ($inCategory && trim(tr($cat['content_hi'] ?? '', $cat['content_en'] ?? '')) !== ''): ?>
    <section class="esy-section home-seo prose"><h2><?= bi('इस श्रेणी के बारे में', 'About this category') ?></h2>
      <?php foreach (preg_split('/\R\s*\R/u', trim(tr($cat['content_hi'] ?? '', $cat['content_en'] ?? ''))) as $paragraph): if (trim($paragraph) !== ''): ?><p><?= e(trim($paragraph)) ?></p><?php endif; endforeach; ?>
    </section>
    <?php endif; ?>
    <?php if ($blogLatest): ?>
    <section class="esy-section"><div class="esy-section-heading"><h2><?= bi('नवीनतम ब्लॉग और गाइड', 'Latest blogs and guides') ?></h2><a href="<?= e(lurl('/blog')) ?>"><?= bi('सभी ब्लॉग', 'All blogs') ?> →</a></div>
    <div class="grid blog-grid"><?php $blogCategories = array_column(blog_categories(), null, 'id'); foreach ($blogLatest as $blog) echo blog_card($blog, $blogCategories); ?></div></section>
    <?php endif; ?>
    <?php if (trim(strip_tags($homeSeo)) !== ''): ?><section class="esy-section home-seo prose"><?= $homeSeo ?></section><?php endif; ?>
  </div>
</div>
<?php layout_end(); ?>
