<?php
// Editorially reviewed links, inserted into existing blog paragraphs only.
// Saved CMS HTML and layout of scheme-detail pages are not changed.
defined('ESY') or exit;

function esy_blog_inline_links(string $slug, string $html): string {
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

    // Match only topic-specific phrases, not generic keywords such as "scheme" or "apply".
    $phrases = [
        'atal-pension-yojana-apy' => ['अटल पेंशन योजना', 'Atal Pension Yojana', 'APY pension'],
        'pradhan-mantri-shram-yogi-maandhan' => ['प्रधानमंत्री श्रम योगी मानधन', 'PM-SYM', 'Shram Yogi Maandhan'],
        'pradhan-mantri-kisan-maandhan-yojana-pension' => ['प्रधानमंत्री किसान मानधन', 'PM-KMY', 'Kisan Maandhan'],
        'ayushman-bharat-pradhan-mantri-jan-arogya-yojana-pm-jay' => ['आयुष्मान भारत PM-JAY', 'प्रधानमंत्री जन आरोग्य योजना', 'Ayushman Bharat PM-JAY', 'PM-JAY scheme'],
        'ayushman-card-hospital-list-kaise-check-kare' => ['अस्पतालों की सूची', 'हॉस्पिटल लिस्ट', 'hospital list', 'empanelled hospitals'],
        'ayushman-bharat-cashless-treatment-hospital-claim-guide' => ['कैशलेस इलाज', 'कैशलेस उपचार', 'cashless treatment', 'hospital claim'],
        'ayushman-bharat-pm-jay-eligibility-2026' => ['आयुष्मान भारत पात्रता', 'PM-JAY पात्रता', 'PM-JAY eligibility', 'Ayushman eligibility'],
        'ayushman-vay-vandana-70-plus' => ['आयुष्मान वय वंदना', 'Ayushman Vay Vandana', 'Vay Vandana card'],
        'bihar-mukhyamantri-mahila-rojgar-yojana' => ['मुख्यमंत्री महिला रोजगार योजना', 'Bihar Mahila Rojgar Yojana', 'Mahila Rojgar Yojana'],
        'lakhpati-didi-shg-livelihoods-initiative' => ['लखपति दीदी', 'Lakhpati Didi'],
        'mission-shakti-sambal-samarthya' => ['मिशन शक्ति', 'Mission Shakti'],
        'pm-kisan-ekyc-done-payment-pending' => ['eKYC पूरा होने के बाद', 'ई-केवाईसी के बाद भुगतान', 'eKYC done', 'eKYC completed', 'payment pending'],
        'pm-kisan-beneficiary-list-village-check' => ['लाभार्थी सूची', 'बेनेफिशियरी लिस्ट', 'beneficiary list', 'village-wise list'],
        'pradhan-mantri-kisan-samman-nidhi-yojana' => ['पीएम किसान सम्मान निधि', 'प्रधानमंत्री किसान सम्मान निधि', 'PM-Kisan Samman Nidhi', 'PM Kisan scheme'],
        'pm-kisan-23rd-installment-physical-verification' => ['भौतिक सत्यापन', 'physical verification', '23rd installment', '23वीं किस्त'],
        'pm-kisan-samman-nidhi-2026-eligibility-registration-ekyc' => ['पीएम किसान पात्रता', 'PM Kisan eligibility', 'PM-Kisan registration', 'किसान पंजीकरण'],
        'pradhan-mantri-mudra-yojana-pmmy' => ['प्रधानमंत्री मुद्रा योजना', 'मुद्रा लोन', 'Pradhan Mantri Mudra Yojana', 'Mudra loan'],
        'pm-svanidhi-yojana-2-0' => ['पीएम स्वनिधि योजना', 'PM SVANidhi', 'स्वनिधि योजना'],
        'cgtmse-credit-guarantee-micro-small-enterprises' => ['सीजीटीएमएसई', 'CGTMSE', 'credit guarantee scheme'],
        'pm-vidyalaxmi-education-loan' => ['पीएम विद्यालक्ष्मी', 'PM Vidyalaxmi', 'विद्यालक्ष्मी योजना'],
        'national-means-cum-merit-scholarship' => ['राष्ट्रीय साधन सह योग्यता छात्रवृत्ति', 'National Means-cum-Merit Scholarship', 'NMMS scholarship'],
        'post-matric-scholarship-scheduled-caste-students' => ['पोस्ट मैट्रिक छात्रवृत्ति', 'Post Matric Scholarship'],
        'pradhan-mantri-awas-yojana-urban-2-0-pmay-u' => ['प्रधानमंत्री आवास योजना शहरी', 'PMAY Urban 2.0', 'PMAY-U 2.0', 'पीएमएवाई शहरी'],
        'pmay-gramin' => ['प्रधानमंत्री आवास योजना ग्रामीण', 'PMAY Gramin', 'PMAY-G'],
        'pm-garib-kalyan-anna-yojana' => ['प्रधानमंत्री गरीब कल्याण अन्न योजना', 'पीएम गरीब कल्याण अन्न योजना', 'PMGKAY', 'Garib Kalyan Anna Yojana'],
        'one-nation-one-ration-card' => ['वन नेशन वन राशन कार्ड', 'One Nation One Ration Card', 'ONORC'],
        'antyodaya-anna-yojana-aay-ration' => ['अंत्योदय अन्न योजना', 'Antyodaya Anna Yojana', 'AAY ration'],
        'pradhan-mantri-jeevan-jyoti-bima-yojana-pmjjby' => ['प्रधानमंत्री जीवन ज्योति बीमा योजना', 'PMJJBY', 'Jeevan Jyoti Bima'],
        'pm-suraksha-bima' => ['प्रधानमंत्री सुरक्षा बीमा योजना', 'PMSBY', 'Suraksha Bima'],
        'pm-jan-dhan-yojana-financial-inclusion' => ['जन धन योजना', 'Pradhan Mantri Jan Dhan', 'PMJDY'],
        'sukanya-samriddhi-yojana-ssy' => ['सुकन्या समृद्धि योजना', 'Sukanya Samriddhi Yojana', 'SSY account'],
        'sukanya-samriddhi-withdrawal-premature-closure-rules' => ['सुकन्या निकासी नियम', 'आंशिक निकासी', 'premature closure', 'partial withdrawal', 'withdrawal rules'],
        'sukanya-samriddhi-yojana-2026' => ['सुकन्या समृद्धि योजना 2026', 'Sukanya Samriddhi Yojana 2026', 'Sukanya account rules'],
        'sukanya-samriddhi-interest-maturity-calculator' => ['ब्याज कैलकुलेटर', 'मैच्योरिटी कैलकुलेटर', 'interest calculator', 'maturity calculator'],
        'pradhan-mantri-ujjwala-yojana-free-lpg-connection' => ['प्रधानमंत्री उज्ज्वला योजना', 'उज्ज्वला योजना', 'Pradhan Mantri Ujjwala Yojana', 'Ujjwala Yojana'],
    ];
    if (!isset($blogLinks[$slug]) || trim($html) === '') return $html;
    $candidates = [];
    foreach ($blogLinks[$slug] as [$type, $targetSlug]) {
        if ($type === 'blog' && $slug === $targetSlug) continue;
        if (!isset($phrases[$targetSlug])) continue;
        $post = find_post($type, $targetSlug);
        if (!$post || (lang() === 'en' && !has_english($post))) continue;
        $url = lurl(post_path($type, $targetSlug));
        // CMS-authored links always take priority, including absolute links.
        if (preg_match('~href\s*=\s*["\'][^"\']*' . preg_quote(post_path($type, $targetSlug), '~') . '(?:["\']|[?#])~iu', $html)) continue;
        $candidates[] = ['url' => $url, 'phrases' => $phrases[$targetSlug]];
    }
    if (!$candidates) return $html;

    // Tokenize without serializing/reformatting existing CMS markup or its attributes.
    // Only alter plain text directly inside <p> or <li> and outside all protected tags.
    $tokens = preg_split('~(<(?:[^>"\']|"[^"]*"|\'[^\']*\')*>)~u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($tokens === false) return $html;
    $stack = [];
    $used = [];
    $total = 0;
    $out = '';
    $protected = ['a','script','style','noscript','code','pre','h1','h2','h3','h4','h5','h6','button','textarea','select','svg'];
    $void = ['br','hr','img','input','source','meta','link','wbr','area','embed'];
    foreach ($tokens as $token) {
        if ($token !== '' && $token[0] === '<') {
            if (preg_match('~^<\s*(/?)\s*([a-z][a-z0-9:-]*)\b~i', $token, $m)) {
                $tag = strtolower($m[2]);
                if ($m[1] === '/') {
                    for ($k = count($stack)-1; $k >= 0; $k--) {
                        if ($stack[$k] === $tag) { $stack = array_slice($stack, 0, $k); break; }
                    }
                } elseif (!in_array($tag, $void, true) && !preg_match('~/\s*>$~', $token)) {
                    $stack[] = $tag;
                }
            }
            $out .= $token;
            continue;
        }
        if ($total >= 3 || !array_intersect($stack, ['p','li']) || array_intersect($stack, $protected)) {
            $out .= $token;
            continue;
        }
        foreach ($candidates as $i => $candidate) {
            if ($total >= 3) break;
            if (isset($used[$i])) continue;
            // Longest candidate first; preserve exact matched text as the anchor.
            $phrasesHere = $candidate['phrases'];
            usort($phrasesHere, static fn($a,$b) => mb_strlen($b,'UTF-8') <=> mb_strlen($a,'UTF-8'));
            $escaped = array_map(static fn($x) => preg_quote($x, '~'), $phrasesHere);
            $pattern = '~(?<![\p{L}\p{N}\p{M}])(?:' . implode('|', $escaped) . ')(?![\p{L}\p{N}\p{M}])~iu';
            $changed = 0;
            $url = e($candidate['url']);
            $token = preg_replace_callback($pattern, static function($m) use ($url) {
                return '<a href="' . $url . '">' . $m[0] . '</a>';
            }, $token, 1, $changed) ?? $token;
            if ($changed > 0) {
                $used[$i] = true;
                $total++;
            }
        }
        $out .= $token;
    }
    return $out;
}

// Retained for previously implemented scheme-category links only.
function esy_editorial_links(string $type, string $slug, string $renderedBody = ''): string {
    if ($type !== 'yojna') return '';
    $links = [
        'sarva-shiksha-abhiyan-samagra-shiksha' => ['/category/shiksha',
          'अन्य शिक्षा योजनाएं और छात्र सहायता कार्यक्रम','Explore other education schemes and student support'],
        'nikshay-poshan-yojana' => ['/category/swasthya',
          'अन्य स्वास्थ्य योजनाएं और पात्रता गाइड','Explore other health schemes and eligibility guides'],
    ];
    if (!isset($links[$slug])) return '';
    [$path,$hi,$en] = $links[$slug];
    $label = tr('संबंधित जानकारी','Related reading');
    return '<section class="page-related" aria-label="' . e($label) . '"><h2>' . e($label) .
       '</h2><ul><li><a href="' . e(lurl($path)) . '">' . e(tr($hi,$en)) . '</a></li></ul></section>';
}
