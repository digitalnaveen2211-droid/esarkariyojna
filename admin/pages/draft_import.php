<?php
defined('ESY') or exit;
require_perm('settings.manage');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['rotate_token'])) {
        $token = bin2hex(random_bytes(32));
        save_settings(['draft_api_token_hash' => hash('sha256', $token)]);
        $_SESSION['draft_import_once'] = $token;
        log_activity('rotate_draft_api_token', 'Token rotated');
        flash('Draft API token created. Copy it once; it will not be displayed again.');
    } elseif (isset($_POST['disable_token'])) {
        save_settings(['draft_api_token_hash' => '']);
        unset($_SESSION['draft_import_once']);
        log_activity('disable_draft_api_token', 'Token disabled');
        flash('Draft API access disabled.');
    }
    redirect('/admin/?p=draft_import');
}
$once = $_SESSION['draft_import_once'] ?? '';
unset($_SESSION['draft_import_once']);
$enabled = trim((string)(settings()['draft_api_token_hash'] ?? '')) !== '';
admin_header('Automated Yojna Draft Import', 'draft_import');
?>
<div class="card">
  <h2>Draft-only API <?= $enabled ? '🟢 Active' : '⚪ Disabled' ?></h2>
  <p>Automated research tool ko <strong>sirf naye Draft yojna pages</strong> create karne ki permission hogi. Existing schemes ko edit, publish ya delete karne ka endpoint nahi hai.</p>
  <p class="muted">HTTPS token authentication, existing slug/title check, 1–5 drafts per import aur 25 successful drafts/day limit enabled hain.</p>
  <?php if ($once): ?>
    <div class="alert ok"><b>One-time token:</b> Isko abhi secure password manager mein copy karein. Reload ke baad nahi dikhega.</div>
    <textarea rows="3" style="width:100%" readonly><?= e($once) ?></textarea>
    <p><b>Do not</b> paste this token into GitHub code, issues, public screenshots, chat or browser URLs.</p>
  <?php endif; ?>
  <div style="display:flex;gap:12px;flex-wrap:wrap">
    <form method="post"><?= csrf_field() ?><button class="btn primary" name="rotate_token" value="1" onclick="return confirm('Old token immediately invalid ho jayega. Continue?')">Generate / Rotate Token</button></form>
    <?php if ($enabled): ?><form method="post"><?= csrf_field() ?><button class="btn danger" name="disable_token" value="1" onclick="return confirm('Draft API access disable karein?')">Disable API</button></form><?php endif; ?>
  </div>
</div>
<div class="card">
  <h2>How to connect</h2>
  <p><b>Endpoint:</b> <code>https://esarkariyojna.com/admin/draft-api.php</code></p>
  <p><b>GET:</b> Existing published and draft scheme slugs + CMS category slugs return karega (deduplication).</p>
  <p><b>POST:</b> JSON <code>{"drafts":[...]}</code>. Required header <code>X-Draft-Token: YOUR_SECRET_TOKEN</code> and <code>Content-Type: application/json</code>.</p>
  <p><b>Security:</b> Article content <code>content_hi_md</code> and <code>content_en_md</code> mein plaintext/limited Markdown-like formatting allow hai: <code>## heading</code>, <code>### heading</code>, <code>- list</code>, and paragraphs. HTML is escaped; hyperlinks official sources array se securely generate hote hain.</p>
  <p>Every scheme needs a unique slug, existing category_slug, bilingual titles, summaries, eligibility, 800+ characters per language, SEO fields, one HTTPS official_url and 1–8 source_urls on gov.in/nic.in domains. Unknown category/duplicates return errors, never overwrite.</p>
  <p><b>Review:</b> <a href="/admin/?p=posts&status=draft">Yojnayein → Draft</a> mein imported records kholkar verification, formatting aur image add karein; publication sirf CMS editor se manually hoti hai.</p>
  <p><b>Note:</b> API create hone se external ChatGPT automation apne aap connect nahi hoti. Token ko authorized integration/runner ke secure secret store se attach karna hoga.</p>
</div>
<?php admin_footer();
