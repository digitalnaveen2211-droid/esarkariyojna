<?php
defined('ESY') or exit;
require_perm('posts.edit');

// Read-only export. A fresh DB query always includes drafts and published records.
// Exporting the entire content library is restricted to administrators.
require_perm('settings.manage');

$kind = (string)($_GET['kind'] ?? 'yojna');
$format = (string)($_GET['format'] ?? 'json');
$status = (string)($_GET['status'] ?? 'all');
if (!in_array($kind, ['yojna', 'blog', 'both'], true)) $kind = 'yojna';
if (!in_array($format, ['json', 'csv'], true)) $format = 'json';
if (!in_array($status, ['all', 'published', 'draft'], true)) $status = 'all';

if (isset($_GET['download'])) {
    // Export is a GET download with an authenticated, same-site session.
    // Protect against stale or forged download URLs using a CSRF token.
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_GET['token'] ?? ''))) {
        http_response_code(403);
        exit('Invalid export token. Reload the Export page and try again.');
    }

    $where = ["p.type IN (" . ($kind === 'both' ? "'yojna','blog'" : ($kind === 'blog' ? "'blog'" : "'yojna'")) . ")"];
    $params = [];
    if ($status !== 'all') { $where[] = 'p.status = ?'; $params[] = $status; }
    $items = q_all(
        'SELECT p.*, c.slug AS category_slug, c.name_hi AS category_name_hi, ' .
        'bc.slug AS blog_category_slug, bc.name_hi AS blog_category_name_hi ' .
        'FROM posts p LEFT JOIN categories c ON c.id=p.category_id ' .
        'LEFT JOIN blog_categories bc ON bc.id=p.blog_cat_id ' .
        'WHERE ' . implode(' AND ', $where) . ' ORDER BY p.type, p.id',
        $params
    );
    // Convert internal author/session-independent references to portable values.
    // Keep original post columns (including bilingual content, SEO, image paths and status).
    $data = [];
    foreach ($items as $item) {
        $data[] = $item;
    }

    $file = 'esarkariyojna_' . $kind . '_' . $status . '_' . date('Y-m-d_His') . '.' . $format;
    if ($format === 'json') {
        $payload = json_encode([
            'export_format' => 'esy-content-v1',
            'exported_at' => date(DATE_ATOM),
            'type' => $kind,
            'status_filter' => $status,
            'record_count' => count($data),
            'records' => $data,
            // Note: this is a backup-style export, NOT the five-item draft_import JSON format.
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($payload === false) { http_response_code(500); exit('JSON export failed'); }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo $payload;
        exit;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    $columns = $data ? array_keys($data[0]) : ['type','slug','status','title_hi','title_en'];
    fputcsv($out, $columns);
    foreach ($data as $row) {
        $cells = [];
        foreach ($columns as $key) {
            $value = (string)($row[$key] ?? '');
            // Prevent CSV spreadsheet formula injection.
            if (preg_match('/^[\x00-\x20]*[=+@\-]/u', $value)) $value = "'" . $value;
            $cells[] = $value;
        }
        fputcsv($out, $cells);
    }
    fclose($out);
    exit;
}

admin_header('Export Yojnayein & Blogs', 'export');
?>
<div class="card">
  <h2>⬇️ Export CMS Content</h2>
  <p class="muted">Saari published aur draft Yojnayein / Blogs ka full bilingual content download karein. JSON backup aur Excel-friendly CSV available hain. Export se CMS mein koi changes nahi honge.</p>
  <form method="get" action="/admin/" class="form-row" style="gap:12px;align-items:flex-end;flex-wrap:wrap">
    <input type="hidden" name="p" value="export">
    <input type="hidden" name="download" value="1">
    <input type="hidden" name="token" value="<?= e(csrf_token()) ?>">
    <label class="field"><span>Content type</span>
      <select name="kind">
        <option value="yojna">All Yojnayein</option>
        <option value="blog">All Blogs</option>
        <option value="both">Yojnayein + Blogs</option>
      </select>
    </label>
    <label class="field"><span>Status</span>
      <select name="status">
        <option value="all">Published + Draft</option>
        <option value="published">Published only</option>
        <option value="draft">Draft only</option>
      </select>
    </label>
    <label class="field"><span>Format</span>
      <select name="format">
        <option value="json">Full JSON Backup</option>
        <option value="csv">CSV (Excel / Google Sheets)</option>
      </select>
    </label>
    <button class="btn primary" type="submit">⬇ Download Export</button>
  </form>
  <p class="muted">JSON mein original body HTML, Hindi/English, SEO, slug, status, category aur featured image path sab rahenge. Media files khud export mein embed nahi hote. Yeh backup-style JSON hai, 5-item Draft Import JSON nahi.</p>
</div>
<?php admin_footer(); ?>
