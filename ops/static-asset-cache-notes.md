# Optional static asset cache optimization — eSarkariYojna

**Status: NOT applied to VPS.** The website deploys via SSH/rsync into a running nginx server. No nginx configuration is stored in this repo, so a site-wide nginx edit without inspecting the active server block would be unsafe.

## Existing behavior
- Public CSS and JS links include a `?v=filemtime(...)` version query in `app/views/layout.php`.
- Uploaded card thumbnails have hashed names.
- Both techniques support reasonable browser caching.

## Proposed production cache policy
Review `nginx-static-asset-cache.conf` with the VPS administrator and integrate only into the correct `server {}` block. Review collisions with existing `location` statements, Cloudflare, upstream response headers, symlinks and other rewrite logic first. The `location` directives may override other regex locations; do not paste blindly.

After integration, validate with `sudo nginx -t` before `sudo systemctl reload nginx`. Keep an original configuration backup. Verify with:

```bash
curl -I 'https://esarkariyojna.com/assets/style.css'
curl -I 'https://esarkariyojna.com/assets/app.js'
curl -I 'https://esarkariyojna.com/hi/'
```

Expected assets: `Cache-Control` with max-age around 604800; homepage must remain dynamically served (must **not** gain the static asset cache rule). Uploaded images may use up to 2592000 seconds. Do not cache dynamic HTML, login, CMS, ad/click endpoints or user-specific pages.

**Limitation:** This will not directly fix the 11–12-second mobile LCP; it primarily improves repeat-visit caching. Review after the Accessibility contrast PR #84.
