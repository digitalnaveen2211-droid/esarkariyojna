<?php
defined('ESY') or exit;

$type = $p === 'pages' ? 'page' : ($p === 'blog' ? 'blog' : 'yojna');
$typeLabel = ['yojna' => ['Yojnayein', 'Nayi Yojna', 'yojnaon'], 'page' => ['Pages', 'Naya Page', 'pages'], 'blog' => ['Blog posts', 'Naya Blog Post', 'blog posts']][$type];
$canDelete = $type === 'page' ? can('pages.manage') : can('posts.publish');
if ($type === 'page') require_once ROOT . '/app/page_seo.php';

// Review-first metadata refresh. This action changes ONLY four SEO metadata
// columns on existing published pages. No URL, title/H1, body or status changes.
if ($type === 'page' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_page_seo'])) {
    require_perm('pages.manage');
    $recommended = esy_page_seo_recommendations();
    try {
        db()->beginTransaction();
        $updated = 0;
        foreach ($recommended as $slug => $meta) {
            $row = q_one("SELECT id FROM posts WHERE type='page' AND slug=? AND status='published' FOR UPDATE", [$slug]);
            if (!$row) continue; // Never invent missing pages.
            q("UPDATE posts SET seo_title=?, seo_title_en=?, seo_desc=?, seo_desc_en=?, updated_at=NOW() WHERE id=? AND type='page' AND status='published'",
                [$meta['seo_title'], $meta['seo_title_en'], $meta['seo_desc'], $meta['seo_desc_en'], $row['id']]);
            $updated++;
        }
        db()->commit();
        log_activity('page_seo_refresh', 'updated metadata: ' . $updated . ' pages');
        flash($updated . ' published pages ka Hindi-English SEO metadata update ho gaya. Content, slug, image aur status nahi badle.');
    } catch (Throwable $ex) {
        if (db()->inTransaction()) db()->rollBack();
        error_log('Page SEO refresh: ' . $ex->getMessage());
        flash('SEO metadata update failed. Database unchanged.', 'err');
    }
    redirect('/admin/?p=pages');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if (!$canDelete) { flash('Delete ki permission nahi hai.', 'err'); }
    else {
        $row = q_one('SELECT title_hi FROM posts WHERE id = ? AND type = ?', [(int)$_POST['delete'], $type]);
        q('DELETE FROM posts WHERE id = ? AND type = ?', [(int)$_POST['delete'], $type]);
        log_activity('delete_' . $type, $row['title_hi'] ?? '');
        flash('Delete ho gaya.');
    }
    redirect('/admin/?p=' . $p);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seo_autofix'])) {
    $n = 0;
    foreach (q_all('SELECT * FROM posts WHERE type = ?', [$type]) as $row) {
        $ch = seo_autofix($row);
        if (!$ch) continue;
        q('UPDATE posts SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($ch))) . ' WHERE id = ?', [...array_values($ch), $row['id']]);
        $n++;
    }
    log_activity('seo_autofix', "$type: $n");
    flash($n ? "$n " . $typeLabel[2] . " ka SEO auto-fix ho gaya ✔ Bachi hui kamiyan (image, content, English) haath se theek karni hongi." : 'Auto-fix karne layak kuch nahi mila.');
    redirect('/admin/?p=' . $p);
}

$s = trim((string)($_GET['s'] ?? ''));
$status = in_array($_GET['status'] ?? '', ['published', 'draft'], true) ? $_GET['status'] : '';
$cat = (int)($_GET['cat'] ?? 0);
// Advanced Yojna list filters are GET-only; never modify CMS records.
$sortAllowed = ['newest','published','title','category','status','seo','issues','views','updated'];
$sort = $type === 'yojna' && in_array($_GET['sort'] ?? '', $sortAllowed, true) ? (string)$_GET['sort'] : 'published';
$direction = ($_GET['dir'] ?? '') === 'asc' ? 'asc' : 'desc';
$seoFilter = $type === 'yojna' && in_array($_GET['seo'] ?? '', ['perfect','needs_work','critical'], true) ? (string)$_GET['seo'] : '';
$updatedFilter = $type === 'yojna' && in_array($_GET['changed'] ?? '', ['day','week','month','edited'], true) ? (string)$_GET['changed'] : '';
$minViews = $type === 'yojna' && isset($_GET['min_views']) && ctype_digit((string)$_GET['min_views']) ? min((int)$_GET['min_views'], 10000000) : 0;
$perPageChoices = [10, 20, 50, 100];
$perPageValue = (string)($_GET['per_page'] ?? '20');
$perPage = $type === 'yojna' && $perPageValue === 'all' ? 'all'
    : (in_array((int)$perPageValue, $perPageChoices, true) && ctype_digit($perPageValue) ? (int)$perPageValue : 20);
$pageNumber = $type === 'yojna' && isset($_GET['page_no']) && ctype_digit((string)$_GET['page_no'])
    ? max(1, min((int)$_GET['page_no'], 1000000)) : 1;
$schemeTotals = $type === 'yojna' ? q_one("SELECT COUNT(*) total, SUM(status='published') published, SUM(status='draft') draft FROM posts WHERE type='yojna'") : null;
$where = ['p.type = ?'];
$params = [$type];
if ($s !== '') { $where[] = '(p.title_hi LIKE ? OR p.title_en LIKE ? OR p.slug LIKE ?)'; array_push($params, "%$s%", "%$s%", "%$s%"); }
if ($status) { $where[] = 'p.status = ?'; $params[] = $status; }
if ($cat) { $where[] = ($type === 'blog' ? 'p.blog_cat_id' : 'p.category_id') . ' = ?'; $params[] = $cat; }
$catJoin = $type === 'blog' ? 'LEFT JOIN blog_categories c ON c.id = p.blog_cat_id' : 'LEFT JOIN categories c ON c.id = p.category_id';
$rows = q_all('SELECT p.*, c.name_hi cat_name, u.name author,
    (SELECT COUNT(*) FROM visits v WHERE v.path IN (CONCAT(?, p.slug), CONCAT(\'/hi\', ?, p.slug), CONCAT(\'/en\', ?, p.slug)) AND v.ts >= ?) views30
    FROM posts p ' . $catJoin . ' LEFT JOIN users u ON u.id = p.author_id
    WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . ($type === 'blog' ? 'p.created_at DESC, p.id DESC' : ($type === 'yojna' ? "CASE WHEN p.status = 'published' THEN 0 ELSE 1 END, p.created_at DESC, p.id DESC" : 'p.sort, p.id')), array_merge(array_fill(0, 3, post_path($type, '')), [date('Y-m-d', strtotime('-30 days'))], $params));

// Compute SEO once per row. The SEO and issue filters depend on seo_analyze(),
// so they must use the same current checks shown in the table.
if ($type === 'yojna') {
    foreach ($rows as &$row) {
        $analysis = seo_analyze($row);
        $row['_seo_analysis'] = $analysis;
        $row['_seo_score'] = (int)$analysis['score'];
        $row['_seo_issues'] = count(array_filter($analysis['checks'], static fn($check) => $check['ok'] !== true));
    }
    unset($row);
    if ($seoFilter !== '') {
        $rows = array_values(array_filter($rows, static function ($row) use ($seoFilter) {
            return match ($seoFilter) {
                'perfect' => $row['_seo_issues'] === 0,
                'needs_work' => $row['_seo_issues'] > 0,
                'critical' => $row['_seo_score'] < 70,
                default => true,
            };
        }));
    }
    if ($updatedFilter !== '') {
        $cutoff = match ($updatedFilter) {
            'day' => strtotime('-1 day'), 'week' => strtotime('-7 days'),
            'month' => strtotime('-30 days'), default => 0,
        };
        $rows = array_values(array_filter($rows, static function ($row) use ($updatedFilter, $cutoff) {
            $updated = strtotime((string)$row['updated_at']);
            return $updatedFilter === 'edited'
                ? $updated > strtotime((string)$row['created_at'])
                : $updated >= $cutoff;
        }));
    }
    if ($minViews) $rows = array_values(array_filter($rows, static fn($row) => (int)$row['views30'] >= $minViews));

    $sortValue = static function (array $row) use ($sort): mixed {
        return match ($sort) {
            'title' => mb_strtolower((string)$row['title_hi'], 'UTF-8'),
            'category' => mb_strtolower((string)($row['cat_name'] ?? ''), 'UTF-8'),
            'status' => $row['status'] === 'published' ? 1 : 0,
            'seo' => $row['_seo_score'],
            'issues' => $row['_seo_issues'],
            'views' => (int)$row['views30'],
            'updated' => strtotime((string)$row['updated_at']),
            default => strtotime((string)$row['created_at']),
        };
    };
    usort($rows, static function ($a, $b) use ($sortValue, $sort, $direction) {
        if ($sort === 'published') {
            $priority = (int)($b['status'] === 'published') <=> (int)($a['status'] === 'published');
            if ($priority !== 0) return $priority;
        }
        $aVal = $sortValue($a);
        $bVal = $sortValue($b);
        $diff = $aVal <=> $bVal;
        if ($diff !== 0) return $direction === 'asc' ? $diff : -$diff;
        return (int)$b['id'] <=> (int)$a['id'];
    });
}

// Pagination runs AFTER the combined status/category/SEO/views filters and sorting.
$filteredCount = count($rows);
$pageCount = $type === 'yojna' && $perPage !== 'all' ? max(1, (int)ceil($filteredCount / $perPage)) : 1;
$pageNumber = min($pageNumber, $pageCount);
$firstRow = $filteredCount === 0 ? 0 : ($perPage === 'all' ? 1 : ($pageNumber - 1) * $perPage + 1);
$lastRow = $perPage === 'all' ? $filteredCount : min($pageNumber * $perPage, $filteredCount);
if ($type === 'yojna' && $perPage !== 'all') $rows = array_slice($rows, ($pageNumber - 1) * $perPage, $perPage);

$pageLink = static function (int $n) use ($p): string {
    $query = $_GET;
    $query['p'] = $p;
    $query['page_no'] = $n;
    return '/admin/?' . e(http_build_query($query));
};

$sortHeader = static function (string $key, string $label) use ($p, $type, $sort, $direction): string {
    if ($type !== 'yojna') return e($label);
    $query = $_GET;
    $query['p'] = $p;
    $query['sort'] = $key;
    $query['dir'] = $sort === $key && $direction === 'desc' ? 'asc' : 'desc';
    unset($query['delete'], $query['seo_autofix'], $query['page_no']);
    $arrow = $sort === $key ? ($direction === 'asc' ? ' ↑' : ' ↓') : ' ↕';
    return '<a class="admin-sort-head" href="/admin/?' . e(http_build_query($query)) . '">' . e($label . $arrow) . '</a>';
};

admin_header($typeLabel[0], $p, '<a class="btn primary" href="/admin/?p=post_edit&type=' . $type . '">+ ' . $typeLabel[1] . '</a>');
?>
<?php if ($type === 'page'): ?>
<div class="card">
  <h2>📄 Pages — SEO Refresh (Preview)</h2>
  <p class="muted">About, Privacy Policy aur Disclaimer ke liye reviewed Hindi-English metadata. In legal pages par 600+ words ya featured image SEO requirement nahi hai. Legal content aur public URLs untouched rahenge.</p>
  <div class="tbl-wrap"><table class="tbl small">
    <thead><tr><th>Existing slug</th><th>Hindi SEO title</th><th>English SEO title</th><th>New meta descriptions</th></tr></thead>
    <tbody>
    <?php foreach (esy_page_seo_recommendations() as $slug => $meta): ?>
      <tr><td><code><?= e($slug) ?></code></td><td><?= e($meta['seo_title']) ?></td><td><?= e($meta['seo_title_en']) ?></td>
      <td><?= e($meta['seo_desc']) ?><hr><?= e($meta['seo_desc_en']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <form method="post" onsubmit="return confirm('Sirf existing published About, Privacy Policy aur Disclaimer pages ke SEO title aur description update karne hain? Legal content, URL aur status bilkul nahi badlenge.')">
    <?= csrf_field() ?>
    <button name="apply_page_seo" value="1" class="btn primary">Apply Reviewed Page SEO Metadata</button>
  </form>
</div>
<?php endif; ?>
<form method="post" class="card form-row" style="justify-content:space-between;align-items:center">
  <?= csrf_field() ?>
  <span>⚡ <b>SEO auto-fix:</b> SEO title, meta description, keywords aur image alt text apne aap theek kar deta hai. URL aur likha hua content nahi badalta.</span>
  <button class="btn primary" name="seo_autofix" value="1" onclick="return confirm('Saare <?= $typeLabel[2] ?> ka SEO auto-fix karein?')">⚡ Sab ka SEO theek karein</button>
</form>
<form class="filters card" method="get">
  <input type="hidden" name="p" value="<?= e($p) ?>">
  <input type="search" name="s" value="<?= e($s) ?>" placeholder="Title ya slug se khojein...">
  <select name="status"><option value="">Sab status</option><option value="published"<?= $status === 'published' ? ' selected' : '' ?>>Published</option><option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>Draft</option></select>
  <?php if ($type === 'blog'): ?>
  <select name="cat"><option value="0">Sab categories</option><?php foreach (blog_cat_tree() as $c): ?><option value="<?= $c['id'] ?>"<?= $cat === (int)$c['id'] ? ' selected' : '' ?>><?= str_repeat('— ', $c['depth']) . e($c['name_hi']) ?></option><?php endforeach; ?></select>
  <?php elseif ($type === 'yojna'): ?>
  <select name="cat"><option value="0">Sab categories</option><?php foreach (categories() as $c): ?><option value="<?= $c['id'] ?>"<?= $cat === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['icon'] . ' ' . $c['name_hi']) ?></option><?php endforeach; ?></select>
  <?php endif; ?>
  <?php if ($type === 'yojna'): ?>
  <select name="sort" aria-label="Sort Yojnayein">
    <?php foreach (['published'=>'Latest published first','newest'=>'Newest created','updated'=>'Recently updated','title'=>'Title','category'=>'Category','status'=>'Status','seo'=>'SEO score','issues'=>'SEO issues','views'=>'Views (30 days)'] as $key => $label): ?>
    <option value="<?= e($key) ?>"<?= $sort === $key ? ' selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="dir" aria-label="Sort direction"><option value="desc"<?= $direction === 'desc' ? ' selected' : '' ?>>Descending</option><option value="asc"<?= $direction === 'asc' ? ' selected' : '' ?>>Ascending</option></select>
  <select name="seo" aria-label="SEO health"><option value="">All SEO scores</option><option value="perfect"<?= $seoFilter === 'perfect' ? ' selected' : '' ?>>SEO: sab theek</option><option value="needs_work"<?= $seoFilter === 'needs_work' ? ' selected' : '' ?>>SEO: kami baaki</option><option value="critical"<?= $seoFilter === 'critical' ? ' selected' : '' ?>>SEO: score under 70</option></select>
  <select name="changed" aria-label="Last updated"><option value="">Any update date</option><option value="day"<?= $updatedFilter === 'day' ? ' selected' : '' ?>>Updated last 24h</option><option value="week"<?= $updatedFilter === 'week' ? ' selected' : '' ?>>Updated last 7 days</option><option value="month"<?= $updatedFilter === 'month' ? ' selected' : '' ?>>Updated last 30 days</option><option value="edited"<?= $updatedFilter === 'edited' ? ' selected' : '' ?>>Edited since creation</option></select>
  <input type="number" name="min_views" min="0" max="10000000" style="width:125px;flex:0 0 125px" value="<?= $minViews ?: '' ?>" placeholder="Min views" aria-label="Minimum views in last 30 days">
  <?php endif; ?>
  <button class="btn sm">Filter</button>
  <?php if ($type === 'yojna'): ?>
    <label for="yojna-per-page" class="muted" style="font-weight:700">Rows per page</label>
    <select id="yojna-per-page" name="per_page" aria-label="Rows per page">
      <?php foreach (['10'=>'10','20'=>'20','50'=>'50','100'=>'100','all'=>'All'] as $value => $label): ?>
        <option value="<?= e((string)$value) ?>"<?= (string)$perPage === (string)$value ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <a class="btn ghost sm" href="/admin/?p=posts">Reset</a>
  <?php endif; ?>
</form>
<?php if ($type === 'yojna'): ?>
<div class="card yojna-list-summary" role="status" aria-live="polite">
  <div class="yojna-list-totals">
    <span><b><?= (int)($schemeTotals['total'] ?? 0) ?></b> Total Yojnayein</span>
    <span><b><?= (int)($schemeTotals['published'] ?? 0) ?></b> Published</span>
    <span><b><?= (int)($schemeTotals['draft'] ?? 0) ?></b> Draft</span>
    <span><b><?= $filteredCount ?></b> Filtered results</span>
  </div>
  <div class="muted">Showing <strong><?= $firstRow ?>–<?= $lastRow ?></strong> of <strong><?= $filteredCount ?></strong><?= $perPage !== 'all' ? ' · Page ' . $pageNumber . ' of ' . $pageCount : ' · All rows' ?></div>
</div>
<?php endif; ?>
<div class="card"><div class="tbl-wrap"><table class="tbl">
  <thead><tr><th><?= $sortHeader('title', 'Title') ?></th><?php if ($type !== 'page'): ?><th><?= $sortHeader('category', 'Category') ?></th><?php endif; ?><th><?= $sortHeader('status', 'Status') ?></th><th class="center"><?= $sortHeader('seo', 'SEO') ?></th><th><?= $sortHeader('issues', 'SEO me kya kami hai') ?></th><th class="num"><?= $sortHeader('views', 'Views (30 din)') ?></th><th><?= $sortHeader('updated', 'Update') ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $url = '/hi' . post_path($type, $r['slug']); ?>
    <tr>
      <td><a class="strong" href="/admin/?p=post_edit&type=<?= $type ?>&id=<?= $r['id'] ?>"><?= e(trim($r['icon'] . ' ' . $r['title_hi'])) ?></a><?= $r['featured'] ? ' <span class="badge star">⭐ Featured</span>' : '' ?><br><small class="muted"><?= e($url) ?></small></td>
      <?php if ($type !== 'page'): ?><td><?= e($r['cat_name'] ?? '—') ?><?= $type === 'blog' ? '<br><small class="muted">' . e(date('d M Y', strtotime($r['created_at']))) . '</small>' : '' ?></td><?php endif; ?>
      <td><span class="badge <?= $r['status'] === 'published' ? 'green' : 'gray' ?>"><?= $r['status'] === 'published' ? 'Live' : 'Draft' ?></span></td>
      <?php $seo = $r['_seo_analysis'] ?? seo_analyze($r); $issues = array_filter($seo['checks'], fn($c) => $c['ok'] !== true); ?>
      <td class="center"><a href="/admin/?p=post_edit&type=<?= $type ?>&id=<?= $r['id'] ?>#seo"><?= seo_badge($seo['score']) ?></a></td>
      <td class="seo-issues">
        <?php if (!$issues): ?><span class="muted">✅ Sab theek</span><?php endif; ?>
        <?php foreach ($issues as $c): ?><div class="<?= $c['ok'] === null ? 'mid' : 'bad' ?>"><b><?= e($c['label']) ?></b> — <?= e($c['tip']) ?></div><?php endforeach; ?>
      </td>
      <td class="num"><?= (int)$r['views30'] ?></td>
      <td><small><?= e(time_ago($r['updated_at'])) ?><?= $r['author'] ? '<br>' . e($r['author']) : '' ?></small></td>
      <td class="actions">
        <a class="btn ghost sm" href="/admin/?p=post_edit&type=<?= $type ?>&id=<?= $r['id'] ?>">Edit</a>
        <?php if ($r['status'] === 'published'): ?><a class="btn ghost sm" href="<?= e($url) ?>" target="_blank">View</a><?php endif; ?>
        <?php if ($canDelete): ?><form method="post" onsubmit="return confirm('Pakka delete karna hai?')"><?= csrf_field() ?><button class="btn danger sm" name="delete" value="<?= $r['id'] ?>">Delete</button></form><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="muted">Kuch nahi mila.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php if ($type === 'yojna' && $pageCount > 1): ?>
<nav class="yojna-pages card" aria-label="Yojnayein pagination">
  <span class="muted">Page <?= $pageNumber ?> / <?= $pageCount ?></span>
  <div class="yojna-pages-links">
    <a class="btn ghost sm" href="<?= $pageLink(1) ?>"<?= $pageNumber === 1 ? ' aria-disabled="true" tabindex="-1"' : '' ?>>« First</a>
    <a class="btn ghost sm" href="<?= $pageLink(max(1,$pageNumber - 1)) ?>"<?= $pageNumber === 1 ? ' aria-disabled="true" tabindex="-1"' : '' ?>>‹ Previous</a>
    <?php for ($n = max(1, $pageNumber - 2); $n <= min($pageCount, $pageNumber + 2); $n++): ?>
      <a class="btn <?= $n === $pageNumber ? 'primary' : 'ghost' ?> sm" href="<?= $pageLink($n) ?>"<?= $n === $pageNumber ? ' aria-current="page"' : '' ?>><?= $n ?></a>
    <?php endfor; ?>
    <a class="btn ghost sm" href="<?= $pageLink(min($pageCount,$pageNumber + 1)) ?>"<?= $pageNumber === $pageCount ? ' aria-disabled="true" tabindex="-1"' : '' ?>>Next ›</a>
    <a class="btn ghost sm" href="<?= $pageLink($pageCount) ?>"<?= $pageNumber === $pageCount ? ' aria-disabled="true" tabindex="-1"' : '' ?>>Last »</a>
  </div>
</nav>
<?php endif; ?>
<?php admin_footer();
