<?php
// POST /admin/draft-api.php — token authenticated, DRAFT-ONLY yojna creation.
// GET /admin/draft-api.php — existing slugs and category list for deduplication.
// A CMS administrator must activate/rotate the token from Settings > Draft Import.
require dirname(__DIR__) . '/app/bootstrap.php';
require ROOT . '/app/draft_import.php';
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
if (!is_installed()) draft_import_response(503, ['error' => 'CMS not installed']);
if (!draft_import_https()) draft_import_response(403, ['error' => 'HTTPS required']);
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    draft_import_response(405, ['error' => 'Method not allowed']);
}
$expected = (string)(settings()['draft_api_token_hash'] ?? '');
$token = (string)($_SERVER['HTTP_X_DRAFT_TOKEN'] ?? '');
if ($expected === '' || strlen($token) !== 64 || !ctype_xdigit($token)
    || !hash_equals($expected, hash('sha256', $token))) {
    draft_import_response(401, ['error' => 'Unauthorized']);
}
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $existing = q_all("SELECT slug, title_hi, title_en, status FROM posts WHERE type='yojna' ORDER BY id");
        $categories = array_map(
            static fn($c) => ['slug' => $c['slug'], 'name_hi' => $c['name_hi'], 'name_en' => $c['name_en']],
            categories()
        );
        draft_import_response(200, ['existing_schemes' => $existing, 'categories' => $categories, 'write_mode' => 'create_draft_only']);
    }
    if (stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== 0) {
        draft_import_response(415, ['error' => 'Content-Type must be application/json']);
    }
    $body = file_get_contents('php://input', false, null, 0, 1500001);
    if (!is_string($body) || strlen($body) > 1500000) {
        draft_import_response(413, ['error' => 'Payload too large (max 1.5 MB)']);
    }
    $payload = json_decode($body, true, 30, JSON_THROW_ON_ERROR);
    $drafts = $payload['drafts'] ?? null;
    if (!is_array($drafts) || !array_is_list($drafts) || count($drafts) < 1 || count($drafts) > 5) {
        draft_import_response(422, ['error' => 'Pass 1-5 drafts in a drafts array']);
    }
    $categoryIds = [];
    foreach (categories() as $category) $categoryIds[$category['slug']] = (int)$category['id'];
    $clean = [];
    $slugs = [];
    foreach ($drafts as $i => $draft) {
        if (!is_array($draft)) throw new InvalidArgumentException("draft[$i] must be an object");
        $item = draft_import_validate($draft, $categoryIds);
        if (in_array($item['slug'], $slugs, true)) throw new InvalidArgumentException('Duplicate slug within request: ' . $item['slug']);
        $slugs[] = $item['slug'];
        $clean[] = $item;
    }
    // Serialize all import requests: the per-day quota must not be raced by parallel calls.
    // MySQL advisory lock is automatically released when this request's DB connection closes.
    if ((int)q_val("SELECT GET_LOCK('esy_draft_import_daily', 10)") !== 1) {
        draft_import_response(503, ['error' => 'Import busy; please retry']);
    }
    // Cap successful automated imports, not failed validation attempts, to 25 per IST day.
    db()->beginTransaction();
    $used = (int)q_val("SELECT COUNT(*) FROM activity_log WHERE action='draft_api_created' AND ts >= CURDATE()");
    if ($used + count($clean) > 25) {
        db()->rollBack();
        draft_import_response(429, ['error' => 'Daily import limit reached (25 drafts)']);
    }
    foreach ($clean as $item) {
        $found = q_one("SELECT id FROM posts WHERE type='yojna' AND (slug=? OR title_hi=? OR title_en=?) LIMIT 1",
            [$item['slug'], $item['title_hi'], $item['title_en']]);
        if ($found) {
            db()->rollBack();
            draft_import_response(409, ['error' => 'Existing scheme/draft already uses the slug or title', 'slug' => $item['slug']]);
        }
    }
    $columns = ['slug','category_id','gov_level','state_code','title_hi','title_en','summary_hi','summary_en','elig_hi','elig_en',
                'content_hi','content_en','seo_title','seo_title_en','seo_desc','seo_desc_en','keywords','official_url'];
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $sql = "INSERT INTO posts (type, " . implode(',', $columns) . ", status, featured, created_at, updated_at)
            VALUES ('yojna', $placeholders, 'draft', 0, NOW(), NOW())";
    $ids = [];
    $log = db()->prepare("INSERT INTO activity_log (user_id, action, details, ip) VALUES (NULL,'draft_api_created',?,?)");
    foreach ($clean as $item) {
        q($sql, array_map(static fn($column) => $item[$column], $columns));
        $ids[] = (int)db()->lastInsertId();
        $log->execute([$item['slug'], client_ip()]);
    }
    db()->commit();
    $result = [];
    foreach ($ids as $i => $id) $result[] = [
        'id' => $id, 'slug' => $clean[$i]['slug'],
        'status' => 'draft', 'edit_url' => '/admin/?p=post_edit&type=yojna&id=' . $id,
    ];
    draft_import_response(201, ['created' => $result, 'published' => 0]);
} catch (InvalidArgumentException | JsonException $e) {
    if (db()->inTransaction()) db()->rollBack();
    draft_import_response(422, ['error' => $e->getMessage()]);
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    error_log('Draft import error: ' . $e->getMessage());
    draft_import_response(500, ['error' => 'Draft import failed; check server logs']);
}
