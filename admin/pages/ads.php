<?php
defined('ESY') or exit;

$slots = ad_slots();
$canHtml = can('code.manage'); // HTML/script ads run on every visitor's browser: trusted admins only
$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, [7, 30, 90, 365], true)) $days = 30;
$from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        $id = (int)$_POST['delete'];
        q('DELETE FROM ad_events WHERE ad_id = ?', [$id]);
        q('DELETE FROM ads WHERE id = ?', [$id]);
        log_activity('delete_ad', (string)$id);
        flash('Ad delete ho gaya.');
        redirect('/admin/?p=ads');
    }
    if (isset($_POST['toggle'])) {
        q('UPDATE ads SET active = 1 - active, updated_at = ? WHERE id = ?', [now(), (int)$_POST['toggle']]);
        redirect('/admin/?p=ads');
    }
    $id = (int)($_POST['id'] ?? 0);
    $old = $id ? q_one('SELECT * FROM ads WHERE id = ?', [$id]) : null;
    $d = [
        'name' => mb_substr(trim((string)($_POST['name'] ?? '')), 0, 160),
        'slot' => array_key_exists($_POST['slot'] ?? '', $slots) ? $_POST['slot'] : 'header',
        'type' => ($_POST['type'] ?? '') === 'html' ? 'html' : 'image',
        'image' => trim((string)($_POST['image'] ?? '')),
        'link' => trim((string)($_POST['link'] ?? '')) !== '' ? safe_url(trim((string)$_POST['link'])) : '',
        'alt' => mb_substr(trim((string)($_POST['alt'] ?? '')), 0, 255),
        'html' => (string)($_POST['html'] ?? ''),
        'lang' => in_array($_POST['lang'] ?? '', ['hi', 'en'], true) ? $_POST['lang'] : 'all',
        'device' => in_array($_POST['device'] ?? '', ['mobile', 'desktop'], true) ? $_POST['device'] : 'all',
        'active' => ($_POST['active'] ?? '') === '1' ? 1 : 0,
        'start_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['start_date'] ?? '') ? $_POST['start_date'] : null,
        'end_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['end_date'] ?? '') ? $_POST['end_date'] : null,
        'weight' => max(1, min(100, (int)($_POST['weight'] ?? 1))),
    ];
    $d = apply_uploads($d, ['image']);
    if (!$canHtml) { // keep whatever HTML a code admin saved; never accept new HTML from this user
        $d['html'] = $old['html'] ?? '';
        if ($d['type'] === 'html' && ($old['type'] ?? '') !== 'html') $d['type'] = 'image';
    }
    if ($d['name'] === '') $d['name'] = $slots[$d['slot']];
    if ($d['type'] === 'image' && $d['image'] === '') {
        flash('Image ad ke liye image upload karein ya URL daalein.', 'err');
        redirect('/admin/?p=ads&edit=' . ($id ?: 'new'));
    }
    if ($id) {
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($d)));
        q("UPDATE ads SET $set, updated_at = ? WHERE id = ?", array_merge(array_values($d), [now(), $id]));
        log_activity('update_ad', $d['name']);
    } else {
        $cols = implode(', ', array_keys($d));
        q("INSERT INTO ads ($cols, created_by, created_at, updated_at) VALUES (" . implode(',', array_fill(0, count($d), '?')) . ', ?, ?, ?)',
          array_merge(array_values($d), [current_user()['id'], now(), now()]));
        $id = (int)db()->lastInsertId();
        log_activity('create_ad', $d['name']);
    }
    flash('Ad save ho gaya ✔');
    redirect('/admin/?p=ads');
}

