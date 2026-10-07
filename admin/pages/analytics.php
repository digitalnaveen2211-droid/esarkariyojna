<?php
defined('ESY') or exit;

$geoResolved = 0;
try { $geoResolved = resolve_geo(100); } catch (Throwable $e) {}

$tab = ($_GET['tab'] ?? 'visits') === 'clicks' ? 'clicks' : 'visits';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-d', strtotime('-6 days'));
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
$filterCols = $tab === 'visits'
    ? ['path' => 'Page', 'source' => 'Source', 'country' => 'Country', 'city' => 'City', 'device' => 'Device', 'browser' => 'Browser', 'utm_campaign' => 'Campaign', 'ref_host' => 'Referrer', 'os' => 'OS', 'ip' => 'IP']
    : ['url' => 'Link', 'page' => 'Page', 'country' => 'Country', 'city' => 'City', 'device' => 'Device', 'browser' => 'Browser', 'ip' => 'IP'];

$where = ['ts >= ?', 'ts <= ?'];
$params = [$from . ' 00:00:00', $to . ' 23:59:59'];
$active = [];
foreach ($filterCols as $col => $label) {
    if (isset($_GET['f_' . $col]) && $_GET['f_' . $col] !== '') {
        $where[] = "$col = ?";
        $params[] = (string)$_GET['f_' . $col];
        $active[$col] = (string)$_GET['f_' . $col];
    }
}
$W = implode(' AND ', $where);
$table = $tab;
$baseQ = ['p' => 'analytics', 'tab' => $tab, 'from' => $from, 'to' => $to] + array_combine(array_map(fn($k) => "f_$k", array_keys($active)), array_values($active));
$link = fn(array $extra) => '?' . http_build_query(array_merge($baseQ, $extra));

/* CSV export */
if (isset($_GET['export'])) {
    $cols = $tab === 'visits'
        ? 'ts, path, source, referrer, utm_source, utm_medium, utm_campaign, ip, country, region, city, device, os, browser, lang, visitor_id, session_id, user_agent'
        : 'ts, page, url, link_text, is_external, ip, country, region, city, device, os, browser, visitor_id, session_id';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $tab . '-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_map('trim', explode(',', $cols)));
    $st = q("SELECT $cols FROM $table WHERE $W ORDER BY ts DESC", $params);
    while ($r = $st->fetch(PDO::FETCH_NUM)) fputcsv($out, $r);
    exit;
}

$tot = $tab === 'visits'
    ? q_one("SELECT COUNT(*) n, COUNT(DISTINCT visitor_id) u, COUNT(DISTINCT session_id) s FROM visits WHERE $W", $params)
    : q_one("SELECT COUNT(*) n, COUNT(DISTINCT visitor_id) u, COUNT(DISTINCT url) s FROM clicks WHERE $W", $params);
$group = fn(string $col, int $lim = 10) => q_all("SELECT $col v, COUNT(*) n, COUNT(DISTINCT visitor_id) u FROM $table WHERE $W GROUP BY $col ORDER BY n DESC LIMIT $lim", $params);

$per = 50;
$pg = max(1, (int)($_GET['pg'] ?? 1));
$rows = q_all("SELECT * FROM $table WHERE $W ORDER BY ts DESC LIMIT $per OFFSET " . (($pg - 1) * $per), $params);

$daily = q_all("SELECT DATE(ts) d, COUNT(*) n, COUNT(DISTINCT visitor_id) u FROM $table WHERE $W GROUP BY DATE(ts) ORDER BY d", $params);
$hours = array_column(q_all("SELECT HOUR(ts) h, COUNT(*) n FROM $table WHERE $W GROUP BY HOUR(ts)", $params), 'n', 'h');

function breakdown(string $title, string $col, array $rows, callable $link, int $total): void {
    echo '<div class="card"><h2>' . e($title) . '</h2>';
    if (!$rows) { echo '<p class="muted">Koi data nahi.</p></div>'; return; }
    foreach ($rows as $r) {
        $v = $r['v'] === '' || $r['v'] === null ? '(unknown)' : $r['v'];
        $pct = $total ? round($r['n'] * 100 / $total) : 0;
        echo '<div class="bar-row"><a class="ellip" title="' . e($v) . '" href="' . e($link(['f_' . $col => $r['v'], 'pg' => 1])) . '">' . e($v) . '</a><div class="bar"><i style="width:' . $pct . '%"></i></div><b>' . $r['n'] . '</b></div>';
    }
    echo '</div>';
}

