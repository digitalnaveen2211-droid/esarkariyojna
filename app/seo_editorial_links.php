<?php
// Curated editorial links for 21 blog guides; links are validated against live published CMS posts.
// Never change saved article HTML or link by fuzzy keyword matching.
defined('ESY') or exit;

function esy_editorial_links(string $type, string $slug, string $renderedBody = ''): string {
    static $blogLinks = [
        'atal-pension-yojana-eligibility-taxpayer-exclusion' => [['yojna', 'atal-pension-yojana-apy'], ['yojna', 'pradhan-mantri-shram-yogi-maandhan'], ['yojna', 'pradhan-mantri-kisan-maandhan-yojana-pension']],
        'ayushman-bharat-cashless-treatment-hospital-claim-guide' => [['yojna', 'ayushman-bharat-pradhan-mantri-jan-arogya-yojana-pm-jay'], ['blog', 'ayushman-card-hospital-list-kaise-check-kare'], ['blog', 'ayushman-bharat-pm-jay-eligibility-2026']],
        'ayushman-bharat-pm-jay-eligibility-2026' => [['yojna', 'ayushman-bharat-pradhan-mantri-jan-arogya-yojana-pm-jay'], ['blog', 'ayushman-vay-vandana-70-plus'], ['blog', 'ayushman-card-hospital-list-kaise-check-kare']],
        'ayushman-card-hospital-list-kaise-check-kare' => [['blog', 'ayushman-bharat-cashless-treatment-hospital-claim-guide'], ['blog', 'ayushman-bharat-pm-jay-eligibility-2026'], ['yojna', 'ayushman-bharat-pradhan-mantri-jan-arogya-yojana-pm-jay']],
        'ayushman-vay-vandana-70-plus' => [['blog', 'ayushman-bharat-pm-jay-eligibility-2026'], ['yojna', 'ayushman-bharat-pradhan-mantri-jan-arogya-yojana-pm-jay'], ['blog', 'ayushman-card-hospital-list-kaise-check-kare']],
        'bihar-mahila-rojgar-payment-check' => [['yojna', 'bihar-mukhyamantri-mahila-rojgar-yojana'], ['yojna', 'lakhpati-didi-shg-livelihoods-initiative'], ['yojna', 'mission-shakti-sambal-samarthya']],
        'epfo-uan-umang-face-authentication' => [['yojna', 'pradhan-mantri-shram-yogi-maandhan'], ['blog', 'atal-pension-yojana-eligibility-taxpayer-exclusion']],
        'pm-kisan-23rd-installment-physical-verification' => [['blog', 'pm-kisan-ekyc-done-payment-pending'], ['blog', 'pm-kisan-beneficiary-list-village-check'], ['yojna', 'pradhan-mantri-kisan-samman-nidhi-yojana']],
        'pm-kisan-beneficiary-list-village-check' => [['blog', 'pm-kisan-ekyc-done-payment-pending'], ['blog', 'pm-kisan-samman-nidhi-2026-eligibility-registration-ekyc'], ['yojna', 'pradhan-mantri-kisan-samman-nidhi-yojana']],
        'pm-kisan-ekyc-done-payment-pending' => [['blog', 'pm-kisan-beneficiary-list-village-check'], ['blog', 'pm-kisan-23rd-installment-physical-verification'], ['yojna', 'pradhan-mantri-kisan-samman-nidhi-yojana']],
        'pm-kisan-samman-nidhi-2026-eligibility-registration-ekyc' => [['yojna', 'pradhan-mantri-kisan-samman-nidhi-yojana'], ['blog', 'pm-kisan-beneficiary-list-village-check'], ['blog', 'pm-kisan-ekyc-done-payment-pending']],
        'pm-mudra-loan-categories-documents-rejection' => [['yojna', 'pradhan-mantri-mudra-yojana-pmmy'], ['yojna', 'pm-svanidhi-yojana-2-0'], ['yojna', 'cgtmse-credit-guarantee-micro-small-enterprises']],
        'pm-vidyalaxmi-interest-subsidy-guide' => [['yojna', 'pm-vidyalaxmi-education-loan'], ['yojna', 'national-means-cum-merit-scholarship'], ['yojna', 'post-matric-scholarship-scheduled-caste-students']],
        'pmay-urban-2-online-apply-eligibility-status' => [['yojna', 'pradhan-mantri-awas-yojana-urban-2-0-pmay-u'], ['yojna', 'pmay-gramin']],
        'pmgkay-ration-card-status-nfsa-beneficiary-list' => [['yojna', 'pm-garib-kalyan-anna-yojana'], ['yojna', 'one-nation-one-ration-card'], ['yojna', 'antyodaya-anna-yojana-aay-ration']],
        'pmjjby-renewal-premium-autodebit-active-cover' => [['yojna', 'pradhan-mantri-jeevan-jyoti-bima-yojana-pmjjby'], ['yojna', 'pm-suraksha-bima'], ['yojna', 'pm-jan-dhan-yojana-financial-inclusion']],
        'sukanya-samriddhi-interest-maturity-calculator' => [['yojna', 'sukanya-samriddhi-yojana-ssy'], ['blog', 'sukanya-samriddhi-withdrawal-premature-closure-rules'], ['blog', 'sukanya-samriddhi-yojana-2026']],
        'sukanya-samriddhi-withdrawal-premature-closure-rules' => [['yojna', 'sukanya-samriddhi-yojana-ssy'], ['blog', 'sukanya-samriddhi-yojana-2026'], ['blog', 'sukanya-samriddhi-interest-maturity-calculator']],
        'sukanya-samriddhi-yojana-2026' => [['yojna', 'sukanya-samriddhi-yojana-ssy'], ['blog', 'sukanya-samriddhi-withdrawal-premature-closure-rules'], ['blog', 'sukanya-samriddhi-interest-maturity-calculator']],
        'svanidhi-upi-rupay-credit-card' => [['yojna', 'pm-svanidhi-yojana-2-0'], ['yojna', 'pradhan-mantri-mudra-yojana-pmmy'], ['yojna', 'pm-jan-dhan-yojana-financial-inclusion']],
        'ujjwala-lpg-connection-eligibility-documents-apply' => [['yojna', 'pradhan-mantri-ujjwala-yojana-free-lpg-connection']],
    ];
    // Preserve the existing manually reviewed scheme-page category links.
    $schemeLinks = [
        'sarva-shiksha-abhiyan-samagra-shiksha' => ['/category/shiksha',
            'अन्य शिक्षा योजनाएं और छात्र सहायता कार्यक्रम', 'Explore other education schemes and student support'],
        'nikshay-poshan-yojana' => ['/category/swasthya',
            'अन्य स्वास्थ्य योजनाएं और पात्रता गाइड', 'Explore other health schemes and eligibility guides'],
    ];

    $targets = [];
    if ($type === 'blog') {
        foreach ($blogLinks[$slug] ?? [] as [$targetType, $targetSlug]) {
            if ($targetType === $type && $targetSlug === $slug) continue;
            $post = find_post($targetType, $targetSlug);
            if (!$post) continue; // Never link to unpublished or removed entries.
            if (lang() === 'en' && !has_english($post)) continue;
            $path = post_path($targetType, $targetSlug);
            $url = lurl($path);
            // Don't repeat a contextual link that the editor already placed in the body.
            if (str_contains($renderedBody, 'href="' . $url . '"')
                || str_contains($renderedBody, "href='" . $url . "'")
                || str_contains($renderedBody, 'href="' . $path . '"')
                || str_contains($renderedBody, "href='" . $path . "'")) continue;
            $title = trim((string)tr($post['title_hi'], $post['title_en']));
            if ($title === '') continue;
            $targets[$url] = $title;
            if (count($targets) >= 3) break;
        }
    } elseif ($type === 'yojna' && isset($schemeLinks[$slug])) {
        [$path, $hi, $en] = $schemeLinks[$slug];
        $targets[lurl($path)] = tr($hi, $en);
    }

    if (!$targets) return '';
    $label = tr('संबंधित जानकारी', 'Related reading');
    $html = '<section class="page-related" aria-label="' . e($label) . '"><h2>' . e($label) . '</h2><ul>';
    foreach ($targets as $url => $title) {
        $html .= '<li><a href="' . e($url) . '">' . e($title) . '</a></li>';
    }
    return $html . '</ul></section>';
}
