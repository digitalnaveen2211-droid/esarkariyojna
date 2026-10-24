# First five GSC opportunities — editorial review (25 October 2026)

Source: user-supplied GSC Performance export, last 28 days. These are **page-level metrics**; separate Queries export does not establish exact query-to-page associations. Avoid claiming these are direct CTR experiments.

| Page | Impressions | Clicks | Avg position | Priority |
|---|---:|---:|---:|---|
| EN Sukanya withdrawal blog | 116 | 0 | 7.94 | Review title/snippet against education withdrawal and early closure search intent |
| EN Samagra Shiksha scheme | 137 | 2 | 9.24 | Confirm definition and earlier schemes clearly in introduction |
| HI Ayushman PM-JAY eligibility blog | 55 | 0 | 5.96 | Front-load practical eligibility and verified senior 70+ distinction |
| EN Ayushman Vay Vandana blog | 70 | 0 | 8.86 | Clear who qualifies, enrolment, and family cover in opening section |
| EN Nikshay Poshan scheme | 60 | 0 | 8.83 | Ensure TB nutrition support, treatment period and status process are explained accurately |

## Changes shipped in this PR
- Targeted contextual **internal navigation links** near the bottom of these five pages.
- Bilingual link labels.
- Uses existing `.page-related` styles already in the theme. No content/CMS DB edits, no title/meta changes.
- Related links are intentionally limited to verifiable existing live blog or category routes; no new factual scheme claims.

## CMS editorial recommendations — not auto-applied
1. Inspect **current CMS SEO title and meta description** before editing. Existing user-written titles may already be optimized.
2. Use a question-first opening that immediately answers the dominant topic, **only after verifying official scheme sources and current rules**. The existing guides are long and substantive; avoid word-count padding.
3. Make metadata distinct in Hindi and English; don't translate without reviewing search intent.
4. Avoid adding FAQPage rich-result markup just to chase snippets. FAQ structured-data visibility is limited and doesn't guarantee visibility.
5. Compare GSC Performance at 28 days post-indexing or after recrawl, focusing on page-level impressions/clicks/position; very small volume means CTR is noisy.

## QA
Open both language variants where available; inspect related links for correct local language and HTTP 200. Ensure no duplicate headings and layout stays consistent. Before any manual CMS metadata edits, save the previous values for rollback.
