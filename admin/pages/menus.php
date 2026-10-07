<?php
defined('ESY') or exit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loc = ($_POST['location'] ?? '') === 'footer' ? 'footer' : 'header';
    db()->beginTransaction();
    q('DELETE FROM menus WHERE location = ?', [$loc]);
    $items = $_POST['items'] ?? [];
    $i = 0;
    foreach ((array)$items as $it) {
        $lh = trim((string)($it['label_hi'] ?? ''));
        $url = trim((string)($it['url'] ?? ''));
        if ($lh === '' || $url === '') continue;
        if (safe_url($url) === '#' && $url !== '#') { flash("\"$lh\" ka link sahi nahi tha, isliye chhod diya.", 'err'); continue; }
        q('INSERT INTO menus (location, label_hi, label_en, url, new_tab, sort) VALUES (?,?,?,?,?,?)', [$loc, $lh, trim((string)($it['label_en'] ?? '')), safe_url($url), !empty($it['new_tab']) ? 1 : 0, $i++]);
    }
    db()->commit();
    log_activity('update_menu', $loc);
    flash(ucfirst($loc) . ' menu save ho gaya ✔');
    redirect('/admin/?p=menus');
}

$quick = [];
foreach (q_all("SELECT type, slug, title_hi FROM posts WHERE status = 'published' ORDER BY type, sort") as $r) $quick[] = [($r['type'] === 'yojna' ? '/yojna/' : '/page/') . $r['slug'], $r['title_hi']];
foreach (categories() as $c) $quick[] = ['/category/' . $c['slug'], 'Category: ' . $c['name_hi']];

admin_header('Menus', 'menus');
?>
<datalist id="urls"><?php foreach ($quick as [$u, $t]): ?><option value="<?= e($u) ?>"><?= e($t) ?></option><?php endforeach; ?></datalist>
<div class="grid2">
<?php foreach (['header' => 'Header menu (upar)', 'footer' => 'Footer links (neeche)'] as $loc => $title): ?>
  <form method="post" class="card menu-editor">
    <?= csrf_field() ?><input type="hidden" name="location" value="<?= $loc ?>">
    <h2><?= $title ?></h2>
    <div class="menu-rows" data-menu-rows>
      <?php foreach (array_merge(menu($loc), [['label_hi' => '', 'label_en' => '', 'url' => '', 'new_tab' => 0]]) as $i => $m): ?>
      <div class="menu-row">
        <span class="drag" title="Upar/neeche">⇅</span>
        <input name="items[<?= $i ?>][label_hi]" value="<?= e($m['label_hi']) ?>" placeholder="Naam (हिंदी)">
        <input name="items[<?= $i ?>][label_en]" value="<?= e($m['label_en']) ?>" placeholder="Name (English)">
        <input name="items[<?= $i ?>][url]" value="<?= e($m['url']) ?>" placeholder="/page/about ya https://..." list="urls">
        <label class="check" title="Naye tab me kholein"><input type="checkbox" name="items[<?= $i ?>][new_tab]" value="1"<?= $m['new_tab'] ? ' checked' : '' ?>>↗</label>
        <button type="button" class="btn ghost sm" data-up>↑</button><button type="button" class="btn ghost sm" data-del>✕</button>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="row-actions"><button type="button" class="btn ghost sm" data-add-row>+ Item jodein</button><button class="btn primary">Save</button></div>
  </form>
<?php endforeach; ?>
</div>
<?php admin_footer();
