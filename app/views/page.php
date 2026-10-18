<?php
defined('ESY') or exit;
require __DIR__ . '/layout.php';
$S = settings();
$title = lang() === 'en'
    ? (($p['seo_title_en'] ?? '') ?: tr($p['title_hi'], $p['title_en']) . ' | ' . $S['site_name'])
    : ($p['seo_title'] ?: $p['title_hi'] . ' | ' . $S['site_name']);
$desc = lang() === 'en' ? (($p['seo_desc_en'] ?? '') ?: $S['meta_desc_en']) : ($p['seo_desc'] ?: $S['meta_desc']);
layout_start($title, $desc, '/page/' . $p['slug'], $p['image'] ?? '', has_english($p));
?>
<main class="wrap article-wrap narrow">
  <nav class="crumbs"><a href="<?= lurl('/') ?>"><?= bi('होम', 'Home') ?></a> › <span><?= bi($p['title_hi'], $p['title_en']) ?></span></nav>
  <article class="article">
    <h1><?= bi($p['title_hi'], $p['title_en']) ?></h1>
    <?php if (!empty($p['image'])): ?><img class="article-img" src="<?= e($p['image']) ?>" alt="<?= e(tr($p['title_hi'], $p['title_en'])) ?>"><?php endif; ?>
    <div class="prose"><?= bi_html($p['content_hi'], $p['content_en']) ?></div>
    <nav class="page-related" aria-label="<?= e(tr('महत्वपूर्ण वेबसाइट पेज', 'Important website pages')) ?>">
      <h2><?= bi('वेबसाइट के बारे में और जानें', 'More about this website') ?></h2>
      <ul>
        <?php foreach (['about' => ['हमारे बारे में', 'About us'], 'privacy-policy' => ['गोपनीयता नीति', 'Privacy policy'], 'disclaimer' => ['अस्वीकरण', 'Disclaimer']] as $relatedSlug => $labels): ?>
          <?php if ($relatedSlug !== $p['slug'] && find_post('page', $relatedSlug)): ?>
            <li><a href="<?= e(lurl('/page/' . $relatedSlug)) ?>"><?= e(tr($labels[0], $labels[1])) ?> →</a></li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ul>
    </nav>
  </article>
</main>
<?php layout_end();