/* ---------- edit form ---------- */
if (isset($_GET['edit'])) {
    $ad = $_GET['edit'] === 'new' ? null : q_one('SELECT * FROM ads WHERE id = ?', [(int)$_GET['edit']]);
    $ad = $ad ?: ['id' => 0, 'name' => '', 'slot' => $_GET['slot'] ?? 'header', 'type' => 'image', 'image' => '', 'link' => '', 'alt' => '', 'html' => '',
                  'lang' => 'all', 'device' => 'all', 'active' => 1, 'start_date' => '', 'end_date' => '', 'weight' => 1];
    admin_header($ad['id'] ? 'Ad edit: ' . $ad['name'] : 'Naya Ad', 'ads', '<a class="btn ghost" href="/admin/?p=ads">← Sabhi ads</a>');
    ?>
<form method="post" enctype="multipart/form-data" class="card settings-form">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$ad['id'] ?>">
  <div class="grid2">
    <?= f_text('name', 'Ad ka naam (aapki pehchan ke liye)', $ad['name'], ['placeholder' => 'Diwali offer banner']) ?>
    <?= f_select('slot', 'Kahan dikhana hai (slot)', $ad['slot'], $slots) ?>
  </div>
  <?= f_select('type', 'Ad type', $ad['type'], ['image' => 'Image / banner (click track hota hai)'] + ($canHtml || $ad['type'] === 'html' ? ['html' => 'HTML / AdSense code'] : []),
      ['help' => $canHtml ? 'AdSense/ad network code ke liye HTML chunein.' : 'HTML ads sirf "Code & Tracking" permission wale admin bana sakte hain.']) ?>
  <div data-show="image">
    <?= f_image('image', 'Banner image', $ad['image']) ?>
    <div class="grid2">
      <?= f_text('link', 'Click karne par kahan jaye (URL)', $ad['link'], ['placeholder' => 'https://advertiser.com/offer']) ?>
      <?= f_text('alt', 'Image ka alt text', $ad['alt'], ['placeholder' => 'Offer ka naam']) ?>
    </div>
    <p class="muted">Size idea: header/footer 728×90 ya 970×90, content 728×90 / 336×280, sidebar 300×250 ya 300×600, mobile sticky 320×50.</p>
  </div>
  <div data-show="html">
    <?php if ($canHtml): ?>
      <?= f_area('html', 'HTML / script code', $ad['html'], ['rows' => 8, 'class' => 'code', 'help' => 'Views apne aap count honge. Normal links ke clicks bhi count honge; AdSense jaise iframe ads ke clicks ad network ke dashboard me hi dikhte hain.']) ?>
    <?php else: ?>
      <p class="muted">HTML code sirf trusted admin badal sakta hai.</p>
    <?php endif; ?>
  </div>
  <div class="grid3">
    <?= f_select('lang', 'Bhasha', $ad['lang'], ['all' => 'Dono (हिंदी + English)', 'hi' => 'Sirf हिंदी pages (/hi/)', 'en' => 'Sirf English pages (/en/)']) ?>
    <?= f_select('device', 'Device', $ad['device'], ['all' => 'Sabhi', 'mobile' => 'Sirf mobile', 'desktop' => 'Sirf desktop']) ?>
    <?= f_text('weight', 'Weight (ek slot me kai ads ho to)', $ad['weight'], ['type' => 'number', 'min' => 1, 'max' => 100, 'help' => 'Zyada weight = zyada baar dikhega.']) ?>
  </div>
  <div class="grid3">
    <?= f_text('start_date', 'Shuru (optional)', $ad['start_date'] ?? '', ['type' => 'date']) ?>
    <?= f_text('end_date', 'Khatam (optional)', $ad['end_date'] ?? '', ['type' => 'date']) ?>
    <div class="field"><span>Status</span><?= f_check('active', 'Ad chalu hai', (int)$ad['active']) ?></div>
  </div>
  <button class="btn primary">💾 Save</button>
</form>
<script>
(function () {
  var sel = document.querySelector('select[name="type"]');
  function show() { document.querySelectorAll('[data-show]').forEach(function (b) { b.hidden = b.dataset.show !== sel.value; }); }
  sel.addEventListener('change', show); show();
})();
</script>
<?php
    admin_footer();
    return;
}

