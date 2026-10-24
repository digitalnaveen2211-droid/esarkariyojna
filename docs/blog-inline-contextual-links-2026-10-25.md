# Inline contextual linking for existing blog content

## What changes
- The standalone `Related reading / संबंधित जानकारी` section is removed **from blog articles**.
- Instead, a matching relevant phrase already present in the article's paragraph/list text becomes a standard HTML link to its corresponding published blog or scheme guide.
- The current 21-blog, 59-candidate mapping remains the scope. A phrase only links when it exists verbatim in the CMS content. Some articles may receive 0 links; no extra text is inserted merely to force links.
- One link maximum per text node and 3 links maximum per article.

## Safety
- On-the-fly PHP rendering only. CMS database text, headings and editor remain untouched.
- Matching is limited to p/li text nodes and intentionally skips existing anchors, headings, code/pre, scripts/styles and markup attributes.
- A matching target must exist and be published. English pages link only to English targets with real English body.
- Existing CMS-authored links to the same target take precedence.
- SEO links use exact matched natural visible phrases; no auto-generated keyword text.
- Previously added scheme detail category related-reading links remain as-is; only blog article related-reading boxes are replaced.
- Titles, canonical, structured data, schema, ads, GTM, GA4 and sitemap untouched.

## Manual review after deploy
Inspect Hindi and English versions of one PM-Kisan, Ayushman, Sukanya, and Ujjwala blog. Verify highlighted anchors are natural in the sentence and point to the correct corresponding guide. Verify no links inside headings or existing links. If a phrase is missing, leave that page unlinked rather than insert irrelevant text.

Run `php tests/blog-inline-anchor-test.php` where PHP CLI is available. Validate website with PHP syntax lint as part of the deploy pipeline.
