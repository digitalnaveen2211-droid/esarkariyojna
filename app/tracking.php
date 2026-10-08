<?php
// First-party visitor tracking: page views, link clicks, device, source and IP location.
defined('ESY') or exit;

function is_bot(string $ua): bool {
    return $ua === '' || (bool)preg_match('/bot|crawl|spider|slurp|mediapartners|facebookexternalhit|whatsapp|telegrambot|preview|curl|wget|python|java\/|go-http|headless|lighthouse|pingdom|uptime|monitor|scan/i', $ua);
}

function parse_ua(string $ua): array {
    $device = 'Desktop';
    if (preg_match('/iPad|Tablet|PlayBook|Silk|(Android(?!.*Mobile))/i', $ua)) $device = 'Tablet';
    elseif (preg_match('/Mobi|iPhone|iPod|Android|BlackBerry|Opera Mini|IEMobile/i', $ua)) $device = 'Mobile';

    $os = 'Other';
    foreach (['Android' => '/Android/i', 'iOS' => '/iPhone|iPad|iPod/i', 'Windows' => '/Windows/i', 'macOS' => '/Mac OS X|Macintosh/i', 'Linux' => '/Linux/i', 'ChromeOS' => '/CrOS/i'] as $name => $re) {
        if (preg_match($re, $ua)) { $os = $name; break; }
    }
    $browser = 'Other';
    foreach (['Edge' => '/Edg\//', 'Opera' => '/OPR\/|Opera/', 'Samsung' => '/SamsungBrowser/', 'UC Browser' => '/UCBrowser/', 'Firefox' => '/Firefox\//', 'Chrome' => '/Chrome\/|CriOS/', 'Safari' => '/Safari\//'] as $name => $re) {
        if (preg_match($re, $ua)) { $browser = $name; break; }
    }
    return [$device, $os, $browser];
}

function traffic_source(string $refHost, string $utmSource): string {
    if ($utmSource !== '') return mb_substr($utmSource, 0, 60);
    if ($refHost === '') return 'Direct';
    $map = [
        'Google' => '/(^|\.)google\./', 'Bing' => '/bing\.com$/', 'Yahoo' => '/yahoo\./', 'DuckDuckGo' => '/duckduckgo\.com$/',
        'Facebook' => '/facebook\.com$|fb\.me$|fb\.com$/', 'Instagram' => '/instagram\.com$/', 'YouTube' => '/youtube\.com$|youtu\.be$/',
        'WhatsApp' => '/whatsapp\./', 'Telegram' => '/t\.me$|telegram\./', 'X / Twitter' => '/t\.co$|twitter\.com$|x\.com$/',
        'LinkedIn' => '/linkedin\.com$|lnkd\.in$/', 'ChatGPT' => '/chatgpt\.com$|openai\.com$/',
    ];
    foreach ($map as $name => $re) if (preg_match($re, $refHost)) return $name;
    return $refHost;
}

function tracking_ids(): array {
    $vid = $_COOKIE['esy_vid'] ?? '';
    if (!preg_match('/^[a-f0-9]{16}$/', $vid)) $vid = bin2hex(random_bytes(8));
    $sid = $_COOKIE['esy_sid'] ?? '';
    if (!preg_match('/^[a-f0-9]{16}$/', $sid)) $sid = bin2hex(random_bytes(8));
    if (!headers_sent()) {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('esy_vid', $vid, ['expires' => time() + 365 * 86400, 'path' => '/', 'samesite' => 'Lax', 'secure' => $secure, 'httponly' => true]);
        setcookie('esy_sid', $sid, ['expires' => time() + 1800, 'path' => '/', 'samesite' => 'Lax', 'secure' => $secure, 'httponly' => true]);
    }
    return [$vid, $sid];
}

function tracked_ip(): string {
    $ip = client_ip();
    if (settings()['track_anonymize_ip'] === '1') {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) $ip = preg_replace('/\.\d+$/', '.0', $ip);
        else $ip = preg_replace('/:[0-9a-f]*:[0-9a-f]*:[0-9a-f]*:[0-9a-f]*$/i', ':0:0:0:0', $ip);
    }
    return $ip;
}

function tracking_context(): ?array {
    $S = settings();
    if ($S['track_on'] !== '1') return null;
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    if (is_bot($ua)) return null;
    if (!empty($_COOKIE['esy_admin'])) return null; // don't count logged-in staff
    [$device, $os, $browser] = parse_ua($ua);
    [$vid, $sid] = tracking_ids();
    $ip = tracked_ip();
    $geo = q_one('SELECT country, region, city FROM ip_geo WHERE ip = ?', [$ip]) ?? ['country' => '', 'region' => '', 'city' => ''];
    if ($geo['country'] === '' && !empty($_SERVER['HTTP_CF_IPCOUNTRY'])) $geo['country'] = substr($_SERVER['HTTP_CF_IPCOUNTRY'], 0, 80);
    return compact('ua', 'device', 'os', 'browser', 'vid', 'sid', 'ip') + $geo;
}

