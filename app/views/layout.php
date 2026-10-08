<?php
defined('ESY') or exit;

/**
 * $path is the language-neutral path ('/', '/yojna/pm-kisan' ...). The page URL is /hi$path or /en$path.
 * $hasEn = false means this page has no real English text yet: the /en/ copy is kept out of Google (noindex).
 */
function layout_start(string $title, string $desc, string $path = '/', string $image = '', bool $hasEn = true, bool $noindex = false): void {
    $S = settings();
    $p = valid_color($S['color_primary'], '#4338ca');
    $a = valid_color($S['color_accent'], '#f97316');
    $l = lang();
    $base = rtrim($S['site_url'], '/');
    $url = $base . lurl($path);
    $hiUrl = $base . lurl($path, 'hi');
    $enUrl = $base . lurl($path, 'en');
    $enCopy = $l === 'en' && !$hasEn;
    ?><!DOCTYPE html>
<html lang="<?= $l ?>" data-lang="<?= $l ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<?php if ($noindex): ?>
<meta name="robots" content="noindex, follow">
<?php elseif ($enCopy): ?>
<meta name="robots" content="noindex, follow">
<link rel="canonical" href="<?= e($hiUrl) ?>">
<?php else: ?>
<link rel="canonical" href="<?= e($url) ?>">
<link rel="alternate" hreflang="hi" href="<?= e($hiUrl) ?>">
<?php if ($hasEn): ?><link rel="alternate" hreflang="en" href="<?= e($enUrl) ?>"><?php endif; ?>
<link rel="alternate" hreflang="x-default" href="<?= e($hiUrl) ?>">
<?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:locale" content="<?= $l === 'en' ? 'en_IN' : 'hi_IN' ?>">
<meta property="og:site_name" content="<?= e($S['site_name']) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($url) ?>">
<?php if ($image): ?><meta property="og:image" content="<?= e(preg_match('#^https?://#', $image) ? $image : $base . $image) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="<?= e($S['favicon']) ?>">
<meta name="theme-color" content="<?= $p ?>">
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
<header class="site-head">
  <div class="wrap head-in">
    <a class="brand" href="<?= lurl('/') ?>" aria-label="<?= e($S['site_name']) ?>">
      <?php if ($S['logo']): ?><img src="<?= e($S['logo']) ?>" alt="<?= e($S['site_name']) ?>" height="44"><?php else: ?><strong><?= e($S['site_name']) ?></strong><?php endif; ?>
    </a>
    <nav class="main-nav" id="mainNav" aria-label="Main">
      <?php foreach (menu('header') as $it): ?>
        <a href="<?= e(lurl(safe_url($it['url']))) ?>"<?= $it['new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>><?= bi($it['label_hi'], $it['label_en']) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="head-tools">
      <div class="lang" role="group" aria-label="Language">
        <a href="<?= e(lurl($path, 'hi')) ?>" hreflang="hi" lang="hi"<?= $l === 'hi' ? ' class="on" aria-current="true"' : '' ?>>हिं</a><a href="<?= e(lurl($path, 'en')) ?>" hreflang="en" lang="en"<?= $l === 'en' ? ' class="on" aria-current="true"' : '' ?>>EN</a>
      </div>
      <button class="nav-toggle" type="button" aria-label="Menu" aria-controls="mainNav" aria-expanded="false"><span></span><span></span><span></span></button>
    </div>
  </div>
</header>
<?= ad_slot('header') ?>
<?php
}

function social_icon(string $k): string {
    $paths = [
        'facebook'  => '<path d="M14 8.5V6.6c0-.8.2-1.3 1.4-1.3H17V2.2C16.7 2.1 15.7 2 14.6 2 12.2 2 10.6 3.4 10.6 6.1v2.4H8v3.3h2.6V22H14v-10.2h2.7l.4-3.3H14z"/>',
        'instagram' => '<path d="M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm0 8.2a3.2 3.2 0 1 1 0-6.4 3.2 3.2 0 0 1 0 6.4zM17.3 5.5a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4zM21.9 7.9c-.1-1.6-.4-3-1.6-4.2S17.7 2.2 16.1 2.1C14.5 2 9.5 2 7.9 2.1 6.3 2.2 4.9 2.5 3.7 3.7S2.2 6.3 2.1 7.9C2 9.5 2 14.5 2.1 16.1c.1 1.6.4 3 1.6 4.2s2.6 1.5 4.2 1.6c1.6.1 6.6.1 8.2 0 1.6-.1 3-.4 4.2-1.6s1.5-2.6 1.6-4.2c.1-1.6.1-6.6 0-8.2zm-2.1 10a3.3 3.3 0 0 1-1.9 1.9c-1.3.5-4.4.4-5.9.4s-4.6.1-5.9-.4a3.3 3.3 0 0 1-1.9-1.9c-.5-1.3-.4-4.4-.4-5.9s-.1-4.6.4-5.9a3.3 3.3 0 0 1 1.9-1.9c1.3-.5 4.4-.4 5.9-.4s4.6-.1 5.9.4a3.3 3.3 0 0 1 1.9 1.9c.5 1.3.4 4.4.4 5.9s.1 4.6-.4 5.9z"/>',
        'x'         => '<path d="M17.8 2.5h3.1l-6.8 7.8 8 10.6h-6.3l-4.9-6.4-5.6 6.4H2.2l7.3-8.3L1.9 2.5h6.4l4.4 5.9 5.1-5.9zm-1.1 16.5h1.7L7.4 4.3H5.6L16.7 19z"/>',
        'youtube'   => '<path d="M23 7.2a3 3 0 0 0-2.1-2.1C19 4.6 12 4.6 12 4.6s-7 0-8.9.5A3 3 0 0 0 1 7.2 31 31 0 0 0 .5 12a31 31 0 0 0 .5 4.8 3 3 0 0 0 2.1 2.1c1.9.5 8.9.5 8.9.5s7 0 8.9-.5a3 3 0 0 0 2.1-2.1 31 31 0 0 0 .5-4.8 31 31 0 0 0-.5-4.8zM9.7 15V9l5.8 3-5.8 3z"/>',
        'whatsapp'  => '<path d="M17.5 14.4c-.3-.1-1.8-.9-2-1-.3-.1-.5-.1-.7.1-.2.3-.8 1-.9 1.2-.2.2-.3.2-.6.1-.3-.1-1.3-.5-2.4-1.5-.9-.8-1.5-1.8-1.7-2.1-.2-.3 0-.5.1-.6l.4-.5.3-.5v-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.1.2 2.1 3.2 5.1 4.5 2.5 1 3 .8 3.6.8.6-.1 1.8-.7 2-1.4.2-.7.2-1.3.2-1.4-.1-.2-.3-.3-.6-.4zM12 21.8a9.9 9.9 0 0 1-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4A9.8 9.8 0 1 1 12 21.8zM20.5 3.5A11.8 11.8 0 0 0 .2 11.9c0 2.1.5 4.1 1.6 5.9L.1 24l6.3-1.7a11.9 11.9 0 0 0 5.7 1.4c6.5 0 11.9-5.3 11.9-11.9 0-3.2-1.2-6.2-3.5-8.3z"/>',
        'telegram'  => '<path d="M21.9 4.3 18.7 19.4c-.2 1-.9 1.3-1.7.8l-4.8-3.5-2.3 2.2c-.3.3-.5.5-1 .5l.3-4.9 9-8.1c.4-.3-.1-.5-.6-.2L6.4 13.2 1.6 11.7c-1-.3-1.1-1 .2-1.5l18.8-7.2c.9-.3 1.6.2 1.3 1.3z"/>',
    ];
    return '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">' . ($paths[$k] ?? '') . '</svg>';
}

function layout_end(): void {
    $S = settings();
    $socials = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X', 'youtube' => 'YouTube', 'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram'];
    ?>
<?= ad_slot('footer_top') ?>
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
        <?php foreach (menu('footer') as $it): ?><li><a href="<?= e(lurl(safe_url($it['url']))) ?>"<?= $it['new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>><?= bi($it['label_hi'], $it['label_en']) ?></a></li><?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h4><?= bi('श्रेणियां', 'Categories') ?></h4>
      <ul class="foot-links">
        <?php foreach (array_slice(categories(), 0, 8) as $c): ?><li><a href="<?= lurl('/category/' . e($c['slug'])) ?>"><?= e($c['icon']) ?> <?= bi($c['name_hi'], $c['name_en']) ?></a></li><?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h4><?= bi('जुड़ें', 'Connect') ?></h4>
      <div class="socials">
        <?php foreach ($socials as $k => $label): if (!empty($S['social_' . $k])): ?>
          <a class="soc soc-<?= $k ?>" href="<?= e(safe_url($S['social_' . $k])) ?>" target="_blank" rel="noopener" aria-label="<?= e($label) ?>" title="<?= e($label) ?>"><?= social_icon($k) ?></a>
        <?php endif; endforeach; ?>
      </div>
      <?php if ($S['contact_email']): ?><p><a href="mailto:<?= e($S['contact_email']) ?>"><?= e($S['contact_email']) ?></a></p><?php endif; ?>
    </div>
  </div>
  <div class="wrap foot-bottom">
    <?php if (!empty($S['notice_on'])): ?>
      <p class="foot-notice"><?= bi($S['notice_hi'], $S['notice_en']) ?> <button type="button" data-open-modal><?= bi('पूरी घोषणा पढ़ें', 'Read full declaration') ?></button></p>
    <?php endif; ?>
    <small><?= bi($S['footer_disc_hi'], $S['footer_disc_en']) ?></small>
    <small><?= e(str_replace('{year}', date('Y'), $S['copyright'])) ?></small>
  </div>
</footer>
<?= ad_slot('mobile_sticky') ?>
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
  <?php if (!empty($s['image'])): ?><img class="card-img" src="<?= e($s['image']) ?>" alt="<?= e(tr($s['title_hi'], $s['title_en'])) ?>" loading="lazy"><?php endif; ?>
  <div class="card-top">
    <div class="ic" style="background:<?= e(valid_color($c['color'], '#eef2ff')) ?>"><?= e($s['icon'] ?: $c['icon']) ?></div>
    <span class="tag"><?= bi($c['name_hi'], $c['name_en']) ?></span>
  </div>
  <h3><?= bi($s['title_hi'], $s['title_en']) ?></h3>
  <p><?= bi($s['summary_hi'], $s['summary_en']) ?></p>
  <?php if ($s['elig_hi'] || $s['elig_en']): ?><div class="meta"><b><?= bi('पात्रता', 'Eligibility') ?>:</b> <?= bi($s['elig_hi'], $s['elig_en']) ?></div><?php endif; ?>
  <?php if ($hasPage): ?>
    <a class="btn" href="<?= e(lurl('/yojna/' . $s['slug'])) ?>"><?= bi('पूरी जानकारी देखें →', 'View full details →') ?></a>
  <?php else: ?>
    <span class="btn off" aria-disabled="true"><?= bi('जल्दी आ रही है', 'Coming soon') ?></span>
  <?php endif; ?>
</article>
<?php return ob_get_clean();
}
