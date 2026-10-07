<?php
defined('ESY') or exit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        q('DELETE FROM links WHERE id = ?', [(int)$_POST['delete']]);
        flash('Link delete ho gaya.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $target = safe_url(trim((string)($_POST['target'] ?? '')));
        $slug = slugify(trim((string)($_POST['slug'] ?? '')) ?: bin2hex(random_bytes(3)));
        $title = trim((string)($_POST['title'] ?? ''));
        if ($target === '' || $target === '#') flash('Target URL zaroori hai.', 'err');
        elseif (q_val('SELECT id FROM links WHERE slug = ? AND id <> ?', [$slug, $id])) flash('Ye short naam pehle se hai.', 'err');
        elseif ($id) { q('UPDATE links SET slug=?, target=?, title=? WHERE id=?', [$slug, $target, $title, $id]); flash('Update ho gaya ✔'); }
        else { q('INSERT INTO links (slug, target, title, created_by) VALUES (?,?,?,?)', [$slug, $target, $title, current_user()['id']]); flash('Tracking link ban gaya ✔'); log_activity('create_link', $slug); }
    }
    redirect('/admin/?p=links');
}

$base = rtrim(settings()['site_url'], '/');
$rows = q_all("SELECT l.*, (SELECT COUNT(*) FROM clicks c WHERE c.link_id = l.id) total,
    (SELECT COUNT(*) FROM clicks c WHERE c.link_id = l.id AND c.ts >= ?) week,
    (SELECT MAX(ts) FROM clicks c WHERE c.link_id = l.id) last_click FROM links l ORDER BY l.id DESC", [date('Y-m-d', strtotime('-6 days'))]);
$edit = isset($_GET['edit']) ? q_one('SELECT * FROM links WHERE id = ?', [(int)$_GET['edit']]) : null;
admin_header('Tracking Links', 'links');
?>
<div class="card">
  <p class="muted">Yahan short link banaiye, jaise <code><?= e($base) ?>/go/<b>pm-kisan-apply</b></code>. Isse WhatsApp, Facebook, YouTube ya site ke andar kahin bhi lagaiye. Har click ka samay, location, device aur IP save hoga. Website ke baaki saare links ke clicks bhi apne aap track hote hain (Analytics → Link clicks).</p>
  <form method="post" class="form-row">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <?= f_text('title', 'Naam (aapki pehchan ke liye)', $edit['title'] ?? '', ['placeholder' => 'PM Kisan apply button']) ?>
    <?= f_text('target', 'Kahan bhejna hai (target URL) *', $edit['target'] ?? '', ['placeholder' => 'https://pmkisan.gov.in', 'required' => true]) ?>
    <?= f_text('slug', 'Short naam', $edit['slug'] ?? '', ['placeholder' => 'auto']) ?>
    <button class="btn primary"><?= $edit ? 'Update' : '+ Link banayein' ?></button>
  </form>
</div>
<div class="card"><div class="tbl-wrap"><table class="tbl">
  <thead><tr><th>Link</th><th>Target</th><th class="num">Kul clicks</th><th class="num">7 din</th><th>Aakhri click</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): $short = $base . '/go/' . $r['slug']; ?>
    <tr>
      <td><b><?= e($r['title'] ?: $r['slug']) ?></b><br><input readonly class="copy" value="<?= e($short) ?>" onclick="this.select();navigator.clipboard&&navigator.clipboard.writeText(this.value)"></td>
      <td class="ellip"><a href="<?= e($r['target']) ?>" target="_blank" rel="noopener"><?= e($r['target']) ?></a></td>
      <td class="num"><a href="/admin/?p=analytics&tab=clicks&from=2000-01-01&f_url=<?= e(urlencode($r['target'])) ?>"><?= $r['total'] ?></a></td>
      <td class="num"><?= $r['week'] ?></td>
      <td><small><?= $r['last_click'] ? e(time_ago($r['last_click'])) : '—' ?></small></td>
      <td class="actions"><a class="btn ghost sm" href="/admin/?p=links&edit=<?= $r['id'] ?>">Edit</a>
        <form method="post" onsubmit="return confirm('Delete?')"><?= csrf_field() ?><button class="btn danger sm" name="delete" value="<?= $r['id'] ?>">✕</button></form></td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="muted">Abhi koi tracking link nahi.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php admin_footer();
