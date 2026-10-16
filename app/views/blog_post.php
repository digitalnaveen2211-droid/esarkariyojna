<?php
defined('ESY') or exit;
require __DIR__ . '/layout.php';
$S = settings();
$cats = array_column(blog_categories(), null, 'id');
$bc = $b['blog_cat_id'] ? ($cats[$b['blog_cat_id']] ?? null) : null;
$parent = $bc && $bc['parent_id'] ? ($cats[$bc['parent_id']] ?? null) : null;
if (lang() === 'en') {
    $title = ($b['seo_title_en'] ?? '') ?: tr($b['title_hi'], $b['title_en']) . ' | ' . $S['site_name'];
    $desc = ($b['seo_desc_en'] ?? '') ?: tr($b['summary_hi'], $b['summary_en']);
} else {
    $title = $b['seo_title'] ?: $b['title_hi'] . ' | ' . $S['site_name'];
    $desc = $b['seo_desc'] ?: $b['summary_hi'];
}
if (trim($desc) === '') $desc = mb_substr(seo_plain_text(tr($b['content_hi'], $b['content_en'])), 0, 155);
layout_start($title, $desc, '/blog/' . $b['slug'], $b['image'] ?? '', has_english($b));

$related = array_slice(array_values(array_filter(blog_posts($bc ? blog_cat_ids_with_children((int)($parent['id'] ?? $bc['id'])) : []), fn($p) => $p['id'] !== $b['id'])), 0, 5);
if (count($related) < 3) {
    $ids = array_column($related, 'id');
    foreach (blog_posts() as $p) { if (count($related) >= 5) break; if ($p['id'] !== $b['id'] && !in_array($p['id'], $ids, true)) $related[] = $p; }
}
$author = $b['author_id'] ? q_val('SELECT name FROM users WHERE id = ?', [$b['author_id']]) : null;
$words = count(preg_split('/\s+/u', trim(seo_plain_text(tr($b['content_hi'], $b['content_en']))))) ?: 1;
$base = rtrim($S['site_url'], '/');
$crumbs = [['@type' => 'ListItem', 'position' => 1, 'name' => tr('होम', 'Home'), 'item' => $base . lurl('/')],
           ['@type' => 'ListItem', 'position' => 2, 'name' => tr('ब्लॉग', 'Blog'), 'item' => $base . lurl('/blog')]];
if ($bc) $crumbs[] = ['@type' => 'ListItem', 'position' => 3, 'name' => tr($bc['name_hi'], $bc['name_en']), 'item' => $base . lurl('/blog/category/' . $bc['slug'])];
$crumbs[] = ['@type' => 'ListItem', 'position' => count($crumbs) + 1, 'name' => tr($b['title_hi'], $b['title_en'])];
?>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        array_filter(['@type' => 'BlogPosting', 'headline' => mb_substr(tr($b['title_hi'], $b['title_en']), 0, 110), 'description' => $desc, 'inLanguage' => lang(),
         'image' => $b['image'] ? $base . $b['image'] : null,
         'datePublished' => date('c', strtotime($b['created_at'])), 'dateModified' => date('c', strtotime($b['updated_at'])),
         'author' => $author ? ['@type' => 'Person', 'name' => $author] : ['@type' => 'Organization', 'name' => $S['site_name']],
         'mainEntityOfPage' => $base . lurl('/blog/' . $b['slug']),
         'publisher' => ['@type' => 'Organization', 'name' => $S['site_name']]]),
        ['@type' => 'BreadcrumbList', 'itemListElement' => $crumbs],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<main class="wrap article-wrap">
  <nav class="crumbs"><a href="<?= lurl('/') ?>"><?= bi('होम', 'Home') ?></a> › <a href="<?= lurl('/blog') ?>"><?= bi('ब्लॉग', 'Blog') ?></a><?php if ($parent): ?> › <a href="<?= e(lurl('/blog/category/' . $parent['slug'])) ?>"><?= bi($parent['name_hi'], $parent['name_en']) ?></a><?php endif; ?><?php if ($bc): ?> › <a href="<?= e(lurl('/blog/category/' . $bc['slug'])) ?>"><?= bi($bc['name_hi'], $bc['name_en']) ?></a><?php endif; ?></nav>
  <div class="article-grid">
    <article class="article">
      <?php if ($bc): ?><a class="tag" href="<?= e(lurl('/blog/category/' . $bc['slug'])) ?>"><?= bi($bc['name_hi'], $bc['name_en']) ?></a><?php endif; ?>
      <h1><?= bi($b['title_hi'], $b['title_en']) ?></h1>
      <p class="post-meta">
        <?php if ($author): ?><span>✍️ <?= e($author) ?></span><?php endif; ?>
        <span>📅 <?= e(date('d M Y', strtotime($b['created_at']))) ?></span>
        <span>⏱️ <?= max(1, (int)round($words / 200)) ?> <?= bi('मिनट पढ़ें', 'min read') ?></span>
      </p>
      <?php if (trim($b['summary_hi'] . $b['summary_en']) !== ''): ?><p class="lead"><?= bi($b['summary_hi'], $b['summary_en']) ?></p><?php endif; ?>
      <?php if (!empty($b['image'])): ?><img class="article-img" src="<?= e($b['image']) ?>" alt="<?= e(tr($b['title_hi'], $b['title_en'])) ?>"><?php endif; ?>
      <?= share_box(lurl('/blog/' . $b['slug']), tr($b['title_hi'], $b['title_en']), tr('यह लेख शेयर करें', 'Share this article')) ?>
      <?= ad_slot('article_top') ?>
      <div class="prose"><?= bi_html($b['content_hi'], $b['content_en']) ?></div>
      <?= ad_slot('article_bottom') ?>
      <p class="updated"><?= bi('अंतिम अपडेट', 'Last updated') ?>: <?= e(date('d M Y', strtotime($b['updated_at']))) ?></p>
    </article>
    <aside class="side">
      <?= ad_slot('sidebar') ?>
      <?php if ($related): ?>
      <div class="side-box">
        <h3><?= bi('और पढ़ें', 'Read more') ?></h3>
        <ul class="rel-posts">
          <?php foreach ($related as $r): ?>
          <li><a href="<?= e(lurl('/blog/' . $r['slug'])) ?>">
            <?php if ($r['image']): ?><img src="<?= e($r['image']) ?>" alt="" loading="lazy"><?php endif; ?>
            <span><b><?= bi($r['title_hi'], $r['title_en']) ?></b><small><?= e(date('d M Y', strtotime($r['created_at']))) ?></small></span>
          </a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      <?php $tree = blog_cat_tree(); if ($tree): ?>
      <div class="side-box">
        <h3><?= bi('श्रेणियां', 'Categories') ?></h3>
        <ul class="cat-tree">
          <?php foreach ($tree as $c): ?><li style="--d:<?= (int)$c['depth'] ?>"><a href="<?= e(lurl('/blog/category/' . $c['slug'])) ?>"><?= $c['depth'] ? '↳ ' : '' ?><?= bi($c['name_hi'], $c['name_en']) ?></a></li><?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      <div class="side-sticky"><?= ad_slot('sidebar_bottom') ?></div>
    </aside>
  </div>
</main>
<?php layout_end();