/* ---------- list + stats ---------- */
$stats = [];
foreach (q_all("SELECT ad_id, SUM(event = 'view') v, SUM(event = 'click') c FROM ad_events WHERE ts >= ? GROUP BY ad_id", [$from]) as $r) $stats[$r['ad_id']] = $r;
$ads = q_all('SELECT * FROM ads ORDER BY active DESC, slot, id DESC');
$tot = q_one("SELECT SUM(event = 'view') v, SUM(event = 'click') c FROM ad_events WHERE ts >= ?", [$from]);
$ctr = fn($v, $c) => $v > 0 ? round($c * 100 / $v, 2) . '%' : '—';
$adId = (int)($_GET['ad'] ?? 0);
$where = 'ts >= ?' . ($adId ? ' AND ad_id = ?' : '');
$args = $adId ? [$from, $adId] : [$from];
$daily = q_all("SELECT DATE(ts) d, SUM(event = 'view') v, SUM(event = 'click') c FROM ad_events WHERE $where GROUP BY DATE(ts) ORDER BY d DESC LIMIT 60", $args);
$by = fn($col) => q_all("SELECT $col k, SUM(event = 'view') v, SUM(event = 'click') c FROM ad_events WHERE $where GROUP BY $col ORDER BY v DESC LIMIT 10", $args);
$used = array_count_values(array_column(array_filter($ads, fn($a) => $a['active']), 'slot'));

admin_header('Ads', 'ads', '<a class="btn primary" href="/admin/?p=ads&edit=new">+ Naya Ad</a>');
?>
<div class="tabs">
  <?php foreach ([7, 30, 90, 365] as $dd): ?><a href="/admin/?p=ads&days=<?= $dd ?><?= $adId ? '&ad=' . $adId : '' ?>" class="<?= $days === $dd ? 'on' : '' ?>"><?= $dd ?> din</a><?php endforeach; ?>
</div>
<div class="kpis">
  <div class="kpi"><span>Views (<?= $days ?> din)</span><b><?= (int)$tot['v'] ?></b></div>
  <div class="kpi"><span>Clicks</span><b><?= (int)$tot['c'] ?></b></div>
  <div class="kpi"><span>CTR</span><b><?= $ctr((int)$tot['v'], (int)$tot['c']) ?></b></div>
  <div class="kpi"><span>Chalu ads</span><b><?= count(array_filter($ads, fn($a) => $a['active'])) ?></b></div>
</div>

<div class="card"><div class="tbl-wrap"><table class="tbl">
  <thead><tr><th>Ad</th><th>Slot</th><th>Bhasha / Device</th><th class="num">Views</th><th class="num">Clicks</th><th class="num">CTR</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($ads as $a): $s = $stats[$a['id']] ?? ['v' => 0, 'c' => 0];
    $expired = $a['end_date'] && $a['end_date'] < date('Y-m-d'); ?>
    <tr>
      <td><?php if ($a['type'] === 'image' && $a['image']): ?><img src="<?= e($a['image']) ?>" alt="" style="height:34px;max-width:120px;object-fit:contain;vertical-align:middle;margin-right:.5rem"><?php endif; ?>
        <a class="strong" href="/admin/?p=ads&edit=<?= $a['id'] ?>"><?= e($a['name']) ?></a><br><small class="muted"><?= $a['type'] === 'html' ? 'HTML code' : e($a['link'] ?: 'link nahi') ?></small></td>
      <td><small><?= e($slots[$a['slot']] ?? $a['slot']) ?></small></td>
      <td><small><?= ['all' => 'हिं + EN', 'hi' => 'हिंदी', 'en' => 'English'][$a['lang']] ?> · <?= ['all' => 'Sabhi', 'mobile' => 'Mobile', 'desktop' => 'Desktop'][$a['device']] ?></small></td>
      <td class="num"><a href="/admin/?p=ads&days=<?= $days ?>&ad=<?= $a['id'] ?>#report"><?= (int)$s['v'] ?></a></td>
      <td class="num"><?= (int)$s['c'] ?></td>
      <td class="num"><?= $ctr((int)$s['v'], (int)$s['c']) ?></td>
      <td><?php if ($expired): ?><span class="badge">Khatam</span><?php elseif ($a['active']): ?><span class="badge ok">Chalu</span><?php else: ?><span class="badge">Band</span><?php endif; ?></td>
      <td class="actions">
        <form method="post"><?= csrf_field() ?><button class="btn ghost sm" name="toggle" value="<?= $a['id'] ?>"><?= $a['active'] ? 'Band karein' : 'Chalu karein' ?></button></form>
        <a class="btn ghost sm" href="/admin/?p=ads&edit=<?= $a['id'] ?>">Edit</a>
        <form method="post" onsubmit="return confirm('Ad aur uska data delete karein?')"><?= csrf_field() ?><button class="btn danger sm" name="delete" value="<?= $a['id'] ?>">✕</button></form>
      </td>
    </tr>
  <?php endforeach; if (!$ads): ?><tr><td colspan="8" class="muted">Abhi koi ad nahi. "+ Naya Ad" se banaiye.</td></tr><?php endif; ?>
  </tbody></table></div></div>

