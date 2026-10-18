<?php
defined('ESY') or exit;

$type = in_array($_GET['type'] ?? '', ['page', 'blog'], true) ? $_GET['type'] : 'yojna';
$listPage = ['yojna' => 'posts', 'page' => 'pages', 'blog' => 'blog'][$type];
if ($type === 'page') require_perm('pages.manage');
$canPublish = $type === 'page' ? can('pages.manage') : can('posts.publish');
$id = (int)($_GET['id'] ?? 0);
$fields = ['slug', 'gov_level', 'state_code', 'category_id', 'blog_cat_id', 'icon', 'title_hi', 'title_en', 'summary_hi', 'summary_en', 'elig_hi', 'elig_en', 'keywords',
           'content_hi', 'content_en', 'official_url', 'image', 'status', 'featured', 'seo_title', 'seo_desc', 'seo_title_en', 'seo_desc_en', 'sort'];
$post = $id ? q_one('SELECT * FROM posts WHERE id = ? AND type = ?', [$id, $type]) : null;
if ($id && !$post) { flash('Post nahi mila.', 'err'); redirect('/admin/?p=' . $listPage); }
$post = $post ?: array_fill_keys($fields, '') + ['status' => 'draft', 'featured' => 0, 'sort' => 0, 'category_id' => null, 'blog_cat_id' => null, 'created_at' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seo_autofix']) && $id) {
    $ch = seo_autofix(q_one('SELECT * FROM posts WHERE id = ?', [$id]));
    if ($ch) q('UPDATE posts SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($ch))) . ' WHERE id = ?', [...array_values($ch), $id]);
    log_activity('seo_autofix', $post['title_hi']);
    flash($ch ? 'SEO auto-fix ho gaya ✔ (' . implode(', ', array_keys($ch)) . ')' : 'Auto-fix karne layak kuch nahi mila.');
    redirect('/admin/?p=post_edit&type=' . $type . '&id=' . $id . '#seo');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = [];
    foreach ($fields as $f) $d[$f] = trim((string)($_POST[$f] ?? ''));
    $d = apply_uploads($d, ['image']);
    $d['slug'] = slugify($d['slug'] !== '' ? $d['slug'] : ($d['title_en'] !== '' ? $d['title_en'] : $d['title_hi']));
    $d['gov_level'] = $type === 'yojna' && $d['gov_level'] === 'state' ? 'state' : 'central';
    $d['state_code'] = $type === 'yojna' && $d['gov_level'] === 'state' && array_key_exists($d['state_code'], scheme_states()) ? $d['state_code'] : '';
    $d['category_id'] = $type === 'yojna' && (int)$d['category_id'] ? (int)$d['category_id'] : null;
    $d['blog_cat_id'] = $type === 'blog' && (int)$d['blog_cat_id'] ? (int)$d['blog_cat_id'] : null;
    $d['featured'] = $d['featured'] === '1' ? 1 : 0;
    $d['sort'] = (int)$d['sort'];
    $d['icon'] = mb_substr($d['icon'], 0, 8);
    $d['official_url'] = $d['official_url'] !== '' ? safe_url($d['official_url']) : '';
    if (!in_array($d['status'], ['draft', 'published'], true)) $d['status'] = 'draft';
    if (!$canPublish) $d['status'] = $id ? $post['status'] : 'draft'; // authors cannot change publish state

    $dup = q_val('SELECT id FROM posts WHERE type = ? AND slug = ? AND id <> ?', [$type, $d['slug'], $id]);
    if ($d['title_hi'] === '') {
        flash('Hindi title zaroori hai.', 'err');
        $post = array_merge($post, $d);
    } elseif ($dup) {
        flash('Ye URL (slug) pehle se kisi aur ka hai. Dusra slug rakhein.', 'err');
        $post = array_merge($post, $d);
    } else {
        if ($id) {
            $set = implode(', ', array_map(fn($f) => "$f = ?", $fields));
            q("UPDATE posts SET $set, updated_at = ? WHERE id = ?", array_merge(array_values(array_intersect_key($d, array_flip($fields))), [now(), $id]));
            log_activity('update_' . $type, $d['title_hi']);
        } else {
            $cols = implode(', ', $fields);
            $qs = implode(', ', array_fill(0, count($fields), '?'));
            q("INSERT INTO posts (type, $cols, author_id, created_at, updated_at) VALUES (?, $qs, ?, ?, ?)",
              array_merge([$type], array_values(array_intersect_key(array_replace(array_flip($fields), $d), array_flip($fields))), [current_user()['id'], now(), now()]));
            $id = (int)db()->lastInsertId();
            log_activity('create_' . $type, $d['title_hi']);
        }
        flash('Save ho gaya ✔');
        redirect('/admin/?p=post_edit&type=' . $type . '&id=' . $id);
    }
}

$url = '/hi' . post_path($type, (string)$post['slug']);
$title = $id ? 'Edit: ' . $post['title_hi'] : ['yojna' => 'Nayi Yojna', 'page' => 'Naya Page', 'blog' => 'Naya Blog Post'][$type];
$previewButton = $id ? '<a class="btn ghost" target="_blank" rel="noopener" href="/admin/?p=content_preview&type=' . e($type) . '&id=' . $id . '&lang=hi">👁 Preview draft / saved page</a>' : '';
admin_header($title, $listPage, ($id && $post['status'] === 'published' ? '<a class="btn ghost" target="_blank" href="' . e($url) . '">Website par dekhein ↗</a>' : '') . $previewButton . '<button class="btn primary" form="postForm">💾 Save</button>');
?>
<form method="post" enctype="multipart/form-data" class="edit-grid" id="postForm">
  <?= csrf_field() ?>
  <div class="edit-main">
    <div class="card">
      <div class="lang-tabs" data-tabs>
        <button type="button" class="on" data-tab="hi">हिंदी</button><button type="button" data-tab="en">English</button>
      </div>
      <div data-pane="hi">
        <?= f_text('title_hi', 'Title (हिंदी) *', $post['title_hi'], ['required' => true]) ?>
        <?php if ($type === 'blog'): ?>
          <?= f_area('summary_hi', 'Chhota saar / excerpt (list me card par dikhega)', $post['summary_hi'], ['rows' => 2]) ?>
        <?php endif; ?>
        <?php if ($type === 'yojna'): ?>
          <?= f_area('summary_hi', 'Chhota summary (card par dikhega)', $post['summary_hi'], ['rows' => 2]) ?>
          <?= f_area('elig_hi', 'Patrata (eligibility) ek line me', $post['elig_hi'], ['rows' => 2]) ?>
        <?php endif; ?>
        <?= f_editor('content_hi', 'Poora content (हिंदी)', $post['content_hi']) ?>
        <?php if ($type === 'blog'): ?>
          <?php $savedWords = trim(seo_plain((string)$post['content_hi'])); $savedCount = $savedWords === '' ? 0 : count(preg_split('/\\s+/u', $savedWords) ?: []); ?>
          <p class="muted" style="margin:10px 0;font-weight:700">📖 Saved Hindi article: <?= number_format($savedCount) ?> approximate words · No fixed Google ranking word-count requirement.</p>
        <?php endif; ?>
      </div>
      <div data-pane="en" hidden>
        <?= f_text('title_en', 'Title (English)', $post['title_en']) ?>
        <?php if ($type === 'blog'): ?>
          <?= f_area('summary_en', 'Short excerpt', $post['summary_en'], ['rows' => 2]) ?>
        <?php endif; ?>
        <?php if ($type === 'yojna'): ?>
          <?= f_area('summary_en', 'Short summary', $post['summary_en'], ['rows' => 2]) ?>
          <?= f_area('elig_en', 'Eligibility (one line)', $post['elig_en'], ['rows' => 2]) ?>
        <?php endif; ?>
        <?= f_editor('content_en', 'Full content (English)', $post['content_en']) ?>
        <?php if ($type === 'blog'): ?>
          <?php $savedWords = trim(seo_plain((string)$post['content_en'])); $savedCount = $savedWords === '' ? 0 : count(preg_split('/\\s+/u', $savedWords) ?: []); ?>
          <p class="muted" style="margin:10px 0;font-weight:700">📖 Saved English article: <?= number_format($savedCount) ?> approximate words · No fixed Google ranking word-count requirement.</p>
        <?php endif; ?>
      </div>
    </div>
    <div class="card">
      <h2>SEO (Google ke liye)</h2>
      <p class="muted">Hindi page <code>/hi/...</code> aur English page <code>/en/...</code> ke liye alag. Hindi wala देवनागरी me likhein. English content khali hoga to English page Google me index nahi hoga (duplicate se bachne ke liye).</p>
      <div class="grid2">
        <div>
          <?= f_text('seo_title', 'SEO title (हिंदी)', $post['seo_title'], ['maxlength' => 255, 'help' => 'Khali chhodenge to Hindi title use hoga. 60 characters tak best.']) ?>
          <?= f_area('seo_desc', 'Meta description (हिंदी)', $post['seo_desc'], ['rows' => 2, 'help' => '150-160 characters best.']) ?>
        </div>
        <div>
          <?= f_text('seo_title_en', 'SEO title (English)', $post['seo_title_en'] ?? '', ['maxlength' => 255, 'help' => 'Empty = English title is used.']) ?>
          <?= f_area('seo_desc_en', 'Meta description (English)', $post['seo_desc_en'] ?? '', ['rows' => 2, 'help' => '150-160 characters best.']) ?>
        </div>
      </div>
      <?php if ($type !== 'page'): ?><?= f_text('keywords', 'Search keywords (site search ke liye)', $post['keywords'], ['help' => 'Hindi aur English dono shabd daalein, jaise: kisan farmer किसान']) ?><?php endif; ?>
    </div>
  </div>
  <aside class="edit-side">
    <div class="card">
      <?php if ($canPublish): ?>
        <?= f_select('status', 'Status', $post['status'], ['draft' => 'Draft (chhupa hua)', 'published' => 'Published (live)']) ?>
      <?php else: ?>
        <p class="muted">Status: <b><?= e($post['status']) ?></b>. Publish karne ki permission Editor/Admin ke paas hai.</p>
      <?php endif; ?>
      <?= f_text('slug', 'URL slug', $post['slug'], ['help' => 'Link: /hi' . e(post_path($type, '')) . '<b>slug</b> aur /en' . e(post_path($type, '')) . '<b>slug</b>. Khali chhodenge to title se ban jayega.']) ?>
      <button class="btn primary block">💾 Save</button>
    </div>
    <?php $seo = seo_analyze($post + ['type' => $type]); ?>
    <div class="card seo-card" id="seo">
      <div class="card-head"><h2>SEO score</h2><?= seo_badge($seo['score']) ?></div>
      <ul class="seo-list">
        <?php foreach ($seo['checks'] as $c): ?>
          <li class="<?= $c['ok'] === true ? 'ok' : ($c['ok'] === null ? 'mid' : 'bad') ?>"><b><?= e($c['label']) ?></b><?php if ($c['ok'] !== true): ?><small><?= e($c['tip']) ?></small><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($id): ?><button class="btn ghost block" form="seoFix">⚡ Auto-fix SEO</button><?php endif; ?>
      <small class="muted">Save karne ke baad score update hota hai. Hindi version ke hisaab se. Auto-fix se pehle apne badlaav Save kar lein.</small>
    </div>
    <?php if ($type === 'yojna'): ?>
    <div class="card">
      <?= f_select('gov_level', 'Government Level', $post['gov_level'] ?? 'central', ['central' => '🏛 Central Government', 'state' => '📍 State / UT Government'], ['help' => 'Correct classification is required for homepage Central/State filters.']) ?>
      <?php $stateOpts = ['' => '— Not applicable / All India —']; foreach (scheme_states() as $code => $names) $stateOpts[$code] = $names[0] . ' / ' . $names[1]; ?>
      <?= f_select('state_code', 'State / UT (only for state schemes)', $post['state_code'] ?? '', $stateOpts, ['help' => 'Select the state where this scheme applies. Central scheme: leave blank.']) ?>
      <?= f_select('category_id', 'Category', $post['category_id'], ['' => '— Koi nahi —'] + array_column(array_map(fn($c) => ['id' => $c['id'], 'n' => $c['icon'] . ' ' . $c['name_hi']], categories()), 'n', 'id')) ?>
      <?= f_text('icon', 'Icon (emoji)', $post['icon'], ['maxlength' => 8, 'placeholder' => '🌾']) ?>
      <?= f_text('official_url', 'Official website link', $post['official_url'], ['placeholder' => 'https://pmkisan.gov.in']) ?>
      <?= f_check('featured', '⭐ Home page par featured dikhayein', (int)$post['featured']) ?>
      <?= f_text('sort', 'Order (chhota number pehle)', $post['sort'], ['type' => 'number']) ?>
    </div>
    <?php elseif ($type === 'blog'): ?>
    <div class="card">
      <?php $opts = ['' => '— Koi category nahi —']; foreach (blog_cat_tree() as $c) $opts[$c['id']] = str_repeat('— ', $c['depth']) . $c['name_hi']; ?>
      <?= f_select('blog_cat_id', 'Category / Sub-category', $post['blog_cat_id'], $opts, ['help' => 'Nayi category <a href="/admin/?p=blog_categories" target="_blank">Blog Categories</a> me banayein.']) ?>
      <input type="hidden" name="sort" value="<?= (int)$post['sort'] ?>">
    </div>
    <?php else: ?>
      <input type="hidden" name="sort" value="<?= (int)$post['sort'] ?>">
    <?php endif; ?>
    <div class="card"><?= f_image('image', 'Featured image', $post['image']) ?></div>
  </aside>
</form>
<?php if ($id): ?><form method="post" id="seoFix" hidden><?= csrf_field() ?><input type="hidden" name="seo_autofix" value="1"></form><?php endif; ?>
<?php admin_footer('<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet"><script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script><script>esyEditors();</script>');
