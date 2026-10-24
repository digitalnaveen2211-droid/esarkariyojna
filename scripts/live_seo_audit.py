#!/usr/bin/env python3
"""Read-only public SEO check; writes a local JSON report, never touches CMS."""
import json
import re
import urllib.request
import urllib.error
import xml.etree.ElementTree as ET
from html.parser import HTMLParser

BASE = "https://esarkariyojna.com"

class HTMLHead(HTMLParser):
    def __init__(self):
        super().__init__()
        self.title = ""
        self.h1 = ""
        self.active = ""
        self.meta = {}
        self.links = []
        self.jsonld = 0
    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag in ("title", "h1"):
            self.active = tag
        if tag == "meta" and a.get("name"):
            self.meta[a["name"].lower()] = a.get("content", "")
        if tag == "link":
            self.links.append(a)
        if tag == "script" and a.get("type") == "application/ld+json":
            self.jsonld += 1
    def handle_data(self, text):
        if self.active == "title":
            self.title += text
        if self.active == "h1":
            self.h1 += text
    def handle_endtag(self, tag):
        if self.active == tag:
            self.active = ""

def fetch(url):
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "eSarkariYojna-SEO-Audit/1.0"})
        with urllib.request.urlopen(req, timeout=15) as response:
            return {"url": url, "final_url": response.url, "status": response.status,
                    "content_type": response.headers.get("Content-Type", ""),
                    "body": response.read(2500000).decode("utf-8", "replace")}
    except urllib.error.HTTPError as error:
        return {"url": url, "status": error.code, "error": str(error)}
    except Exception as error:
        return {"url": url, "error": str(error)}

def inspect_page(url):
    data = fetch(url)
    if "body" not in data:
        return data
    parser = HTMLHead()
    parser.feed(data.pop("body"))
    canonicals = [x.get("href", "") for x in parser.links if x.get("rel", "").lower() == "canonical"]
    hreflang = {x.get("hreflang"): x.get("href") for x in parser.links
                if x.get("rel", "").lower() == "alternate" and x.get("hreflang")}
    data.update(title=parser.title.strip(), h1=parser.h1.strip(),
                description=parser.meta.get("description", ""), robots=parser.meta.get("robots", ""),
                canonicals=canonicals, hreflang=hreflang, jsonld_blocks=parser.jsonld)
    warnings = []
    for field in ("title", "h1", "description"):
        if not data[field]:
            warnings.append("Missing " + field)
    if not canonicals:
        warnings.append("Missing canonical")
    if not hreflang and "noindex" not in data["robots"].lower():
        warnings.append("Missing hreflang")
    data["warnings"] = warnings
    return data

report = {}
for name in ("robots.txt", "sitemap.xml"):
    result = fetch(BASE + "/" + name)
    report[name] = {k: v for k, v in result.items() if k != "body"}
    if name == "robots.txt":
        report[name]["excerpt"] = result.get("body", "")[:2500]
    else:
        try:
            root = ET.fromstring(result.get("body", ""))
            ns = {"s": "http://www.sitemaps.org/schemas/sitemap/0.9"}
            urls = [n.text for n in root.findall(".//s:loc", ns) if n.text]
            report[name]["valid_xml"] = True
            report[name]["url_count"] = len(urls)
        except ET.ParseError as error:
            urls = []
            report[name]["valid_xml"] = False
            report[name]["parse_error"] = str(error)
samples = [BASE + p for p in ("/", "/hi/", "/en/", "/hi/blog", "/en/blog")]
for url in urls:
    if url.startswith(BASE + "/") and url not in samples:
        samples.append(url)
    if len(samples) == 15:
        break
report["pages"] = [inspect_page(url) for url in samples]
with open("seo-audit-report.json", "w", encoding="utf-8") as f:
    json.dump(report, f, ensure_ascii=False, indent=2)
print("ROBOTS:", report["robots.txt"])
print("SITEMAP:", report["sitemap.xml"])
for page in report["pages"]:
    print(page.get("status", "ERROR"), page["url"], page.get("warnings", page.get("error", "OK")))
print("Report saved as seo-audit-report.json")
