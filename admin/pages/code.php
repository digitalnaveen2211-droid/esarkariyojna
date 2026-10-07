<?php
defined('ESY') or exit;

$S = settings();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ga = strtoupper(trim((string)($_POST['ga4_id'] ?? '')));
    $gtm = strtoupper(trim((string)($_POST['gtm_id'] ?? '')));
    if ($ga !== '' && !preg_match('/^G-[A-Z0-9]+$/', $ga)) { flash('GA4 ID ka format G-XXXXXXX hona chahiye.', 'err'); redirect('/admin/?p=code'); }
    if ($gtm !== '' && !preg_match('/^GTM-[A-Z0-9]+$/', $gtm)) { flash('GTM ID ka format GTM-XXXXXX hona chahiye.', 'err'); redirect('/admin/?p=code'); }
    save_settings([
        'ga4_id' => $ga, 'gtm_id' => $gtm,
        'head_code' => (string)($_POST['head_code'] ?? ''), 'body_code' => (string)($_POST['body_code'] ?? ''), 'footer_code' => (string)($_POST['footer_code'] ?? ''),
        'track_on' => ($_POST['track_on'] ?? '0') === '1' ? '1' : '0',
        'track_anonymize_ip' => ($_POST['track_anonymize_ip'] ?? '0') === '1' ? '1' : '0',
        'geo_on' => ($_POST['geo_on'] ?? '0') === '1' ? '1' : '0',
        'track_retention_days' => (string)max(7, min(3650, (int)($_POST['track_retention_days'] ?? 365))),
    ]);
    log_activity('update_code_tracking');
    flash('Save ho gaya ✔');
    redirect('/admin/?p=code');
}

admin_header('Code & Tracking', 'code');
?>
<form method="post" class="settings-form">
  <?= csrf_field() ?>
  <div class="card">
    <h2>Google Analytics & Tag Manager</h2>
    <div class="grid2">
      <?= f_text('ga4_id', 'Google Analytics 4 Measurement ID', $S['ga4_id'], ['placeholder' => 'G-XXXXXXXXXX', 'help' => 'analytics.google.com → Admin → Data streams → Web se milega.']) ?>
      <?= f_text('gtm_id', 'Google Tag Manager Container ID', $S['gtm_id'], ['placeholder' => 'GTM-XXXXXXX', 'help' => 'tagmanager.google.com se milega. GTM use kar rahe hain to GA4 ko GTM ke andar hi lagayein, dono jagah nahi.']) ?>
    </div>
  </div>
  <div class="card">
    <h2>Custom code</h2>
    <p class="alert warn">⚠️ Yahan daala code har page par chalega. Sirf bharosemand jagah (Google, Facebook Pixel, AdSense, Search Console verification) ka code daalein. Galat code se site toot sakti hai.</p>
    <?= f_area('head_code', 'Header code: <head> ke andar (meta verification, Pixel, AdSense, CSS)', $S['head_code'], ['rows' => 6, 'class' => 'code']) ?>
    <?= f_area('body_code', 'Body ke shuru me (<body> ke turant baad)', $S['body_code'], ['rows' => 4, 'class' => 'code']) ?>
    <?= f_area('footer_code', 'Footer code: </body> se pehle (chat widget, scripts)', $S['footer_code'], ['rows' => 6, 'class' => 'code']) ?>
  </div>
  <div class="card">
    <h2>Apni tracking (CMS database me)</h2>
    <?= f_check('track_on', 'Har page visit aur link click track karein', $S['track_on'] === '1') ?>
    <?= f_check('geo_on', 'IP se location (city/state/country) nikalein (ip-api.com)', $S['geo_on'] === '1') ?>
    <?= f_check('track_anonymize_ip', 'IP ka aakhri hissa chhupayein (privacy ke liye; location kam sahi hogi)', $S['track_anonymize_ip'] === '1') ?>
    <?= f_text('track_retention_days', 'Kitne din ka data rakhein', $S['track_retention_days'], ['type' => 'number', 'min' => 7, 'max' => 3650]) ?>
    <p class="muted">IP aur location personal data hai. Privacy Policy page me likhein ki aap ye data analytics ke liye collect karte hain.</p>
  </div>
  <button class="btn primary">💾 Save</button>
</form>
<?php admin_footer();
