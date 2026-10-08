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
  </article>
</main>
<?php layout_end();
