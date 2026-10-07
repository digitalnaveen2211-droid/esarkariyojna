<?php
defined('ESY') or exit;
require __DIR__ . '/layout.php';
$S = settings();
layout_start($p['title_hi'] . ' | ' . $S['site_name'], $p['seo_desc'] ?: $S['meta_desc'], '/page/' . $p['slug']);
?>
<main class="wrap article-wrap narrow">
  <nav class="crumbs"><a href="/"><?= bi('होम', 'Home') ?></a> › <span><?= bi($p['title_hi'], $p['title_en']) ?></span></nav>
  <article class="article">
    <h1><?= bi($p['title_hi'], $p['title_en']) ?></h1>
    <div class="prose"><?= bi_html($p['content_hi'], $p['content_en']) ?></div>
  </article>
</main>
<?php layout_end();