admin_header('Analytics', 'analytics', '<a class="btn ghost" href="' . e($link(['export' => 1])) . '">⬇ CSV Export</a>');
?>
<div class="tabs">
  <a href="<?= e('?' . http_build_query(['p' => 'analytics', 'tab' => 'visits', 'from' => $from, 'to' => $to])) ?>" class="<?= $tab === 'visits' ? 'on' : '' ?>">👁 Page visits</a>
  <a href="<?= e('?' . http_build_query(['p' => 'analytics', 'tab' => 'clicks', 'from' => $from, 'to' => $to])) ?>" class="<?= $tab === 'clicks' ? 'on' : '' ?>">🖱 Link clicks</a>
</div>

<form class="filters card" method="get">
  <input type="hidden" name="p" value="analytics"><input type="hidden" name="tab" value="<?= $tab ?>">
  <?php foreach ($active as $k => $v): ?><input type="hidden" name="f_<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
  <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
  <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
  <div class="quick">
    <?php foreach (['Aaj' => 0, '7 din' => 6, '30 din' => 29, '90 din' => 89] as $l => $d): ?>
      <a class="chip" href="<?= e($link(['from' => date('Y-m-d', strtotime("-$d days")), 'to' => date('Y-m-d'), 'pg' => 1])) ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>
  <button class="btn primary sm">Apply</button>
  <?php if ($active): ?>
    <div class="active-filters">Filter:
      <?php foreach ($active as $k => $v): $q2 = $baseQ; unset($q2['f_' . $k]); ?>
        <a class="chip on" href="?<?= e(http_build_query($q2)) ?>"><?= e($filterCols[$k]) ?>: <?= e($v) ?> ✕</a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</form>

<div class="kpis">
  <?php if ($tab === 'visits'): ?>
    <div class="kpi"><span>Page views</span><b><?= number_format($tot['n']) ?></b></div>
    <div class="kpi"><span>Unique visitors</span><b><?= number_format($tot['u']) ?></b></div>
    <div class="kpi"><span>Sessions</span><b><?= number_format($tot['s']) ?></b></div>
    <div class="kpi"><span>Pages / session</span><b><?= $tot['s'] ? round($tot['n'] / $tot['s'], 1) : 0 ?></b></div>
  <?php else: ?>
    <div class="kpi"><span>Total clicks</span><b><?= number_format($tot['n']) ?></b></div>
    <div class="kpi"><span>Clicking visitors</span><b><?= number_format($tot['u']) ?></b></div>
    <div class="kpi"><span>Alag links</span><b><?= number_format($tot['s']) ?></b></div>
  <?php endif; ?>
</div>

<div class="grid2">
  <div class="card"><h2>Din ke hisaab se</h2><div class="chart-box sm"><canvas id="daily"></canvas></div></div>
  <div class="card"><h2>Ghante ke hisaab se (IST)</h2><div class="chart-box sm"><canvas id="hourly"></canvas></div></div>
</div>

<div class="grid3">
<?php if ($tab === 'visits') {
    breakdown('Top pages', 'path', $group('path'), $link, (int)$tot['n']);
    breakdown('Source (kahan se aaye)', 'source', $group('source'), $link, (int)$tot['n']);
    breakdown('Country', 'country', $group('country'), $link, (int)$tot['n']);
    breakdown('City', 'city', $group('city'), $link, (int)$tot['n']);
    breakdown('Device', 'device', $group('device'), $link, (int)$tot['n']);
    breakdown('Browser', 'browser', $group('browser'), $link, (int)$tot['n']);
    breakdown('Operating system', 'os', $group('os'), $link, (int)$tot['n']);
    breakdown('UTM campaign', 'utm_campaign', $group('utm_campaign'), $link, (int)$tot['n']);
    breakdown('Referrer website', 'ref_host', $group('ref_host'), $link, (int)$tot['n']);
} else {
    breakdown('Sabse zyada click hue links', 'url', $group('url', 15), $link, (int)$tot['n']);
    breakdown('Kis page se click hua', 'page', $group('page'), $link, (int)$tot['n']);
    breakdown('Country', 'country', $group('country'), $link, (int)$tot['n']);
    breakdown('City', 'city', $group('city'), $link, (int)$tot['n']);
    breakdown('Device', 'device', $group('device'), $link, (int)$tot['n']);
    breakdown('Browser', 'browser', $group('browser'), $link, (int)$tot['n']);
} ?>
</div>

