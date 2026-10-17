<?php
defined('ESY') or exit;
require_perm('settings.manage');
header('Cache-Control: no-store');
require_once ROOT . '/app/draft_import.php';

// Human-reviewed JSON batch importer. No API token required in browser.
$imported = $_SESSION['yojna_import_results'] ?? [];
unset($_SESSION['yojna_import_results']);
$import_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview_json'])) {
    $file = $_FILES['draft_json'] ?? null;
    if (!$file || (int)$file['error'] !== UPLOAD_ERR_OK || (int)$file['size'] > 1500000
        || (int)$file['size'] < 2 || !is_uploaded_file($file['tmp_name'])) {
        $import_error = 'Choose a valid JSON file (up to 1.5 MB).';
    } else {
        try {
            $raw = json_decode((string)file_get_contents($file['tmp_name']), true, 30, JSON_THROW_ON_ERROR);
            $drafts = $raw['drafts'] ?? null;
            if (!is_array($drafts) || !array_is_list($drafts) || count($drafts) < 1 || count($drafts) > 5) {
                throw new InvalidArgumentException('JSON must have 1–5 drafts.');
            }
            foreach ($drafts as $idx => $draft) {
                if (!is_array($draft)) throw new InvalidArgumentException("Draft $idx is not a JSON object.");
                draft_import_text($draft, 'slug', 150, true);
                draft_import_text($draft, 'title_hi', 255, true);
                draft_import_text($draft, 'title_en', 255, true);
            }
            $_SESSION['yojna_import_pending'] = $drafts;
            flash('Preview ready: verify 1–5 schemes and choose their CMS categories. No records created yet.');
        } catch (Throwable $exc) {
            unset($_SESSION['yojna_import_pending']);
            $import_error = 'JSON validation error: ' . $exc->getMessage();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_import'])) {
    unset($_SESSION['yojna_import_pending']);
    flash('Pending JSON import cancelled.');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    $pending = $_SESSION['yojna_import_pending'] ?? null;
    $assign = $_POST['category_ids'] ?? null;
    if (!is_array($pending) || !is_array($assign) || count($pending) < 1 || count($pending) > 5) {
        $import_error = 'Import session expired. Upload the JSON again.';
    } else {
        try {
            $cats = categories();
            $allowed = array_fill_keys(array_map(static fn($c) => (int)$c['id'], $cats), true);
            $bySlug = [];
            foreach ($cats as $cat) $bySlug[$cat['slug']] = (int)$cat['id'];
            $validated = [];
            $seen = [];
            foreach ($pending as $i => $draft) {
                $id = filter_var($assign[$i] ?? null, FILTER_VALIDATE_INT);
                if (!$id || !isset($allowed[$id])) throw new InvalidArgumentException("Select an existing category for row " . ($i + 1));
                // Explicit category chosen during preview takes precedence over an unknown suggested category.
                $draft['category_slug'] = (string)array_search($id, $bySlug, true);
                $item = draft_import_validate($draft, $bySlug);
                if (isset($seen[$item['slug']])) throw new InvalidArgumentException('Duplicate slug in batch: ' . $item['slug']);
                $seen[$item['slug']] = true;
                $validated[] = $item;
            }
            db()->beginTransaction();
            foreach ($validated as $item) {
                $existing = q_one("SELECT id FROM posts WHERE type='yojna' AND (slug=? OR title_hi=? OR title_en=?) LIMIT 1",
                    [$item['slug'], $item['title_hi'], $item['title_en']]);
                if ($existing) throw new InvalidArgumentException('Existing published/draft scheme already uses this slug or title: ' . $item['slug']);
            }
            $cols = ['slug','category_id','title_hi','title_en','summary_hi','summary_en','elig_hi','elig_en',
                'content_hi','content_en','seo_title','seo_title_en','seo_desc','seo_desc_en','keywords','official_url'];
            $values = implode(',', array_fill(0, count($cols), '?'));
            $sql = "INSERT INTO posts (type, " . implode(',', $cols) .
                ", status, featured, author_id, created_at, updated_at) VALUES ('yojna', $values, 'draft', 0, ?, NOW(), NOW())";
            $results = [];
            foreach ($validated as $item) {
                q($sql, [...array_map(static fn($k) => $item[$k], $cols), (int)current_user()['id']]);
                $newId = (int)db()->lastInsertId();
                $results[] = ['id' => $newId, 'slug' => $item['slug']];
                log_activity('manual_yojna_draft_import', $item['slug']);
            }
            db()->commit();
            unset($_SESSION['yojna_import_pending']);
            $_SESSION['yojna_import_results'] = $results;
            flash(count($results) . ' new schemes saved as DRAFT. Nothing published.');
            redirect('/admin/?p=draft_import');
        } catch (Throwable $exc) {
            if (db()->inTransaction()) db()->rollBack();
            $import_error = $exc instanceof InvalidArgumentException ? $exc->getMessage() : 'Import failed. Check server logs.';
            if (!$exc instanceof InvalidArgumentException) error_log('Manual draft import: ' . $exc->getMessage());
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['rotate_token']) || isset($_POST['disable_token']))) {
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
<?php if ($import_error): ?><div class="alert err"><?= e($import_error) ?></div><?php endif; ?>
<?php if ($imported): ?>
<div class="card">
  <h2>Imported draft records (not published)</h2>
  <?php foreach ($imported as $result): ?>
    <p>✔ <?= e($result['slug']) ?> — <a href="/admin/?p=post_edit&type=yojna&id=<?= (int)$result['id'] ?>">Open draft #<?= (int)$result['id'] ?></a></p>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<div class="card">
  <h2>Import today's JSON drafts</h2>
  <p>Admin-only: upload research JSON (up to 5 schemes), preview, assign existing categories and confirm. This creates <strong>DRAFT</strong> records only. No token or GitHub secret is required in your browser.</p>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="file" accept=".json,application/json" name="draft_json" required>
    <button class="btn primary" name="preview_json" value="1">Preview JSON — no changes yet</button>
  </form>
  <?php $pending = $_SESSION['yojna_import_pending'] ?? []; ?>
  <?php if (is_array($pending) && $pending): ?>
    <h3>Preview — <?= count($pending) ?> draft(s)</h3>
    <p class="muted">Select correct CMS category for each. Existing published or draft slugs/titles are rejected when saving.</p>
    <form method="post">
      <?= csrf_field() ?>
      <?php foreach ($pending as $i => $draft): ?>
        <div style="padding:12px 0;border-bottom:1px solid #ddd">
          <b><?= (int)($i + 1) ?>. <?= e($draft['title_hi'] ?? '') ?></b>
          <p class="muted"><?= e($draft['title_en'] ?? '') ?> | <?= e($draft['slug'] ?? '') ?></p>
          <label>CMS Category
          <select name="category_ids[<?= (int)$i ?>]" required>
            <option value="">-- Select category --</option>
            <?php foreach (categories() as $cat): ?>
              <option value="<?= (int)$cat['id'] ?>" <?= $cat['slug'] === ($draft['category_slug'] ?? '') ? 'selected' : '' ?>>
                <?= e($cat['name_hi'] . ' (' . $cat['slug'] . ')') ?>
              </option>
            <?php endforeach; ?>
          </select></label>
        </div>
      <?php endforeach; ?>
      <p><button class="btn primary" name="confirm_import" value="1" onclick="return confirm('Save all these schemes as DRAFT only?')">Save <?= count($pending) ?> as Draft</button>
      <button class="btn ghost" name="cancel_import" value="1">Cancel preview</button></p>
    </form>
  <?php endif; ?>
</div>
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
