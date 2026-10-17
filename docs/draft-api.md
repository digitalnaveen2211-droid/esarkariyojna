# e-Sarkari Yojna — Secure Draft-Only API

The API creates new **scheme drafts only**. It cannot publish or edit existing records.

## Setup
1. Deploy PR after review. No database schema migration is required.
2. Log in as an administrator who has \`settings.manage\`, open \`/admin/?p=draft_import\`.
3. Click **Generate / Rotate Token** and store the one-time 64-character key in a secure secret manager.
4. Integrate an authorized runner using HTTPS and the \`X-Draft-Token\` HTTP request header. NEVER put the token in a GitHub commit or client-side JavaScript.
5. Optionally revoke the integration instantly using **Disable API**.

## GET discovery
\`GET https://esarkariyojna.com/admin/draft-api.php\`
Headers: \`X-Draft-Token: <secret>\`

Returns \`existing_schemes\` (including draft and published), \`categories\` and \`write_mode\`. Always use this before proposing/importing a batch.

## POST import
\`POST https://esarkariyojna.com/admin/draft-api.php\`
Headers: \`X-Draft-Token: <secret>\` and \`Content-Type: application/json\`

Example body (replace placeholders and expand the text to at least 800 chars **per language**):
\`\`\`json
{
  "drafts": [
    {
      "slug": "example-scheme-2026",
      "category_slug": "kisan",
      "title_hi": "उदाहरण योजना",
      "title_en": "Example Scheme",
      "summary_hi": "पात्र आवेदकों के लिए उदाहरण योजना की जानकारी।",
      "summary_en": "An example informational description of the scheme.",
      "elig_hi": "आधिकारिक पात्रता के अनुसार",
      "elig_en": "Subject to the official eligibility rules",
      "content_hi_md": "## योजना क्या है?\n[Write 800+ Hindi characters, plain text]\n\n## पात्रता\n...\n\n## आवेदन\n...",
      "content_en_md": "## What is this scheme?\n[Write 800+ English characters, plain text]\n\n## Eligibility\n...\n\n## How to apply\n...",
      "seo_title": "उदाहरण योजना: पात्रता, लाभ और आवेदन",
      "seo_title_en": "Example Scheme: Eligibility, Benefits and How to Apply",
      "seo_desc": "उदाहरण योजना की पात्रता, लाभ, दस्तावेज और आवेदन की जानकारी।",
      "seo_desc_en": "Check the example scheme's eligibility, benefits, documents and application procedure.",
      "keywords": "example, योजना",
      "official_url": "https://www.india.gov.in/",
      "source_urls": ["https://www.india.gov.in/"]
    }
  ]
}
\`\`\`

Successful POST response: HTTP 201, each record has \`status: "draft"\`, admin \`edit_url\` and \`published: 0\`.

## Security/operations
- No create/update/delete/publish operation on existing posts; batch fails if a title or slug already exists.
- Requires HTTPS, 256-bit bearer-style token hashed with SHA-256 in CMS settings.
- Max 5 drafts per POST and 25 successful draft creates per IST day.
- Requires Hindi **and** English, SEO metadata, official \`https://*.gov.in\` or \`https://*.nic.in\` sources, and an existing category slug.
- Article text is escaped into safe headings/lists/paragraphs; embedded HTML/script is not accepted as markup.
- Private API token shown only once and never included in success/errors/log details.
- Human editorial fact-check and the final **Publish** click remain mandatory.

## Integration limitation
The scheduled ChatGPT research automation is separate from this endpoint. It will only create drafts in CMS if an authorized integration with an approved secret-handling path actually becomes available. Do not place a credential in an automation prompt.