<div class="card">
  <h2>Ad slots (website par jagah)</h2>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Slot</th><th class="num">Chalu ads</th><th></th></tr></thead><tbody>
    <?php foreach ($slots as $k => $label): ?>
      <tr><td><?= e($label) ?> <small class="muted">(<?= e($k) ?>)</small></td><td class="num"><?= $used[$k] ?? 0 ?></td>
        <td class="actions"><a class="btn ghost sm" href="/admin/?p=ads&edit=new&slot=<?= e($k) ?>">+ Ad lagayein</a></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
</div>

<div class="card" id="report">
  <h2>Report <?= $adId ? '— ' . e(q_val('SELECT name FROM ads WHERE id = ?', [$adId]) ?? '') . ' <a class="btn ghost sm" href="/admin/?p=ads&days=' . $days . '#report">Sabhi ads</a>' : '(sabhi ads)' ?></h2>
  <div class="grid2">
    <div><h3>Din ke hisab se</h3><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Din</th><th class="num">Views</th><th class="num">Clicks</th><th class="num">CTR</th></tr></thead><tbody>
      <?php foreach ($daily as $r): ?><tr><td><?= e(date('d M Y', strtotime($r['d']))) ?></td><td class="num"><?= (int)$r['v'] ?></td><td class="num"><?= (int)$r['c'] ?></td><td class="num"><?= $ctr((int)$r['v'], (int)$r['c']) ?></td></tr><?php endforeach;
      if (!$daily): ?><tr><td colspan="4" class="muted">Abhi data nahi.</td></tr><?php endif; ?>
    </tbody></table></div></div>
    <div>
      <?php foreach (['slot' => 'Slot', 'lang' => 'Bhasha', 'device' => 'Device', 'city' => 'City', 'page' => 'Page'] as $col => $label): $rows = $by($col); if (!$rows) continue; ?>
        <h3><?= $label ?></h3>
        <div class="tbl-wrap"><table class="tbl"><thead><tr><th><?= $label ?></th><th class="num">Views</th><th class="num">Clicks</th></tr></thead><tbody>
          <?php foreach ($rows as $r): ?><tr><td class="ellip"><?= e(($col === 'slot' ? ($slots[$r['k']] ?? $r['k']) : $r['k']) ?: '—') ?></td><td class="num"><?= (int)$r['v'] ?></td><td class="num"><?= (int)$r['c'] ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      <?php endforeach; ?>
    </div>
  </div>
  <p class="muted">View tab count hota hai jab ad ka aadha hissa screen par dikhe. Bots aur logged-in admin count nahi hote. Image ad ka har click count hota hai; AdSense jaise iframe ads ke clicks unke apne dashboard me dekhein.</p>
</div>
<?php admin_footer();
