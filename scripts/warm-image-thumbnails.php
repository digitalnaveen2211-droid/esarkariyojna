<?php
declare(strict_types=1);
// Warm optimized thumbnails outside user requests. No CMS/database writes.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
require_once ROOT . '/app/image_thumbnails.php';
if (!is_installed()) { fwrite(STDERR, "CMS is not installed; skipping thumbnails.\n"); exit(0); }
if (!function_exists('imagewebp') || !function_exists('imagecreatetruecolor')) {
    fwrite(STDERR, "GD WebP support unavailable; skipping thumbnail warming.\n");
    exit(0);
}
$paths = [];
foreach (['yojna', 'blog'] as $type) {
    foreach (q_all("SELECT image FROM posts WHERE type = ? AND status = 'published' AND image <> ''", [$type]) as $row) {
        $path = trim((string)$row['image']);
        if ($path !== '') $paths[$path] = true;
    }
}
$optimized = 0;
$skipped = 0;
foreach (array_keys($paths) as $path) {
    foreach ([320, 480] as $width) {
        $thumb = esy_card_thumb($path, $width);
        if ($thumb === $path) $skipped++;
        else $optimized++;
    }
}
echo "Thumbnail warmup complete: {$optimized} optimized, {$skipped} originals/unavailable.\n";
