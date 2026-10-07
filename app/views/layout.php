<?php
defined('ESY') or exit;

function layout_start(string $title, string $desc, string $canonical = '', string $image = ''): void {
    $S = settings();
    $p = valid_color($S['color_primary'], '#4338ca');
    $a = valid_color($S['color_accent'], '#f97316');
    $lang = $S['default_lang'] === 'en' ? 'en' : 'hi';
    $url = rtrim($S['site_url'], '/') . $canonical;
    ?><!DOCTYPE html>
<html lang="<?= $lang ?>" data-lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="canonical" href="<?= e($url) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($url) ?>">
<?php if ($image): ?><meta property="og:image" content="<?= e(rtrim($S['site_url'], '/') . $image) ?>"><?php endif; ?>
<link rel="icon" href="<?= e($S['favicon']) ?>">
<meta name="theme-color" content="<?= $p ?>">
<script>try{var l=localStorage.getItem('esy_lang');if(l==='hi'||l==='en'){document.documentElement.dataset.lang=l;document.documentElement.lang=l;}}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css?v=<?= @filemtime(ROOT . '/assets/style.css') ?>">
<style>:root{--p:<?= $p ?>;--a:<?= $a ?>}</style>
<?php if (preg_match('/^GTM-[A-Z0-9]+$/', $S['gtm_id'])): ?>
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?= $S['gtm_id'] ?>');</script>
<?php endif; ?>
<?php if (preg_match('/^G-[A-Z0-9]+$/', $S['ga4_id'])): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= $S['ga4_id'] ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= $S['ga4_id'] ?>');</script>
<?php endif; ?>
<?= $S['head_code'] ?>
</head>
<body>
<?php if (preg_match('/^GTM-[A-Z0-9]+$/', $S['gtm_id'])): ?>
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= $S['gtm_id'] ?>" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<?php endif; ?>
<?= $S['body_code'] ?>
<?php if (!empty($S['notice_on'])): ?>
<div class="notice-bar"><span><?= bi($S['notice_hi'], $S['notice_en']) ?></span>
  <button type="button" data-open-modal><?= bi('पूरी घोषणा पढ़ें', 'Read full declaration') ?></button></div>
<?php endif; ?>
<header class="site-head">
  <div class="wrap head-in">
    <a class="brand" href="/" aria-label="<?= e($S['site_name']) ?>">
      <?php if ($S['logo']): ?><img src="<?= e($S['logo']) ?>" alt="<?= e($S['site_name']) ?>" height="44"><?php else: ?><strong><?= e($S['site_name']) ?></strong><?php endif; ?>
    </a>
    <nav class="main-nav" id="mainNav" aria-label="Main">
      <?php foreach (menu('header') as $it): ?>
        <a href="<?= e(safe_url($it['url'])) ?>"<?= $it['new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>><?= bi($it['label_hi'], $it['label_en']) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="head-tools">
      <div class="lang" role="group" aria-label="Language">
        <button type="button" data-lang-btn="hi">हिं</button><button type="button" data-lang-btn="en">EN</button>
      </div>
      <button class="nav-toggle" type="button" aria-label="Menu" aria-controls="mainNav" aria-expanded="false"><span></span><span></span><span></span></button>
    </div>
  </div>
</header>
<?php
}

function layout_end(): void {
    $S = settings();
    $socials = ['facebook' => 'Facebook', 'youtube' => 'YouTube', 'instagram' => 'Instagram', 'x' => 'X', 'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram'];
    ?>
<footer class="site-foot">
  <div class="wrap foot-grid">
    <div>
      <?php if ($S['logo']): ?><img class="foot-logo" src="<?= e($S['logo']) ?>" alt="<?= e($S['site_name']) ?>" height="40"><?php endif; ?>
      <p class="foot-tag"><?= bi($S['tagline_hi'], $S['tagline_en']) ?></p>
      <p><?= bi($S['footer_about_hi'], $S['footer_about_en']) ?></p>
    </div>
    <div>
      <h4><?= bi('ज़रूरी लिंक', 'Quick links') ?></h4>
      <ul class="foot-links">
        <?php foreach (menu('footer') as $it): ?><li><a href="<?= e(safe_url($it['url'])) ?>"<?= $it['new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>><?= bi($it['label_hi'], $it['label_en']) ?></a></li><?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h4><?= bi('श्रेणियां', 'Categories') ?></h4>
      <ul class="foot-links">
        <?php foreach (array_slice(categories(), 0, 8) as $c): ?><li><a href="/category/<?= e($c['slug']) ?>"><?= e($c['icon']) ?> <?= bi($c['name_hi'], $c['name_en']) ?></a></li><?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h4><?= bi('जुड़ें', 'Connect') ?></h4>
      <div class="socials">
        <?php foreach ($socials as $k => $label): if (!empty($S['social_' . $k])): ?>
          <a href="<?= e(safe_url($S['social_' . $k])) ?>" target="_blank" rel="noopener"><?= e($label) ?></a>
        <?php endif; endforeach; ?>
      </div>
      <?php if ($S['contact_email']): ?><p><a href="mailto:<?= e($S['contact_email']) ?>"><?= e($S['contact_email']) ?></a></p><?php endif; ?>
    </div>
  </div>
  <div class="wrap foot-bottom">
    <small><?= bi($S['footer_disc_hi'], $S['footer_disc_en']) ?></small>
    <small><?= e(str_replace('{year}', date('Y'), $S['copyright'])) ?></small>
  </div>
</footer>
<div class="modal" id="declModal" role="dialog" aria-modal="true" aria-labelledby="declTitle" hidden>
  <div class="modal-box">
    <h3 id="declTitle"><?= bi('घोषणा', 'Declaration') ?></h3>
    <?= bi_html($S['declaration_hi'], $S['declaration_en']) ?>
    <button type="button" class="btn" data-close-modal><?= bi('समझ गया, बंद करें', 'Understood, close') ?></button>
  </div>
</div>
<script src="/assets/app.js?v=<?= @filemtime(ROOT . '/assets/app.js') ?>"></script>
<?= $S['footer_code'] ?>
</body>
</html>
<?php
}

function scheme_card(array $s, array $cats): string {
    $c = $cats[$s['category_id']] ?? ['slug' => '', 'name_hi' => '', 'name_en' => '', 'color' => '#eef2ff', 'icon' => '📄'];
    $hasPage = trim(strip_tags($s['content_hi'] ?? '')) !== '' || trim(strip_tags($s['content_en'] ?? '')) !== '';
    $search = mb_strtolower(implode(' ', [$s['title_hi'], $s['title_en'], $s['summary_hi'], $s['summary_en'], $s['keywords'], $c['name_hi'], $c['name_en']]));
    ob_start(); ?>
<article class="card" data-cat="<?= e($c['slug']) ?>" data-search="<?= e($search) ?>">
  <?php if (!empty($s['image'])): ?><img class="card-img" src="<?= e($s['image']) ?>" alt="" loading="lazy"><?php endif; ?>
  <div class="card-top">
    <div class="ic" style="background:<?= e(valid_color($c['color'], '#eef2ff')) ?>"><?= e($s['icon'] ?: $c['icon']) ?></div>
    <span class="tag"><?= bi($c['name_hi'], $c['name_en']) ?></span>
  </div>
  <h3><?= bi($s['title_hi'], $s['title_en']) ?></h3>
  <p><?= bi($s['summary_hi'], $s['summary_en']) ?></p>
  <?php if ($s['elig_hi'] || $s['elig_en']): ?><div class="meta"><b><?= bi('पात्रता', 'Eligibility') ?>:</b> <?= bi($s['elig_hi'], $s['elig_en']) ?></div><?php endif; ?>
  <?php if ($hasPage): ?>
    <a class="btn" href="/yojna/<?= e($s['slug']) ?>"><?= bi('पूरी जानकारी देखें →', 'View full details →') ?></a>
  <?php else: ?>
    <span class="btn off" aria-disabled="true"><?= bi('जल्दी आ रही है', 'Coming soon') ?></span>
  <?php endif; ?>
</article>
<?php return ob_get_clean();
}
