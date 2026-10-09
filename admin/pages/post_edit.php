<?php
defined('ESY') or exit;

$type = ($_GET['type'] ?? 'yojna') === 'page' ? 'page' : 'yojna';
if ($type === 'page') require_perm('pages.manage');
$canPublish = $type === 'page' ? can('pages.manage') : can('posts.publish');
$id = (int)($_GET['id'] ?? 0);
$fields = ['slug', 'category_id', 'icon', 'title_hi', 'title_en', 'summary_hi', 'summary_en', 'elig_hi', 'elig_en', 'keywords',
           'content_hi', 'content_en', 'official_url', 'image', 'status', 'featured', 'seo_title', 'seo_desc', 'seo_title_en', 'seo_desc_en', 'sort'];
$post = $id ? q_one('SELECT * FROM posts WHERE id = ? AND type = ?', [$id, $type]) : null;
if ($id && !$post) { flash('Post nahi mila.', 'err'); redirect('/admin/?p=' . ($type === 'page' ? 'pages' : 'posts')); }
$post = $post ?: array_fill_keys($fields, '') + ['status' => 'draft', 'featured' => 0, 'sort' => 0, 'category_id' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = [];
    foreach ($fields as $f) $d[$f] = trim((string)($_POST[$f] ?? ''));
    $d = apply_uploads($d, ['image']);
    $d['slug'] = slugify($d['slug'] !== '' ? $d['slug'] : ($d['title_en'] !== '' ? $d['title_en'] : $d['title_hi']));
    $d['category_id'] = $type === 'yojna' && (int)$d['category_id'] ? (int)$d['category_id'] : null;
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

$url = '/hi' . ($type === 'yojna' ? '/yojna/' : '/page/') . $post['slug'];
$title = $id ? 'Edit: ' . $post['title_hi'] : ($type === 'yojna' ? 'Nayi Yojna' : 'Naya Page');
admin_header($title, $type === 'yojna' ? 'posts' : 'pages', $id && $post['status'] === 'published' ? '<a class="btn ghost" target="_blank" href="' . e($url) . '">Website par dekhein ↗</a>' : '');
?>
<form method="post" enctype="multipart/form-data" class="edit-grid">
  <?= csrf_field() ?>
  <div class="edit-main">
    <div class="card">
      <div class="lang-tabs" data-tabs>
        <button type="button" class="on" data-tab="hi">हिंदी</button><button type="button" data-tab="en">English</button>
      </div>
      <div data-pane="hi">
        <?= f_text('title_hi', 'Title (हिंदी) *', $post['title_hi'], ['required' => true]) ?>
        <?php if ($type === 'yojna'): ?>
          <?= f_area('summary_hi', 'Chhota summary (card par dikhega)', $post['summary_hi'], ['rows' => 2]) ?>
          <?= f_area('elig_hi', 'Patrata (eligibility) ek line me', $post['elig_hi'], ['rows' => 2]) ?>
        <?php endif; ?>
        <?= f_editor('content_hi', 'Poora content (हिंदी)', $post['content_hi']) ?>
      </div>
      <div data-pane="en" hidden>
        <?= f_text('title_en', 'Title (English)', $post['title_en']) ?>
        <?php if ($type === 'yojna'): ?>
          <?= f_area('summary_en', 'Short summary', $post['summary_en'], ['rows' => 2]) ?>
          <?= f_area('elig_en', 'Eligibility (one line)', $post['elig_en'], ['rows' => 2]) ?>
        <?php endif; ?>
        <?= f_editor('content_en', 'Full content (English)', $post['content_en']) ?>
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
      <?php if ($type === 'yojna'): ?><?= f_text('keywords', 'Search keywords (site search ke liye)', $post['keywords'], ['help' => 'Hindi aur English dono shabd daalein, jaise: kisan farmer किसान']) ?><?php endif; ?>
    </div>
  </div>
  <aside class="edit-side">
    <div class="card sticky">
      <?php if ($canPublish): ?>
        <?= f_select('status', 'Status', $post['status'], ['draft' => 'Draft (chhupa hua)', 'published' => 'Published (live)']) ?>
      <?php else: ?>
        <p class="muted">Status: <b><?= e($post['status']) ?></b>. Publish karne ki permission Editor/Admin ke paas hai.</p>
      <?php endif; ?>
      <?= f_text('slug', 'URL slug', $post['slug'], ['help' => 'Link: /hi' . e(($type === 'yojna' ? '/yojna/' : '/page/')) . '<b>slug</b> aur /en' . e(($type === 'yojna' ? '/yojna/' : '/page/')) . '<b>slug</b>. Khali chhodenge to title se ban jayega.']) ?>
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
      <small class="muted">Save karne ke baad score update hota hai. Hindi version ke hisaab se.</small>
    </div>
    <?php if ($type === 'yojna'): ?>
    <div class="card">
      <?= f_select('category_id', 'Category', $post['category_id'], ['' => '— Koi nahi —'] + array_column(array_map(fn($c) => ['id' => $c['id'], 'n' => $c['icon'] . ' ' . $c['name_hi']], categories()), 'n', 'id')) ?>
      <?= f_text('icon', 'Icon (emoji)', $post['icon'], ['maxlength' => 8, 'placeholder' => '🌾']) ?>
      <?= f_text('official_url', 'Official website link', $post['official_url'], ['placeholder' => 'https://pmkisan.gov.in']) ?>
      <?= f_check('featured', '⭐ Home page par featured dikhayein', (int)$post['featured']) ?>
      <?= f_text('sort', 'Order (chhota number pehle)', $post['sort'], ['type' => 'number']) ?>
    </div>
    <?php else: ?>
      <input type="hidden" name="sort" value="<?= (int)$post['sort'] ?>">
    <?php endif; ?>
    <div class="card"><?= f_image('image', 'Featured image', $post['image']) ?></div>
  </aside>
</form>
<?php admin_footer('<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet"><script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script><script>esyEditors();</script>');
