# 25 October 2026 — GSC SEO metadata proposals (five URLs)

These are proposed **search snippets**, not changes to article body, eligibility conditions, page titles (H1), or JSON-LD headlines. Data: last-28-day user-provided GSC Pages export. CTR is based on a small sample and rankings/CTR gains cannot be guaranteed.

| Language / Page | Impressions | Clicks | Average position | Metadata opportunity |
|---|---:|---:|---:|---|
| EN Sukanya withdrawal blog | 116 | 0 | 7.94 | 50% education limit and early closure answer |
| EN Samagra Shiksha scheme | 137 | 2 | 9.24 | SSA + RMSA + Teacher Education merger |
| HI PM-JAY eligibility blog | 55 | 0 | 5.96 | family eligibility vs separate 70+ provision |
| EN Vay Vandana blog | 70 | 0 | 8.86 | 70+ age condition and application |
| EN Ni-kshay Poshan scheme | 60 | 0 | 8.83 | nutritional DBT and payment status |

## Verified content findings
- Sukanya withdrawal live page already explains Class 10/age 18 rule, prior financial-year-end 50% ceiling, and marriage closure. Avoid changing the body simply to insert keywords.
- Hindi PM-JAY eligibility live page opens with a useful distinction between general family-list eligibility and 70+ age-based criteria.
- English Vay Vandana live page includes enrolment, Aadhaar eKYC, and family-based ₹5 lakh cover nuances.
- The Samagra Shiksha official portal confirms the earlier schemes were Sarva Shiksha Abhiyan, Rashtriya Madhyamik Shiksha Abhiyan and Teacher Education. Source: https://samagra.education.gov.in/about.html
- PIB announced Ni-kshay Poshan nutritional support increase to ₹1,000 per month on 7 Oct 2024. Source: https://www.pib.gov.in/PressReleasePage.aspx?PRID=2062928
- The Samagra and Ni-kshay individual page content could not be reliably opened through the public browser reader. Verify the full currently published CMS content before approving metadata for those two.

## Safe production procedure (manual only)
Deployment will **not** run a content migration. The PHP script reads the actual five CMS records on the VPS and prints current vs proposed values. No content change on preview.

```bash
cd /path/to/your/site
php scripts/review-gsc-first-five-metadata.php
```

Read the output. If the proposals are accurate and you want them applied, then deliberately run:

```bash
php scripts/review-gsc-first-five-metadata.php --apply --confirm=GSC-FIRST-FIVE-2026-10-25
```

This optional command updates **only** the five requested locale-specific SEO title/description pairs, creates a private JSON backup in the operating system temporary directory before applying changes, and performs an atomic database transaction with original-value guards. It does not change slugs, body HTML, H1, translations on the other locale, canonicals, ads, tracking or sitemap.

**Approval note:** Re-read any dates and payments before applying. If current SEO metadata is better than a proposed replacement, **do not run apply**; edit via CMS instead. Preserve the backup securely for rollback. Check all five live HTML <title> and description tags after applying. Request Google recrawl only if the changes are useful and indexable.

## Avoid changing
- Existing authoritative, substantial article body content merely to raise word count.
- Fact-based programme eligibility or benefit amounts without official verification.
- Other language's metadata; do not duplicate Hindi/English snippets.
- CMS metadata automatically via every deployment.
