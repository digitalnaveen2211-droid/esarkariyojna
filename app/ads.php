<?php
// Ad slots managed from Admin → Ads, with first-party view / click tracking (table ad_events).
defined('ESY') or exit;

function ad_slots(): array {
    return [
        'header'              => 'Har page: header ke neeche',
        'home_top'            => 'Home: search box ke neeche',
        'home_after_featured' => 'Home: featured yojnaon ke baad',
        'home_in_list'        => 'Home: yojna list ke beech (har 6 cards ke baad)',
        'home_after_list'     => 'Home: sabhi yojnaon ke baad (content se pehle)',
        'article_top'         => 'Yojna page: title ke neeche',
        'article_middle'      => 'Yojna page: content ke beech me',
        'article_bottom'      => 'Yojna page: content ke baad',
        'sidebar'             => 'Yojna page: sidebar',
        'footer_top'          => 'Har page: footer ke upar',
        'mobile_sticky'       => 'Mobile: screen ke neeche chipka hua',
    ];
}

/** Active ads for a slot that match the current language, device and date. Cached per request. */
function ads_for(string $slot): array {
    static $all = null;
    if ($all === null) {
        $all = [];
        try {
            $today = date('Y-m-d');
            $rows = q_all("SELECT * FROM ads WHERE active = 1 AND (start_date IS NULL OR start_date <= ?) AND (end_date IS NULL OR end_date >= ?)", [$today, $today]);
            [$device] = parse_ua(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500));
            $isMobile = $device !== 'Desktop';
            foreach ($rows as $a) {
                if ($a['lang'] !== 'all' && $a['lang'] !== lang()) continue;
                if ($a['device'] === 'mobile' && !$isMobile) continue;
                if ($a['device'] === 'desktop' && $isMobile) continue;
                $all[$a['slot']][] = $a;
            }
        } catch (Throwable $e) { /* ads table missing: show nothing */ }
    }
    return $all[$slot] ?? [];
}

/** Picks one ad for the slot (weighted random) and returns its HTML, or '' if none. */
function ad_slot(string $slot): string {
    $ads = ads_for($slot);
    if (!$ads) return '';
    $total = array_sum(array_map(fn($a) => max(1, (int)$a['weight']), $ads));
    $r = random_int(1, $total);
    foreach ($ads as $a) { $r -= max(1, (int)$a['weight']); if ($r <= 0) break; }

    $label = e(tr('विज्ञापन', 'Advertisement'));
    $attrs = 'class="ad ad-' . e($slot) . '" data-ad="' . (int)$a['id'] . '" data-slot="' . e($slot) . '" data-type="' . e($a['type']) . '"';
    if ($a['type'] === 'html') {
        $inner = $a['html']; // only users with code.manage can save HTML ads
    } else {
        if ($a['image'] === '') return '';
        $img = '<img src="' . e($a['image']) . '" alt="' . e($a['alt'] ?: $a['name']) . '" loading="lazy">';
        $inner = $a['link'] !== ''
            ? '<a href="/ad/' . (int)$a['id'] . '?s=' . e($slot) . '" target="_blank" rel="sponsored nofollow noopener">' . $img . '</a>'
            : $img;
    }
    $close = $slot === 'mobile_sticky' ? '<button type="button" class="ad-close" aria-label="Close" data-ad-close>×</button>' : '';
    return '<div ' . $attrs . '><span class="ad-label">' . $label . '</span>' . $inner . $close . '</div>';
}

function log_ad_event(int $adId, string $event, string $slot, string $page): void {
    try {
        if (!in_array($event, ['view', 'click'], true) || !q_val('SELECT id FROM ads WHERE id = ?', [$adId])) return;
        $c = tracking_context();
        if (!$c) return;
        $lang = preg_match('#^/(hi|en)(/|$)#', $page, $m) ? $m[1] : '';
        q('INSERT INTO ad_events (ts, ad_id, event, slot, page, lang, visitor_id, ip, country, region, city, device, os, browser)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
          [now(), $adId, $event, mb_substr(preg_replace('/[^a-z_]/', '', $slot), 0, 40), mb_substr($page, 0, 500), $lang, $c['vid'], $c['ip'],
           $c['country'], $c['region'], $c['city'], $c['device'], $c['os'], $c['browser']]);
    } catch (Throwable $e) { /* tracking must never break the site */ }
}