<div class="card">
  <div class="card-head"><h2><?= $tab === 'visits' ? 'Har visit ki detail' : 'Har click ki detail' ?></h2>
    <span class="muted"><?= $geoResolved ? "$geoResolved naye IPs ki location mili · " : '' ?>Location IP se andaza hai (ip-api.com)</span></div>
  <div class="tbl-wrap"><table class="tbl small">
  <thead><tr><th>Samay</th>
    <?php if ($tab === 'visits'): ?><th>Page</th><th>Kahan se</th><?php else: ?><th>Link</th><th>Page</th><?php endif; ?>
    <th>IP</th><th>Location</th><th>Device</th><th>Visitor</th></tr></thead><tbody>
  <?php foreach ($rows as $r): $loc = implode(', ', array_filter([$r['city'], $r['region'], $r['country']])); ?>
    <tr>
      <td title="<?= e($r['ts']) ?>"><?= e(date('d M, h:i:s A', strtotime($r['ts']))) ?></td>
      <?php if ($tab === 'visits'): ?>
        <td class="ellip"><a href="<?= e($link(['f_path' => $r['path'], 'pg' => 1])) ?>"><?= e($r['path']) ?></a></td>
        <td class="ellip" title="<?= e($r['referrer']) ?>"><a href="<?= e($link(['f_source' => $r['source'], 'pg' => 1])) ?>"><?= e($r['source']) ?></a><?= $r['utm_campaign'] ? ' · ' . e($r['utm_campaign']) : '' ?></td>
      <?php else: ?>
        <td class="ellip" title="<?= e($r['url']) ?>"><a href="<?= e($link(['f_url' => $r['url'], 'pg' => 1])) ?>"><?= e($r['link_text'] ?: $r['url']) ?></a><?= $r['is_external'] ? ' <span class="badge">bahar</span>' : '' ?></td>
        <td class="ellip"><?= e($r['page']) ?></td>
      <?php endif; ?>
      <td><a href="<?= e($link(['f_ip' => $r['ip'], 'pg' => 1])) ?>"><?= e($r['ip']) ?></a></td>
      <td><?= e($loc ?: '—') ?></td>
      <td><?= e($r['device']) ?> · <?= e($r['os']) ?> · <?= e($r['browser']) ?></td>
      <td><code title="Visitor ID"><?= e(substr($r['visitor_id'], 0, 6)) ?></code></td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="muted">Is date range me koi data nahi.</td></tr><?php endif; ?>
  </tbody></table></div>
  <?= paginate((int)$tot['n'], $per, $pg, $baseQ) ?>
</div>
<?php
$dl = []; $dn = []; $du = [];
$dmap = array_column($daily, null, 'd');
for ($t = strtotime($from); $t <= strtotime($to); $t += 86400) { $d = date('Y-m-d', $t); $dl[] = date('d M', $t); $dn[] = (int)($dmap[$d]['n'] ?? 0); $du[] = (int)($dmap[$d]['u'] ?? 0); }
$hl = []; $hn = [];
for ($h = 0; $h < 24; $h++) { $hl[] = date('g A', mktime($h, 0, 0)); $hn[] = (int)($hours[$h] ?? 0); }
admin_footer('<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>esyLineChart("daily",' . json_encode($dl) . ',[{label:"' . ($tab === 'visits' ? 'Views' : 'Clicks') . '",data:' . json_encode($dn) . '},{label:"Visitors",data:' . json_encode($du) . '}]);
esyBarChart("hourly",' . json_encode($hl) . ',' . json_encode($hn) . ');</script>');
