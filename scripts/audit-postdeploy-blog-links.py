#!/usr/bin/env python3
"""Read-only live 42-URL blog link audit, no external dependencies."""
import csv
import json
import re
from collections import Counter
from concurrent.futures import ThreadPoolExecutor
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urljoin, urlparse
from urllib.request import Request, urlopen

BASE = "https://esarkariyojna.com"
ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "blog-link-postdeploy"
OUT.mkdir(exist_ok=True)
VOID = {"area", "base", "br", "col", "embed", "hr", "img", "input", "link", "meta", "param", "source", "track", "wbr"}

def get_slugs():
    text = (ROOT / "app/seo_editorial_links.php").read_text(encoding="utf-8")
    block = text.split("static $blogLinks = [", 1)[1].split("\n    ];", 1)[0]
    names = re.findall(r"^\s*'([a-z0-9-]+)'\s*=>\s*\[", block, re.M)
    if len(names) != 21 or len(set(names)) != 21:
        raise RuntimeError("Expected 21 unique existing blog topics; got " + str(len(names)))
    return sorted(names)

class ArticleParser(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.tags = []
        self.prose_depth = 0
        self.seen_prose = False
        self.current = None
        self.links = []

    def handle_starttag(self, tag, attrs):
        data = dict(attrs)
        self.tags.append(tag)
        if tag == "div" and "prose" in data.get("class", "").split() and self.prose_depth == 0:
            self.prose_depth = len(self.tags)
            self.seen_prose = True
        if tag == "a" and self.prose_depth:
            self.current = {
                "href": data.get("href", ""),
                "anchor": "",
                "paragraph": "p" in self.tags or "li" in self.tags,
                "marker": data.get("data-esy-inline", "") == "1"
            }
        if tag in VOID:
            self.tags.pop()

    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)
        if tag not in VOID: self.handle_endtag(tag)

    def handle_data(self, data):
        if self.current is not None:
            self.current["anchor"] += data

    def handle_endtag(self, tag):
        if tag == "a" and self.current is not None:
            self.links.append(self.current)
            self.current = None
        for i in range(len(self.tags) - 1, -1, -1):
            if self.tags[i] == tag:
                if self.prose_depth and i < self.prose_depth: self.prose_depth = 0
                del self.tags[i:]
                break

def check(url):
    row = {
        "url": url, "status": "", "inline_internal_count": 0, "inline_auto_marked_count": 0,
        "internal_targets": "", "internal_anchor_text": "", "external_count": 0,
        "external_unique_count": 0, "external_duplicate_count": 0, "external_domains": "",
        "external_links": "", "external_official_domains": "", "broken_internal_targets": "",
        "fetch_error": "", "prose_found": False
    }
    try:
        with urlopen(Request(url, headers={"User-Agent": "Mozilla/5.0 (compatible; EditorialQA/1.0)"}), timeout=30) as resp:
            row["status"] = resp.status
            final_url = resp.geturl()
            body = resp.read(3000000).decode("utf-8", "replace")
        parsed = ArticleParser()
        parsed.feed(body)
        row["prose_found"] = parsed.seen_prose
        internals = []
        externals = []
        for link in parsed.links:
            full = urljoin(final_url, link["href"])
            parts = urlparse(full)
            if parts.scheme not in ("http", "https") or not parts.hostname: continue
            host = parts.hostname.lower()
            if host in ("esarkariyojna.com", "www.esarkariyojna.com"):
                if re.fullmatch(r"/(?:hi|en)/(?:yojna|blog)/[a-z0-9-]+/?", parts.path) and parts.path.rstrip("/") != urlparse(url).path.rstrip("/"):
                    if link["paragraph"]: internals.append((link, full))
            else:
                externals.append((link, full, host))
        row["inline_internal_count"] = len(internals)
        row["inline_auto_marked_count"] = sum(link["marker"] for link, _ in internals)
        row["internal_targets"] = " | ".join(target for _, target in internals)
        row["internal_anchor_text"] = " | ".join(link["anchor"].replace("|", "/").strip() for link, _ in internals)
        row["external_count"] = len(externals)
        row["external_unique_count"] = len({target for _, target, _ in externals})
        row["external_duplicate_count"] = row["external_count"] - row["external_unique_count"]
        row["external_domains"] = " | ".join(sorted({host for _, _, host in externals}))
        row["external_official_domains"] = " | ".join(sorted({host for _, _, host in externals if host.endswith(".gov.in") or host.endswith(".nic.in") or host.endswith(".gov") or host.endswith(".ac.in")}))
        row["external_links"] = json.dumps([{"url": target, "anchor": link["anchor"].strip(), "domain": host} for link, target, host in externals], ensure_ascii=False)
    except Exception as exc:
        row["status"] = "ERROR"
        row["fetch_error"] = str(exc)[:250]
    return row

