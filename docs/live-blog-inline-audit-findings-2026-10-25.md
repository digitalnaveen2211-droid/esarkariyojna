# Follow-up to 42-URL live interlinking audit — 25 October 2026

The read-only GitHub Actions audit evaluated **all 42 blog URLs** from 21 known Hindi/English blog topics:

- HTTP 200: 42 / 42
- With at least one internal link in a p/li inside the main article prose: 38 / 42
- Without: 4 / 42
- Combined paragraph/list in-content occurrences: 70 (existing editorial links **plus** auto-generated links)

## Live findings / fixes
| URL | Finding | Resolution |
|---|---|---|
| EN Ayushman Vay Vandana | 0 links; the intro uses “Ayushman Bharat Pradhan Mantri Jan Arogya Yojana” | Add **that exact existing phrase** as a candidate anchor to the PM-JAY scheme page |
| EN PM-Kisan 23rd instalment | 0 links; the introduction uses “Pradhan Mantri Kisan Samman Nidhi (PM-KISAN) scheme” | Add exact existing phrases for the PM-Kisan scheme page |
| HI EPFO UAN/UMANG | 0 links; no tightly related published EPFO internal guide in curated inventory | Leave unlinked rather than suggest unrelated pension benefits |
| EN EPFO UAN/UMANG | Same | Same |

A corresponding target must still be published; the template never forces links where the article doesn't contain a matching phrase. If there are already CMS-authored links to a target, they take precedence.

## Better future measurement
Auto-inserted links now use `data-esy-inline="1"` on the `<a>` element. This HTML data attribute doesn't change visible text, presentation or SEO semantics, but allows future audits to distinguish generated inline anchors from manually authored content links.

## Known caveat
The 38/42 metric measures links inside p/li within the main prose, including manually authored “Further reading” paragraphs and linked list items. It is **not a claim that 38 blogs each have a natural link within their explanatory body sentences**. A stricter future editorial audit should separate introductory/answer prose from end-of-article further-reading blocks.

This change is limited to two verified extra anchor phrase variants and measurement tagging. No CMS edits, H1/body copy rewrites, canonical/sitemap edits or ads/tracking modifications.
