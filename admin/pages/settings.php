<?php
defined('ESY') or exit;

$S = settings();
$groups = [
    'brand' => ['🎨 Logo & Rang', ['site_name', 'logo', 'favicon', 'color_primary', 'color_accent', 'default_lang']],
    'header' => ['🔝 Header & Notice', ['tagline_hi', 'tagline_en', 'notice_on', 'notice_hi', 'notice_en', 'declaration_hi', 'declaration_en']],
    'home' => ['🏠 Home page', ['hero_title_hi', 'hero_title_en', 'hero_sub_hi', 'hero_sub_en', 'hero_image', 'show_stats']],
    'footer' => ['🔻 Footer', ['footer_about_hi', 'footer_about_en', 'footer_disc_hi', 'footer_disc_en', 'copyright', 'contact_email',
                              'social_facebook', 'social_youtube', 'social_instagram', 'social_x', 'social_whatsapp', 'social_telegram']],
    'seo' => ['🔍 SEO', ['meta_title', 'meta_desc', 'site_url']],
];
$tab = isset($groups[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'brand';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vals = [];
    foreach ($groups[$tab][1] as $k) if (array_key_exists($k, $_POST)) $vals[$k] = trim((string)$_POST[$k]);
    $vals = apply_uploads($vals, ['logo', 'favicon', 'hero_image']);
    foreach (['color_primary' => '#4338ca', 'color_accent' => '#f97316'] as $k => $def) if (isset($vals[$k])) $vals[$k] = valid_color($vals[$k], $def);
    if (isset($vals['site_url'])) $vals['site_url'] = rtrim(safe_url($vals['site_url']), '/');
    save_settings($vals);
    log_activity('update_settings', $tab);
    flash('Settings save ho gayi ✔');
    redirect('/admin/?p=settings&tab=' . $tab);
}

admin_header('Site Settings', 'settings', '<a class="btn ghost" href="/" target="_blank">Website dekhein ↗</a>');
?>
<div class="tabs">
  <?php foreach ($groups as $k => [$label]): ?><a href="/admin/?p=settings&tab=<?= $k ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= $label ?></a><?php endforeach; ?>
</div>
<form method="post" enctype="multipart/form-data" class="card settings-form">
  <?= csrf_field() ?>
  <?php if ($tab === 'brand'): ?>
    <?= f_text('site_name', 'Website ka naam', $S['site_name']) ?>
    <div class="grid2"><?= f_image('logo', 'Logo', $S['logo']) ?><?= f_image('favicon', 'Favicon (browser tab icon)', $S['favicon']) ?></div>
    <div class="grid2">
      <label class="field"><span>Main rang (buttons, links)</span><input type="color" name="color_primary" value="<?= e($S['color_primary']) ?>"></label>
      <label class="field"><span>Accent rang (highlight)</span><input type="color" name="color_accent" value="<?= e($S['color_accent']) ?>"></label>
    </div>
    <?= f_select('default_lang', 'Default bhasha', $S['default_lang'], ['hi' => 'हिंदी', 'en' => 'English']) ?>
  <?php elseif ($tab === 'header'): ?>
    <div class="grid2"><?= f_text('tagline_hi', 'Tagline (हिंदी)', $S['tagline_hi']) ?><?= f_text('tagline_en', 'Tagline (English)', $S['tagline_en']) ?></div>
    <?= f_check('notice_on', 'Upar notice bar dikhayein', $S['notice_on'] === '1') ?>
    <div class="grid2"><?= f_text('notice_hi', 'Notice (हिंदी)', $S['notice_hi']) ?><?= f_text('notice_en', 'Notice (English)', $S['notice_en']) ?></div>
    <?= f_editor('declaration_hi', 'Poori ghoshna / disclaimer popup (हिंदी)', $S['declaration_hi']) ?>
    <?= f_editor('declaration_en', 'Declaration popup (English)', $S['declaration_en']) ?>
    <p class="muted">Header ke menu links <a href="/admin/?p=menus">Menus</a> me badlein.</p>
  <?php elseif ($tab === 'home'): ?>
    <div class="grid2"><?= f_text('hero_title_hi', 'Bada heading (हिंदी)', $S['hero_title_hi']) ?><?= f_text('hero_title_en', 'Heading (English)', $S['hero_title_en']) ?></div>
    <div class="grid2"><?= f_area('hero_sub_hi', 'Sub-heading (हिंदी)', $S['hero_sub_hi'], ['rows' => 2]) ?><?= f_area('hero_sub_en', 'Sub-heading (English)', $S['hero_sub_en'], ['rows' => 2]) ?></div>
    <?= f_image('hero_image', 'Hero background image (optional, halka dikhega)', $S['hero_image']) ?>
    <?= f_check('show_stats', 'Yojna / category ginti dikhayein', $S['show_stats'] === '1') ?>
  <?php elseif ($tab === 'footer'): ?>
    <div class="grid2"><?= f_area('footer_about_hi', 'About text (हिंदी)', $S['footer_about_hi'], ['rows' => 2]) ?><?= f_area('footer_about_en', 'About text (English)', $S['footer_about_en'], ['rows' => 2]) ?></div>
    <div class="grid2"><?= f_area('footer_disc_hi', 'Disclaimer (हिंदी)', $S['footer_disc_hi'], ['rows' => 2]) ?><?= f_area('footer_disc_en', 'Disclaimer (English)', $S['footer_disc_en'], ['rows' => 2]) ?></div>
    <div class="grid2"><?= f_text('copyright', 'Copyright line', $S['copyright'], ['help' => '{year} likhenge to apne aap saal aa jayega.']) ?><?= f_text('contact_email', 'Contact email', $S['contact_email'], ['type' => 'email']) ?></div>
    <h3>Social links</h3>
    <div class="grid3">
      <?php foreach (['facebook' => 'Facebook', 'youtube' => 'YouTube', 'instagram' => 'Instagram', 'x' => 'X / Twitter', 'whatsapp' => 'WhatsApp (channel/link)', 'telegram' => 'Telegram'] as $k => $l): ?>
        <?= f_text('social_' . $k, $l, $S['social_' . $k], ['placeholder' => 'https://...']) ?>
      <?php endforeach; ?>
    </div>
    <p class="muted">Footer ke "Quick links" <a href="/admin/?p=menus">Menus</a> me badlein.</p>
  <?php elseif ($tab === 'seo'): ?>
    <?= f_text('meta_title', 'Home page title (Google me dikhega)', $S['meta_title']) ?>
    <?= f_area('meta_desc', 'Home page description', $S['meta_desc'], ['rows' => 3]) ?>
    <?= f_text('site_url', 'Website URL', $S['site_url'], ['help' => 'Sitemap aur canonical links me use hota hai.']) ?>
    <p class="muted">Sitemap: <a href="/sitemap.xml" target="_blank">/sitemap.xml</a> (apne aap update hota hai). Ise Google Search Console me submit karein.</p>
  <?php endif; ?>
  <button class="btn primary">💾 Save</button>
</form>
<?php admin_footer($tab === 'header' ? '<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet"><script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script><script>esyEditors();</script>' : '');
