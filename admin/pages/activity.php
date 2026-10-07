<?php
defined('ESY') or exit;
$per = 100;
$pg = max(1, (int)($_GET['pg'] ?? 1));
$total = (int)q_val('SELECT COUNT(*) FROM activity_log');
$rows = q_all('SELECT a.*, u.name FROM activity_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT ' . $per . ' OFFSET ' . (($pg - 1) * $per));
admin_header('Activity Log', 'activity');
?>
<div class="card"><p class="muted">Kis user ne admin panel me kab kya badla.</p><div class="tbl-wrap"><table class="tbl small">
  <thead><tr><th>Samay</th><th>User</th><th>Kaam</th><th>Detail</th><th>IP</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td><?= e(date('d M Y, h:i A', strtotime($r['ts']))) ?></td><td><?= e($r['name'] ?? '—') ?></td><td><code><?= e($r['action']) ?></code></td><td><?= e($r['details']) ?></td><td><?= e($r['ip']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?= paginate($total, $per, $pg, ['p' => 'activity']) ?></div>
<?php admin_footer();
