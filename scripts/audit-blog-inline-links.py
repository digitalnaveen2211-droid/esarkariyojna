#!/usr/bin/env python3
"""Read-only live audit: 21 blog topics x Hindi and English. Python stdlib only."""
import csv, json, re, sys
from concurrent.futures import ThreadPoolExecutor
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urljoin, urlparse
from urllib.request import Request, urlopen

BASE = "https://esarkariyojna.com"
ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "blog-link-audit"
OUT.mkdir(exist_ok=True)

def get_slugs():
    source = (ROOT / "app/seo_editorial_links.php").read_text(encoding="utf-8")
    section = source.split("static $blogLinks = [", 1)[1].split("\n    ];", 1)[0]
    slugs = re.findall(r"^\s*'([a-z0-9-]+)'\s*=>\s*\[", section, re.M)
    if len(slugs) != 21 or len(set(slugs)) != 21:
        raise RuntimeError("Expected 21 curated blog slugs, got " + str(len(slugs)))
    return sorted(slugs)

class ProseParser(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.tags = []
        self.prose_depth = 0
        self.active_link = None
        self.links = []
        self.has_prose = False
    def handle_starttag(self, tag, attrs):
        props = dict(attrs)
        self.tags.append(tag)
        if tag == "div" and "prose" in props.get("class", "").split() and not self.prose_depth:
            self.prose_depth = len(self.tags)
            self.has_prose = True
        if tag == "a" and self.prose_depth:
            self.active_link = {
                "href": props.get("href", ""),
                "anchor": "",
                "in_paragraph": any(t in ("p", "li") for t in self.tags),
                "auto_marker": props.get("data-esy-inline") == "1"
            }
    def handle_data(self, value):
        if self.active_link is not None:
            self.active_link["anchor"] += value
    def handle_endtag(self, tag):
        if tag == "a" and self.active_link is not None:
            self.links.append(self.active_link)
            self.active_link = None
        for i in range(len(self.tags) - 1, -1, -1):
            if self.tags[i] == tag:
                if self.prose_depth and i < self.prose_depth:
                    self.prose_depth = 0
                self.tags = self.tags[:i]
                break

def audit(url):
    row = {
        "live_url": url, "language": url.split("/")[3],
        "status": "", "in_prose_blog_yojna_links": 0,
        "paragraph_blog_yojna_links": 0, "auto_marked_links": 0,
        "target_urls": "", "anchor_text": "", "result": "", "error": ""
    }
    try:
        request = Request(url, headers={"User-Agent": "Mozilla/5.0 (compatible; ESYEditorialAudit/1.0)"})
        with urlopen(request, timeout=25) as response:
            row["status"] = response.status
            final_url = response.geturl()
            html = response.read(2000000).decode("utf-8", "replace")
        parser = ProseParser()
        parser.feed(html)
        found = []
        for link in parser.links:
            full = urljoin(final_url, link["href"])
            p = urlparse(full)
            if p.hostname not in ("esarkariyojna.com", "www.esarkariyojna.com"):
                continue
            if not re.fullmatch(r"/(hi|en)/(blog|yojna)/[a-z0-9-]+/?", p.path):
                continue
            if p.path.rstrip("/") == urlparse(url).path.rstrip("/"):
                continue
            found.append((link, full))
        row["in_prose_blog_yojna_links"] = len(found)
        row["paragraph_blog_yojna_links"] = sum(x["in_paragraph"] for x, _ in found)
        row["auto_marked_links"] = sum(x["auto_marker"] for x, _ in found)
        row["target_urls"] = " | ".join(full for _, full in found)
        row["anchor_text"] = " | ".join(x["anchor"].strip().replace("|", "/") for x, _ in found)
        row["result"] = ("INLINE LINKS PRESENT" if row["paragraph_blog_yojna_links"] else
                         "NO INLINE LINKS") if parser.has_prose else "MISSING PROSE"
    except Exception as exc:
        row["status"] = "ERROR"
        row["result"] = "FETCH ERROR"
        row["error"] = str(exc)[:250]
    return row

def main():
    urls = [f"{BASE}/{lang}/blog/{slug}" for slug in get_slugs() for lang in ("hi", "en")]
    with ThreadPoolExecutor(max_workers=5) as executor:
        rows = list(executor.map(audit, urls))
    fields = list(rows[0].keys())
    with (OUT / "blog-inline-links.csv").open("w", encoding="utf-8-sig", newline="") as stream:
        writer = csv.DictWriter(stream, fieldnames=fields)
        writer.writeheader()
        writer.writerows(rows)
    (OUT / "blog-inline-links.json").write_text(json.dumps(rows, indent=2, ensure_ascii=False), encoding="utf-8")
    good = [r for r in rows if r["status"] == 200]
    linked = [r for r in good if r["paragraph_blog_yojna_links"] > 0]
    missing = [r for r in good if r["paragraph_blog_yojna_links"] == 0]
    errors = [r for r in rows if r["status"] != 200]
    summary = [
        "# 42 blog URL contextual-link audit", "",
        "Read-only crawl of live public pages. No CMS/database edits.", "",
        f"- URLs assessed: {len(rows)}",
        f"- HTTP 200: {len(good)}",
        f"- With paragraph-level blog/yojna links: {len(linked)}",
        f"- Without paragraph-level blog/yojna links: {len(missing)}",
        f"- Fetch/HTTP issues: {len(errors)}",
        f"- Total in-paragraph blog/yojna link occurrences: {sum(r['paragraph_blog_yojna_links'] for r in rows)}",
        "", "## Missing or unverified inline links", ""
    ]
    for row in missing + errors:
        summary.append(f"- {row['live_url']}: {row['result']}" +
                       (f" — {row['error']}" if row["error"] else ""))
    summary.extend(["", "## Caveats", "",
        "- Anchor count includes CMS-authored links and unmarked auto-generated links; earlier auto anchors were not tagged.",
        "- A linked page can still have poor anchor relevance. Check anchor_text + target_urls columns.",
        "- Link absence does not justify adding unrelated phrases or modifying programme facts.",
        "- Newer blogs not yet in curated mapping require separate review."
    ])
    body = "\n".join(summary) + "\n"
    (OUT / "summary.md").write_text(body, encoding="utf-8")
    print(body)

if __name__ == "__main__":
    main()
