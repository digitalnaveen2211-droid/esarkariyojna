#!/usr/bin/env python3
"""Read-only full sitemap SEO audit; no CMS/database changes."""
import collections
import concurrent.futures
import csv
import json
import os
import time
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
from html.parser import HTMLParser

BASE = "https://esarkariyojna.com"
SITEMAP = BASE + "/sitemap.xml"
UA = "eSarkariYojna-SEO-Audit/2.0 (read-only; GitHub Actions)"
LIMIT = 1000

class PageParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.active = None
        self.title = []
        self.h1s = []
        self.current_h1 = []
        self.meta = {}
        self.links = []
        self.lang = ""
        self.schema_blocks = 0
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == "html":
            self.lang = attrs.get("lang", "")
        if tag == "title":
            self.active = "title"
        if tag == "h1":
            self.active = "h1"
            self.current_h1 = []
        if tag == "meta" and attrs.get("name"):
            self.meta[attrs["name"].lower()] = attrs.get("content", "")
        if tag == "link":
            self.links.append(attrs)
        if tag == "script" and attrs.get("type") == "application/ld+json":
            self.schema_blocks += 1
    def handle_data(self, text):
        if self.active == "title":
            self.title.append(text)
        elif self.active == "h1":
            self.current_h1.append(text)
    def handle_endtag(self, tag):
        if tag == "h1" and self.active == "h1":
            self.h1s.append(" ".join(self.current_h1).strip())
            self.active = None
        elif tag == "title" and self.active == "title":
            self.active = None

def fetch(url):
    try:
        request = urllib.request.Request(url, headers={"User-Agent": UA})
        with urllib.request.urlopen(request, timeout=18) as response:
            return {"status": response.status, "final_url": response.url,
                    "content_type": response.headers.get("Content-Type", ""),
                    "body": response.read(3000000).decode("utf-8", "replace")}
    except urllib.error.HTTPError as exc:
        return {"status": exc.code, "final_url": exc.url, "error": str(exc)}
    except Exception as exc:
        return {"status": None, "final_url": "", "error": str(exc)}

def relation(link, name):
    rel = link.get("rel", "")
    return name in (rel.lower().split() if isinstance(rel, str) else rel)

def check(url):
    result = fetch(url)
    row = {"url": url, "status": result["status"],
           "final_url": result.get("final_url", ""),
           "error": result.get("error", ""), "issues": []}
    if result["status"] != 200:
        row["issues"].append("non_200_or_unreachable")
        return row
    if row["final_url"] != url:
        row["issues"].append("redirect_in_sitemap")
    if "text/html" not in result.get("content_type", "").lower():
        row["issues"].append("not_html")
        return row
    p = PageParser()
    p.feed(result["body"])
    title = " ".join(" ".join(p.title).split())
    description = p.meta.get("description", "").strip()
    canonicals = [l.get("href", "") for l in p.links if relation(l, "canonical")]
    alternates = {l.get("hreflang"): l.get("href") for l in p.links
                  if relation(l, "alternate") and l.get("hreflang")}
    row.update(title=title, description=description, h1=p.h1s,
               canonical=canonicals, hreflang=alternates, html_lang=p.lang,
               robots=p.meta.get("robots", ""), schema_blocks=p.schema_blocks)
    if not title:
        row["issues"].append("missing_title")
    if not description:
        row["issues"].append("missing_description")
    if len(p.h1s) != 1 or not p.h1s[0]:
        row["issues"].append("missing_or_multiple_h1")
    if len(canonicals) != 1:
        row["issues"].append("missing_or_multiple_canonical")
    elif canonicals[0] != url:
        row["issues"].append("canonical_mismatch")
    if "noindex" in row["robots"].lower():
        row["issues"].append("noindex_in_sitemap")
    if not alternates.get("hi") or not alternates.get("en"):
        # A missing translation may be legitimate; report for review.
        row["issues"].append("missing_language_alternate_review")
    if url.startswith(BASE + "/en/") and p.lang != "en":
        row["issues"].append("html_lang_mismatch")
    if url.startswith(BASE + "/hi/") and p.lang != "hi":
        row["issues"].append("html_lang_mismatch")
    return row

def main():
    os.makedirs("seo-audit-output", exist_ok=True)
    sitemap = fetch(SITEMAP)
    if sitemap["status"] != 200:
        raise SystemExit("Sitemap unavailable: " + json.dumps({k:v for k,v in sitemap.items() if k != "body"}))
    root = ET.fromstring(sitemap["body"])
    ns = {"s": "http://www.sitemaps.org/schemas/sitemap/0.9"}
    urls = [node.text.strip() for node in root.findall(".//s:loc", ns) if node.text]
    urls = list(dict.fromkeys(u for u in urls if u.startswith(BASE + "/")))[:LIMIT]
    print("Scanning", len(urls), "URLs from sitemap", flush=True)
    # Respect site load: at most 3 simultaneous read-only requests.
    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
        rows = list(pool.map(check, urls))
    title_groups = collections.defaultdict(list)
    description_groups = collections.defaultdict(list)
    for row in rows:
        if row.get("status") == 200:
            if row.get("title"):
                title_groups[row["title"]].append(row["url"])
            if row.get("description"):
                description_groups[row["description"]].append(row["url"])
    for row in rows:
        if row.get("title") and len(title_groups[row["title"]]) > 1:
            row["issues"].append("duplicate_title")
        if row.get("description") and len(description_groups[row["description"]]) > 1:
            row["issues"].append("duplicate_description")
        for lang, alt in row.get("hreflang", {}).items():
            if lang in ("hi", "en") and alt not in urls:
                row["issues"].append("alternate_not_in_sitemap")
                break
    counts = collections.Counter(issue for row in rows for issue in set(row["issues"]))
    summary = {"base": BASE, "sitemap_url_count": len(urls),
               "http_statuses": dict(collections.Counter(str(r["status"]) for r in rows)),
               "issue_counts": dict(counts), "issues_found": sum(bool(r["issues"]) for r in rows),
               "note": "Review language-alternate warnings: some pages legitimately have no translation."}
    with open("seo-audit-output/full-seo-report.json", "w", encoding="utf-8") as f:
        json.dump({"summary": summary, "pages": rows}, f, ensure_ascii=False, indent=2)
    with open("seo-audit-output/seo-issues.csv", "w", encoding="utf-8", newline="") as f:
        writer = csv.writer(f)
        writer.writerow(["URL", "HTTP", "Issues", "Canonical", "Title", "Description"])
        for row in rows:
            if row["issues"]:
                writer.writerow([row["url"], row["status"], "; ".join(row["issues"]),
                                 "; ".join(row.get("canonical", [])),
                                 row.get("title", ""), row.get("description", "")])
    with open("seo-audit-output/summary.md", "w", encoding="utf-8") as f:
        f.write("# Live sitemap SEO audit\n\n")
        f.write("URLs checked: " + str(len(urls)) + "\n\n")
        for issue, count in counts.most_common():
            f.write("- " + issue + ": " + str(count) + "\n")
    print(json.dumps(summary, indent=2), flush=True)

if __name__ == "__main__":
    main()
