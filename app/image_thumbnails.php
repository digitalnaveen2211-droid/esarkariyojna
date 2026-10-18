<?php
// Responsive, uncropped 640px thumbnails for homepage/directory cards.
// Generated only for existing local uploaded image paths; never touch originals.
defined('ESY') or exit;

function esy_card_thumb(string $url): string {
    static $generated = 0;
    if (!preg_match('#^/uploads/([a-zA-Z0-9/_-]+)\.(jpe?g|png|webp)$#iD', $url)) return $url;
    if (!function_exists('imagewebp') || !function_exists('imagecreatetruecolor')) return $url;
    $source = realpath(ROOT . $url);
    $root = realpath(UPLOAD_DIR);
    if (!$source || !$root || !str_starts_with($source, $root . DIRECTORY_SEPARATOR)
        || !is_file($source) || filesize($source) > 8 * 1024 * 1024) return $url;
    $stats = @getimagesize($source);
    if (!$stats || $stats[0] <= 0 || $stats[1] <= 0 || $stats[0] * $stats[1] > 16000000) return $url;
    if (max($stats[0], $stats[1]) <= 700) return $url;
    $key = substr(hash('sha256', $url . ':' . filemtime($source)), 0, 24);
    $cacheDir = UPLOAD_DIR . '/thumbs';
    $dest = $cacheDir . '/' . $key . '-640.webp';
    $webUrl = UPLOAD_URL . '/thumbs/' . $key . '-640.webp';
    if (is_file($dest) && filesize($dest) > 100) return $webUrl;
    // Limit cold-cache transformations so PageSpeed first-response TTFB is not
    // overwhelmed by many large images; later hits progressively warm cache.
    if ($generated >= 3) return $url;
    $generated++;
    $type = (int)$stats[2];
    $image = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
        IMAGETYPE_PNG => @imagecreatefrompng($source),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
        default => false
    };
    if (!$image) return $url;
    $scale = min(1, 640 / max($stats[0], $stats[1]));
    $w = max(1, (int)round($stats[0] * $scale));
    $h = max(1, (int)round($stats[1] * $scale));
    $thumb = imagecreatetruecolor($w, $h);
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    $clear = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
    imagefilledrectangle($thumb, 0, 0, $w, $h, $clear);
    $ok = imagecopyresampled($thumb, $image, 0, 0, 0, 0, $w, $h, $stats[0], $stats[1]);
    imagedestroy($image);
    if (!$ok || (!is_dir($cacheDir) && !@mkdir($cacheDir, 0755, true))) {
        imagedestroy($thumb); return $url;
    }
    $tmp = @tempnam($cacheDir, 'thumb_');
    $saved = $tmp && @imagewebp($thumb, $tmp, 78);
    imagedestroy($thumb);
    if (!$saved || !$tmp || !is_file($tmp) || filesize($tmp) < 100) {
        if ($tmp) @unlink($tmp);
        return $url;
    }
    if (!@rename($tmp, $dest)) {
        @unlink($tmp);
        return $url;
    }
    @chmod($dest, 0644);
    return $webUrl;
}