function log_visit(string $path): void {
    try {
        $c = tracking_context();
        if (!$c) return;
        $ref = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 1000);
        $refHost = strtolower((string)parse_url($ref, PHP_URL_HOST));
        $ownHost = strtolower((string)parse_url(settings()['site_url'], PHP_URL_HOST));
        if ($refHost !== '' && ($refHost === $ownHost || $refHost === 'www.' . $ownHost || $refHost === ($_SERVER['HTTP_HOST'] ?? ''))) $refHost = 'internal';
        $utm = fn($k) => mb_substr(trim((string)($_GET[$k] ?? '')), 0, 120);
        $source = $refHost === 'internal' ? 'Internal' : traffic_source($refHost, $utm('utm_source'));
        $lang = substr(preg_replace('/[^a-zA-Z\-].*$/', '', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 20);
        q('INSERT INTO visits (ts, visitor_id, session_id, path, referrer, ref_host, source, utm_source, utm_medium, utm_campaign, ip, country, region, city, device, os, browser, lang, user_agent)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
          [now(), $c['vid'], $c['sid'], mb_substr($path, 0, 500), $ref, $refHost, $source, $utm('utm_source'), $utm('utm_medium'), $utm('utm_campaign'),
           $c['ip'], $c['country'], $c['region'], $c['city'], $c['device'], $c['os'], $c['browser'], $lang, $c['ua']]);
        if (random_int(1, 200) === 1) purge_old_tracking();
    } catch (Throwable $e) { /* tracking must never break the site */ }
}

function log_click(string $url, string $text, string $page, ?int $linkId = null): void {
    try {
        $c = tracking_context();
        if (!$c) return;
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        $ownHost = strtolower((string)parse_url(settings()['site_url'], PHP_URL_HOST));
        $external = $host !== '' && $host !== $ownHost && $host !== 'www.' . $ownHost && $host !== ($_SERVER['HTTP_HOST'] ?? '');
        $refHost = strtolower((string)parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST));
        q('INSERT INTO clicks (ts, visitor_id, session_id, page, url, link_text, link_id, is_external, ref_host, ip, country, region, city, device, os, browser)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
          [now(), $c['vid'], $c['sid'], mb_substr($page, 0, 500), mb_substr($url, 0, 1000), mb_substr($text, 0, 255), $linkId, $external ? 1 : 0, $refHost,
           $c['ip'], $c['country'], $c['region'], $c['city'], $c['device'], $c['os'], $c['browser']]);
    } catch (Throwable $e) {}
}

function purge_old_tracking(): void {
    $days = max(7, (int)settings()['track_retention_days']);
    $cut = date('Y-m-d H:i:s', time() - $days * 86400);
    q('DELETE FROM visits WHERE ts < ?', [$cut]);
    q('DELETE FROM clicks WHERE ts < ?', [$cut]);
    try { q('DELETE FROM ad_events WHERE ts < ?', [$cut]); } catch (Throwable $e) {}
}

/**
 * Resolve city/region/country for IPs that are not in ip_geo yet, using the ip-api.com batch API,
 * then back-fill visits and clicks. Called from the admin analytics screen so visitors never wait on it.
 */
function resolve_geo(int $limit = 100): int {
    if (settings()['geo_on'] !== '1' || !function_exists('curl_init')) return 0;
    $ips = array_column(q_all(
        "SELECT DISTINCT v.ip FROM (SELECT ip FROM visits WHERE country = '' UNION SELECT ip FROM clicks WHERE country = '') v
         LEFT JOIN ip_geo g ON g.ip = v.ip WHERE g.ip IS NULL LIMIT " . (int)$limit), 'ip');
    $done = 0;
    if ($ips) {
        $public = array_values(array_filter($ips, fn($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)));
        $ins = db()->prepare('INSERT INTO ip_geo (ip, country, region, city, isp, fetched_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE country=VALUES(country), region=VALUES(region), city=VALUES(city), isp=VALUES(isp), fetched_at=VALUES(fetched_at)');
        foreach (array_diff($ips, $public) as $ip) $ins->execute([$ip, 'Local', '', '', '', now()]);
        if ($public) {
            $ch = curl_init('http://ip-api.com/batch?fields=status,query,country,regionName,city,isp');
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($public), CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 6, CURLOPT_HTTPHEADER => ['Content-Type: application/json']]);
            $res = json_decode((string)curl_exec($ch), true);
            curl_close($ch);
            foreach ((array)$res as $r) {
                if (($r['status'] ?? '') !== 'success') { $ins->execute([$r['query'] ?? '', 'Unknown', '', '', '', now()]); continue; }
                $ins->execute([$r['query'], mb_substr($r['country'] ?? '', 0, 80), mb_substr($r['regionName'] ?? '', 0, 120), mb_substr($r['city'] ?? '', 0, 120), mb_substr($r['isp'] ?? '', 0, 190), now()]);
                $done++;
            }
        }
    }
    foreach (['visits', 'clicks', 'ad_events'] as $t) {
        try {
            q("UPDATE $t t JOIN ip_geo g ON g.ip = t.ip SET t.country = g.country, t.region = g.region, t.city = g.city WHERE t.country = ''");
        } catch (Throwable $e) {}
    }
    return $done;
}
