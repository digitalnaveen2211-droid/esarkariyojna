# Draft CMS API connection check

This workflow verifies that the newly added `ESY_DRAFT_API_TOKEN` secret works against the live eSarkariYojna custom CMS.

## Steps

1. Ensure `ESY_DRAFT_API_TOKEN` exists in **Settings → Secrets and variables → Actions**. Never reveal the token in a chat, code file or screenshot.
2. Merge this PR.
3. In GitHub → **Actions → Verify Draft CMS API connection → Run workflow**, select the **main** branch and click Run.
4. Open the job result. Success shows counts of existing scheme records and categories, plus `Mode: create_draft_only`.
5. If HTTP 401, rotate the CMS token and update the secret. If HTTP 403, check HTTPS/reverse-proxy configuration. If 404/5xx, inspect VPS deployment/WAF; never turn off HTTPS enforcement to fix it.

### Important constraints

This workflow is **read-only** (GET). It has no ability to create or publish drafts. It deliberately does not log the token or response details like scheme titles.

**Additional integration still required for daily automatic imports:** The ChatGPT scheduled research task does not automatically have GitHub Actions permissions or API-secret access. A trusted integration must transfer verified bilingual draft payloads into an authenticated runner. Do not commit unpublished content to a public repository; use a private, authenticated draft queue/runner. Until such an integration is available, continue delivering review-ready files and only claim CMS imports when verified.

No deploy or merge is performed by this PR automatically.
