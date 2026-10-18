<?php
// Private, read-only CMS preview for SAVED drafts and published content.
// Never use a public draft token or expose draft records through the public router.
defined('ESY') or exit;
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; img-src \'self\' data: https:; style-src \'self\' \'unsafe-inline\'; font-src \'self\'; base-uri \'none\'; form-action \'none\'; frame-ancestors \'self\'; object-src \'none\'');

$type = (string)($_GET['type'] ?? '');
$idText = (string)($_GET['id'] ?? '');
$lang = (string)($_GET['lang'] ?? 'hi');
if (!in_array($type, ['yojna','blog','page'], true) || !ctype_digit($idText) || (int)$idText < 1
    || !in_array($lang, ['hi','en'], true)) {
    http_response_code(400);
    exit('Invalid preview parameters.');
}
if ($type === 'page') require_perm('pages.manage');
else require_perm('posts.edit');

$entry = q_one("SELECT * FROM posts WHERE id = ? AND type = ? LIMIT 1", [(int)$idText, $type]);
if (!$entry) {
    http_response_code(404);
    exit('CMS content not found.');
}
// Preview ONLY saved database fields; no edits, saving, publishing or logging of page views.
$title = (string)($entry['title_' . $lang] ?: $entry['title_hi']);
$summary = trim((string)($entry['summary_' . $lang] ?: ($entry['summary_hi'] ?? '')));
$html = (string)($entry['content_' . $lang] ?? '');
$seoTitle = trim((string)($entry[$lang === 'hi' ? 'seo_title' : 'seo_title_en'] ?? '')) ?: $title;
$seoDesc = trim((string)($entry[$lang === 'hi' ? 'seo_desc' : 'seo_desc_en'] ?? '')) ?: $summary;
$image = trim((string)($entry['image'] ?? ''));
$draft = ($entry['status'] ?? '') !== 'published';
$label = ['yojna'=>'Scheme / योजना','blog'=>'Blog / ब्लॉग','page'=>'Page / पेज'][$type];
$siteName = (string)(settings()['site_name'] ?? 'e-Sarkari Yojna');
$category = '';
if ($type === 'yojna' && !empty($entry['category_id'])) {
    $row = q_one('SELECT name_hi, name_en FROM categories WHERE id=?', [(int)$entry['category_id']]);
    $category = (string)($row['name_' . $lang] ?? $row['name_hi'] ?? '');
} elseif ($type === 'blog' && !empty($entry['blog_cat_id'])) {
    $row = q_one('SELECT name_hi, name_en FROM blog_categories WHERE id=?', [(int)$entry['blog_cat_id']]);
    $category = (string)($row['name_' . $lang] ?? $row['name_hi'] ?? '');
}
$back = '/admin/?p=post_edit&type=' . rawurlencode($type) . '&id=' . (int)$entry['id'];
$self = '/admin/?p=content_preview&type=' . rawurlencode($type) . '&id=' . (int)$entry['id'];
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow,noarchive,nosnippet">
<title><?= e($title) ?> — Private CMS Preview</title>
<link rel="stylesheet" href="/assets/style.css?v=<?= (int)@filemtime(ROOT . '/assets/style.css') ?>">
<style>
  body{margin:0;background:#f5f7fc;color:#162844;font-family:system-ui,"Mukta",sans-serif}
  .preview-toolbar{position:sticky;top:0;z-index:30;background:#13213a;color:#fff;padding:13px clamp(14px,3vw,36px);display:flex;align-items:center;gap:12px;flex-wrap:wrap;box-shadow:0 5px 20px #15223b22}
  .preview-toolbar strong{font-size:15px;margin-right:auto}
  .preview-toolbar a{color:#fff;text-decoration:none;border:1px solid #8395b2;border-radius:8px;padding:7px 13px;font-size:14px}
  .preview-toolbar a.active{background:#fff;color:#163260}
  .preview-toolbar a:hover{text-decoration:underline}
  .preview-ribbon{background:#fff1c2;color:#664515;padding:10px 20px;text-align:center;font-weight:750;border-bottom:1px solid #efd899}
  .preview-ribbon.live{background:#e4f6ea;color:#16633d;border-color:#c8e8d3}
  .preview-wrap{max-width:1000px;margin:26px auto 55px;padding:0 18px}
  .preview-paper{background:#fff;border:1px solid #e1e8f3;border-radius:18px;padding:clamp(18px,3.5vw,50px);box-shadow:0 8px 35px #1528410b}
  .preview-kicker{display:inline-block;color:#3459a7;background:#edf3ff;border-radius:20px;padding:5px 11px;font-size:13px;font-weight:700}
  .preview-paper h1{font-size:clamp(29px,3.1vw,42px);line-height:1.3;margin:18px 0}
  .preview-lead{font-size:18px;line-height:1.6;color:#536784}
  .preview-img{display:block;max-width:100%;height:auto;object-fit:contain;border-radius:12px;margin:22px 0 26px}
  .preview-paper .prose{overflow-wrap:anywhere;line-height:1.75}
  .preview-paper .prose img{max-width:100%;height:auto}
  .preview-paper .prose table{max-width:100%;border-collapse:collapse}
  .preview-paper .prose td,.preview-paper .prose th{border:1px solid #dde5f1;padding:10px 12px}
  .preview-paper .table-responsive{max-width:100%;overflow-x:auto}
  .preview-meta{margin-top:25px;border-top:1px solid #e3eaf4;padding-top:18px;color:#536784;font-size:13px;overflow-wrap:anywhere}
  .preview-note{margin:13px auto 0;color:#60728c;text-align:center;font-size:13px}
  @media(max-width:600px){.preview-toolbar{gap:8px}.preview-toolbar strong{flex-basis:100%;margin-right:0}.preview-paper{border-radius:12px}.preview-lead{font-size:16px}}
</style>
</head>
<body>
<header class="preview-toolbar">
  <strong>🔒 <?= e($siteName) ?> · <?= e($label) ?> · <?= $draft ? 'DRAFT PREVIEW' : 'Saved content preview' ?></strong>
  <a href="<?= e($back) ?>">← <?= $lang === 'hi' ? 'एडिटर पर वापस' : 'Back to editor' ?></a>
  <a href="<?= e($self . '&lang=hi') ?>" class="<?= $lang === 'hi' ? 'active' : '' ?>" <?= $lang === 'hi' ? 'aria-current="page"' : '' ?>>हिंदी</a>
  <a href="<?= e($self . '&lang=en') ?>" class="<?= $lang === 'en' ? 'active' : '' ?>" <?= $lang === 'en' ? 'aria-current="page"' : '' ?>>English</a>
</header>
<div class="preview-ribbon<?= !$draft ? ' live' : '' ?>">
  <?= $draft ? ($lang === 'hi' ? 'यह केवल निजी CMS ड्राफ्ट प्रीव्यू है — वेबसाइट पर प्रकाशित नहीं है।' : 'Private CMS draft preview only — not published on the website.') :
      ($lang === 'hi' ? 'यह सेव किए हुए प्रकाशित पेज का CMS प्रीव्यू है।' : 'Previewing the saved published content.') ?>
</div>
<main class="preview-wrap">
  <article class="preview-paper article">
    <?php if ($category): ?><span class="preview-kicker"><?= e($category) ?></span><?php endif; ?>
    <h1><?= e($title) ?></h1>
    <?php if ($summary): ?><p class="preview-lead"><?= e($summary) ?></p><?php endif; ?>
    <?php if ($image): ?><img class="preview-img" src="<?= e($image) ?>" alt="<?= e($title) ?>"><?php endif; ?>
    <div class="prose"><?= $html !== '' ? $html : '<p>' . e($lang === 'hi' ? 'इस भाषा का कंटेंट अभी सेव नहीं किया गया है।' : 'No saved content in this language yet.') . '</p>' ?></div>
    <aside class="preview-meta">
      <p><strong>SEO title:</strong> <?= e($seoTitle) ?></p>
      <p><strong>Meta description:</strong> <?= e($seoDesc) ?></p>
      <p><strong>Slug:</strong> <?= e($entry['slug']) ?> · <strong>Status:</strong> <?= e($entry['status']) ?> · <strong>Last saved:</strong> <?= e((string)$entry['updated_at']) ?></p>
    </aside>
  </article>
  <p class="preview-note"><?= $lang === 'hi' ? 'प्रीव्यू सेव किए हुए बदलाव दिखाता है। नए बदलाव देखने के लिए पहले एडिटर में Save करें।' : 'The preview shows saved changes only. Save in the editor before refreshing.' ?></p>
</main>
</body></html>
