<?php
defined('ESY') or exit;

$since = fn(int $days) => date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
$kpi = function (string $from) {
    return q_one('SELECT COUNT(*) views, COUNT(DISTINCT visitor_id) visitors, COUNT(DISTINCT session_id) sessions FROM visits WHERE ts >= ?', [$from])
        + ['clicks' => (int)q_val('SELECT COUNT(*) FROM clicks WHERE ts >= ?', [$from])];
};
$today = $kpi($since(1));
$week = $kpi($since(7));
$month = $kpi($since(30));

$daily = q_all('SELECT DATE(ts) d, COUNT(*) views, COUNT(DISTINCT visitor_id) visitors FROM visits WHERE ts >= ? GROUP BY DATE(ts) ORDER BY d', [$since(30)]);
$dmap = array_column($daily, null, 'd');
$labels = $views = $visitors = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d M', strtotime($d));
    $views[] = (int)($dmap[$d]['views'] ?? 0);
    $visitors[] = (int)($dmap[$d]['visitors'] ?? 0);
}
$topPages = q_all('SELECT path, COUNT(*) n, COUNT(DISTINCT visitor_id) u FROM visits WHERE ts >= ? GROUP BY path ORDER BY n DESC LIMIT 8', [$since(7)]);
$sources = q_all('SELECT source, COUNT(*) n FROM visits WHERE ts >= ? GROUP BY source ORDER BY n DESC LIMIT 8', [$since(7)]);
$devices = q_all('SELECT device, COUNT(*) n FROM visits WHERE ts >= ? GROUP BY device ORDER BY n DESC', [$since(7)]);
$topClicks = q_all('SELECT url, MAX(link_text) txt, COUNT(*) n FROM clicks WHERE ts >= ? GROUP BY url ORDER BY n DESC LIMIT 8', [$since(7)]);
$live = (int)q_val('SELECT COUNT(DISTINCT visitor_id) FROM visits WHERE ts >= ?', [date('Y-m-d H:i:s', time() - 300)]);
$counts = q_one("SELECT SUM(type='yojna' AND status='published') pub, SUM(type='yojna' AND status='draft') draft, SUM(type='page') pages FROM posts");

admin_header('Dashboard', 'dashboard', can('posts.edit') ? '<a class="btn primary" href="/admin/?p=post_edit&type=yojna">+ Nayi Yojna</a>' : '');
if (!can('analytics.view')) {
    $mine = q_all("SELECT id, title_hi, status, updated_at FROM posts WHERE type = 'yojna' AND author_id = ? ORDER BY updated_at DESC LIMIT 10", [current_user()['id']]);
    ?>
<div class="kpis"><div class="kpi"><span>Live yojnayein</span><b><?= (int)$counts['pub'] ?></b></div><div class="kpi"><span>Draft</span><b><?= (int)$counts['draft'] ?></b></div></div>
<div class="card"><h2>Meri yojnayein</h2><table class="tbl"><tbody>
<?php foreach ($mine as $r): ?><tr><td><a href="/admin/?p=post_edit&type=yojna&id=<?= $r['id'] ?>"><?= e($r['title_hi']) ?></a></td><td><span class="badge <?= $r['status'] === 'published' ? 'green' : 'gray' ?>"><?= e($r['status']) ?></span></td><td><small><?= e(time_ago($r['updated_at'])) ?></small></td></tr><?php endforeach; ?>
<?php if (!$mine): ?><tr><td class="muted">Abhi koi nahi. "+ Nayi Yojna" se shuru karein.</td></tr><?php endif; ?>
</tbody></table></div>
<?php
    admin_footer();
    return;
}
?>
<div class="kpis">
  <div class="kpi live"><span>Abhi online (5 min)</span><b><?= $live ?></b></div>
  <div class="kpi"><span>Aaj ke visitors</span><b><?= number_format($today['visitors']) ?></b><small><?= number_format($today['views']) ?> page views</small></div>
  <div class="kpi"><span>7 din visitors</span><b><?= number_format($week['visitors']) ?></b><small><?= number_format($week['views']) ?> views · <?= number_format($week['clicks']) ?> clicks</small></div>
  <div class="kpi"><span>30 din visitors</span><b><?= number_format($month['visitors']) ?></b><small><?= number_format($month['views']) ?> views · <?= number_format($month['clicks']) ?> clicks</small></div>
  <div class="kpi"><span>Content</span><b><?= (int)$counts['pub'] ?></b><small>yojna live · <?= (int)$counts['draft'] ?> draft · <?= (int)$counts['pages'] ?> pages</small></div>
</div>

<div class="card">
  <div class="card-head"><h2>Pichhle 30 din</h2><?php if (can('analytics.view')): ?><a href="/admin/?p=analytics">Poori analytics →</a><?php endif; ?></div>
  <div class="chart-box"><canvas id="trend"></canvas></div>
</div>

<div class="grid2">
  <div class="card"><h2>Top pages (7 din)</h2>
    <table class="tbl"><thead><tr><th>Page</th><th class="num">Views</th><th class="num">Visitors</th></tr></thead><tbody>
    <?php foreach ($topPages as $r): ?><tr><td><a href="<?= e($r['path']) ?>" target="_blank"><?= e($r['path']) ?></a></td><td class="num"><?= $r['n'] ?></td><td class="num"><?= $r['u'] ?></td></tr><?php endforeach; ?>
    <?php if (!$topPages): ?><tr><td colspan="3" class="muted">Abhi koi data nahi.</td></tr><?php endif; ?>
    </tbody></table></div>
  <div class="card"><h2>Visitors kahan se aaye (7 din)</h2>
    <?php $tot = array_sum(array_column($sources, 'n')) ?: 1; foreach ($sources as $r): ?>
      <div class="bar-row"><span><?= e($r['source']) ?></span><div class="bar"><i style="width:<?= round($r['n'] * 100 / $tot) ?>%"></i></div><b><?= $r['n'] ?></b></div>
    <?php endforeach; if (!$sources): ?><p class="muted">Abhi koi data nahi.</p><?php endif; ?>
    <h3>Device</h3>
    <?php $tot = array_sum(array_column($devices, 'n')) ?: 1; foreach ($devices as $r): ?>
      <div class="bar-row"><span><?= e($r['device']) ?></span><div class="bar"><i style="width:<?= round($r['n'] * 100 / $tot) ?>%"></i></div><b><?= round($r['n'] * 100 / $tot) ?>%</b></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card"><h2>Sabse zyada click hue links (7 din)</h2>
  <table class="tbl"><thead><tr><th>Link</th><th>Text</th><th class="num">Clicks</th></tr></thead><tbody>
  <?php foreach ($topClicks as $r): ?><tr><td class="ellip"><a href="<?= e(safe_url($r['url'])) ?>" target="_blank" rel="noopener"><?= e($r['url']) ?></a></td><td><?= e($r['txt']) ?></td><td class="num"><?= $r['n'] ?></td></tr><?php endforeach; ?>
  <?php if (!$topClicks): ?><tr><td colspan="3" class="muted">Abhi koi click nahi.</td></tr><?php endif; ?>
  </tbody></table>
</div>
<?php
admin_footer('<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>esyLineChart("trend", ' . json_encode($labels) . ', [{label:"Page views",data:' . json_encode($views) . '},{label:"Visitors",data:' . json_encode($visitors) . '}]);</script>');
