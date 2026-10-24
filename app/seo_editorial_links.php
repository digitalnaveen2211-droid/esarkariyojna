<?php
// Editorial cross-links for the five GSC opportunities selected on 25 Oct 2026.
// No DB writes, no title/meta overrides, no automatic keyword stuffing.
defined('ESY') or exit;

function esy_editorial_links(string $type, string $slug): string {
    $links = [
        'blog:sukanya-samriddhi-withdrawal-premature-closure-rules' => [
            ['/blog/sukanya-samriddhi-yojana-2026',
             'सुकन्या समृद्धि योजना की ब्याज दर, जमा और परिपक्वता के नियम',
             'Sukanya Samriddhi: interest, deposits and maturity rules'],
        ],
        'blog:ayushman-bharat-pm-jay-eligibility-2026' => [
            ['/blog/ayushman-vay-vandana-70-plus',
             '70 वर्ष से अधिक आयु वालों के लिए आयुष्मान वय वंदना कार्ड',
             'Ayushman Vay Vandana eligibility for people aged 70+'],
        ],
        'blog:ayushman-vay-vandana-70-plus' => [
            ['/blog/ayushman-bharat-pm-jay-eligibility-2026',
             'आयुष्मान भारत PM-JAY के सामान्य पात्रता नियम',
             'General Ayushman Bharat PM-JAY eligibility guide'],
        ],
        'yojna:sarva-shiksha-abhiyan-samagra-shiksha' => [
            ['/category/shiksha',
             'अन्य शिक्षा योजनाएं और छात्र सहायता कार्यक्रम',
             'Explore other education schemes and student support'],
        ],
        'yojna:nikshay-poshan-yojana' => [
            ['/category/swasthya',
             'अन्य स्वास्थ्य योजनाएं और पात्रता गाइड',
             'Explore other health schemes and eligibility guides'],
        ],
    ];
    $items = $links[$type . ':' . $slug] ?? [];
    if (!$items) return '';
    $html = '<section class="page-related" aria-label="' . e(tr('संबंधित जानकारी', 'Related reading')) . '">';
    $html .= '<h2>' . e(tr('संबंधित जानकारी', 'Related reading')) . '</h2><ul>';
    foreach ($items as [$path, $hi, $en]) {
        $html .= '<li><a href="' . e(lurl($path)) . '">' . e(tr($hi, $en)) . '</a></li>';
    }
    return $html . '</ul></section>';
}