if __name__ == "__main__":
    all_urls = [f"{BASE}/{lang}/blog/{slug}" for slug in get_slugs() for lang in ("hi", "en")]
    with ThreadPoolExecutor(max_workers=5) as pool:
        results = list(pool.map(check, all_urls))
    # Verify *actual target pages* after collecting every blog's rendered links.
    # Check each distinct destination once; maintain bounded concurrency and don't
    # misclassify anti-bot 403/429 for external sites as a missing page.
    from urllib.error import HTTPError
    unique_internal = sorted({
        u.strip() for row in results for u in row["internal_targets"].split(" | ")
        if u.strip()
    })
    def check_target(url):
        try:
            with urlopen(Request(url, headers={"User-Agent": "Mozilla/5.0 (compatible; EditorialQA/1.0)"}), timeout=15) as response:
                return {"url": url, "status": response.status, "resolved_url": response.geturl(), "error": ""}
        except HTTPError as exc:
            return {"url": url, "status": exc.code, "resolved_url": url, "error": str(exc)[:160]}
        except Exception as exc:
            return {"url": url, "status": "ERROR", "resolved_url": url, "error": str(exc)[:160]}
    with ThreadPoolExecutor(max_workers=8) as pool:
        checked = list(pool.map(check_target, unique_internal))
    target_status = {x["url"]: x for x in checked}
    (OUT / "internal-target-status.json").write_text(json.dumps(checked, ensure_ascii=False, indent=2), encoding="utf-8")
    with (OUT / "internal-target-status.csv").open("w", newline="", encoding="utf-8-sig") as target_file:
        writer = csv.DictWriter(target_file, fieldnames=["url", "status", "resolved_url", "error"])
        writer.writeheader()
        writer.writerows(checked)
    for row in results:
        url_list = [x.strip() for x in row["internal_targets"].split(" | ") if x.strip()]
        problems = [x for x in url_list if target_status[x]["status"] != 200]
        row["broken_internal_targets"] = " | ".join(
            x + " [HTTP " + str(target_status[x]["status"]) + "]" for x in problems
        )
    with (OUT / "postdeploy-blog-links.csv").open("w", newline="", encoding="utf-8-sig") as f:
        writer = csv.DictWriter(f, fieldnames=list(results[0].keys()))
        writer.writeheader()
        writer.writerows(results)
    (OUT / "postdeploy-blog-links.json").write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8")
    ok = [r for r in results if r["status"] == 200 and r["prose_found"]]
    no_inline = [r for r in ok if not r["inline_internal_count"]]
    duplicates = [r for r in ok if r["external_duplicate_count"]]
    errors = [r for r in results if r not in ok]
    summary = ["# Post-deployment audit: blog internal + external links", "",
        "Read-only crawl of current public HTML; no CMS or website mutations.", "",
        f"- Blog URLs: {len(results)}", f"- HTTP 200 + article body found: {len(ok)}",
        f"- Pages with in-paragraph internal blog/yojna links: {len(ok)-len(no_inline)}",
        f"- Pages without in-paragraph internal links: {len(no_inline)}",
        f"- Total in-paragraph internal links: {sum(r['inline_internal_count'] for r in ok)}",
        f"- Auto-generated links with data-esy-inline marker: {sum(r['inline_auto_marked_count'] for r in ok)}",
        f"- External link occurrences: {sum(r['external_count'] for r in ok)}",
        f"- Duplicate external link occurrences: {sum(r['external_duplicate_count'] for r in ok)}",
        f"- Pages with repeated exact external targets: {len(duplicates)}",
        f"- Pages with >1 external link: {sum(r['external_count'] > 1 for r in ok)}",
        f"- Unique internal linked targets checked: {len(checked)}",
        f"- Internal destinations returning HTTP 200: {sum(x['status'] == 200 for x in checked)}",
        f"- Internal destinations needing review: {sum(x['status'] != 200 for x in checked)}",
        "", "## No paragraph-level internal links"]
    summary += [f"- {r['url']}" for r in no_inline]
    summary += ["", "## Repeated identical external targets"]
    summary += [f"- {r['url']}: {r['external_duplicate_count']} repeats" for r in duplicates]
    summary += ["", "## Internal link destinations needing review"]
    summary += [f"- {x['url']}: {x['status']} ({x['error']})" for x in checked if x["status"] != 200]
    summary += ["", "## Fetch / parse errors"]
    summary += [f"- {r['url']}: {r['fetch_error'] or r['status']}" for r in errors]
    summary += ["", "## Interpretation",
        "- Internal counts include pre-existing editorial anchors; use marker count for code-added anchors.",
        "- 'Official domain' means domain name matches a government-like suffix; editorial verification remains necessary.",
        "- Internal linked target URLs are independently fetched and status checked once each; external links are inventoried, not individually fetched.",
        "- Duplicate external link detection compares resolved exact URLs; tracking-query variants may still duplicate semantically.",
        "- Articles with different necessary official actions should not be forced into a one-external-link limit."
    ]
    report = "\n".join(summary) + "\n"
    (OUT / "summary.md").write_text(report, encoding="utf-8")
    print(report)
