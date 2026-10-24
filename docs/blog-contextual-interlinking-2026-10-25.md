# Contextual blog interlinking rollout — 25 October 2026

Basis: prior 256-URL sitemap audit (158 schemes, 42 blog language URLs = 21 blog topics, 30 scheme category URLs, 14 blog category URLs), and current GitHub CMS views and routing.

## Intent
Make each existing blog guide point to **up to three genuinely related** published blog articles or scheme details, with localized anchor text taken directly from the linked post's current CMS title. Curated topics, not automatic keyword matches.

## Examples
- PM-Kisan eKYC/payment problems ↔ beneficiary lists ↔ PM-Kisan eligibility and scheme
- Ayushman eligibility ↔ Ayushman Vay Vandana ↔ hospital list/cashless claims ↔ PM-JAY scheme
- Sukanya withdrawal ↔ interest and maturity calculator ↔ scheme details
- Ujjwala LPG eligibility → Ujjwala scheme
- PMAY Urban 2.0 application → PMAY-U scheme; PMAY-G as a clearly distinct housing scheme
- PMJJBY renewal → PMJJBY scheme, PMSBY and Jan Dhan
- Bihar Mahila Rojgar → its own official scheme guide and related women's livelihood services

## Engineering safeguards
- Existing 21 blog topics are covered by **59 editorially chosen candidate connections**, generally 1–3 per guide.
- For each request, linked target must exist in CMS as a **published** post. English view links only to a target with actual English title/content.
- No self-links. Skips exact matching links already found in rendered article body. Maximum three.
- Dynamically displays the current CMS target title as bilingual anchor; no dated or hardcoded benefit amount in link text.
- Renders section near end of full article **inside the existing prose wrapper**; no alteration to saved CMS content or editor HTML.
- Previous two GSC scheme-level links are preserved.
- No new links to category pages from blogs merely for the sake of adding links; category navigation already exists elsewhere.
- No H1/title/meta changes, no DB modifications, no sitemap/canonical/hreflang changes, no tracking/ad code edits.

## After merge
Check live Hindi and English versions of a representative PM-Kisan blog, Ayushman blog, Sukanya blog, Ujjwala blog; review links for relevance and HTTP 200. Confirm no duplicate links, empty labels, layout problems, wrong language, or unexpected mobile shifts. Inspect output source to ensure links are crawlable `<a href>`, not click handlers.

## Caution
The sitemap data is a previously captured snapshot. Newly published blogs **will not automatically get editorial mappings**: update the curated array after publishing new content, or extend the mapping via a separately reviewed CMS interface in a later PR.
