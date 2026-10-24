#!/usr/bin/env python3
"""Read-only public HTML inventory: classify analytics/advertising loaders and ad placement.

Never output raw inline scripts, CMS snippets, identifiers, cookies or HTML.
This is an HTML-level diagnostic, not proof about requests injected later by JS.
"""
import json
import re
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urlsplit
from urllib.request import Request, urlopen

URLS = ("https://esarkariyojna.com/hi/", "https://esarkariyojna.com/en/")
REPORT = Path("public-script-audit")
REPORT.mkdir(exist_ok=True)

def classify(value):
    u = (value or "").lower()
    if "googletagmanager.com/gtm.js" in u or "googletagmanager.com/ns.html" in u:
        return "GTM"
    if "googletagmanager.com/gtag/js" in u or "google-analytics.com/analytics.js" in u:
        return "Direct Analytics Loader"
    if "googlesyndication.com/pagead/js/adsbygoogle.js" in u:
        return "AdSense Loader"
    if "googlesyndication.com" in u or "doubleclick.net" in u or "googleadservices.com" in u:
        return "Google Ads Resource"
    if "google-analytics.com" in u:
        return "Google Analytics Resource"
    return "Other"

class Inventory(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.scripts = []
        self.iframes = []
        self.ads = []
        self.h1 = []
        self.events = []
        self.inline = False
        self.inline_text = []
        self.position = 0
        self.current_h1 = False
    def handle_starttag(self, tag, attrs):
        self.position += 1
        a = dict(attrs)
        if tag == "script":
            src = a.get("src", "")
            self.scripts.append({"position": self.position, "type": "external" if src else "inline", "category": classify(src)})
            self.inline = not bool(src)
            self.inline_text = []
        if tag == "iframe":
            self.iframes.append({"position": self.position, "category": classify(a.get("src", ""))})
        if tag == "div" and "ad" in a.get("class", "").split():
            self.ads.append({"position": self.position, "slot": a.get("data-slot", "unspecified"),
                             "type": a.get("data-type", "unknown")})
        if tag == "h1":
            self.h1.append(self.position)
            self.current_h1 = True
        if tag in ("header", "main", "footer"):
            self.events.append({"position": self.position, "element": tag})
        if tag == "ins" and "adsbygoogle" in a.get("class", "").split():
            self.ads.append({"position": self.position, "slot": "adsense-inline", "type": "ins"})
    def handle_data(self, data):
        if self.inline:
            self.inline_text.append(data)
    def handle_endtag(self, tag):
        if tag == "script" and self.inline:
            data = "".join(self.inline_text)
            indicators = []
            if "googletagmanager.com/gtm.js" in data: indicators.append("GTM bootstrap")
            if "googletagmanager.com/gtag/js" in data or re.search(r"gtag\s*\(\s*['\"]config['\"]", data):
                indicators.append("Direct gtag loader/config")
            if "adsbygoogle.js" in data: indicators.append("AdSense loader reference")
            if "adsbygoogle.push" in data or "adsbygoogle = " in data:
                indicators.append("AdSense initialization")
            self.scripts[-1]["indicators"] = indicators
            self.inline = False
            self.inline_text = []
        if tag == "h1": self.current_h1 = False

def analyze(url):
    request = Request(url, headers={"User-Agent": "Mozilla/5.0 (compatible; SiteScriptAudit/1.0)",
                                     "Accept": "text/html"})
    try:
        with urlopen(request, timeout=30) as response:
            status = response.status
            content_type = response.headers.get("Content-Type", "")
            final_url = response.url
            html = response.read(3 * 1024 * 1024).decode("utf-8", "replace")
        if "text/html" not in content_type:
            return {"url": url, "status": status, "error": "Not HTML response"}
        inv = Inventory()
        inv.feed(html)
        counts = {}
        for s in inv.scripts:
            for label in ([s["category"]] if s["type"] == "external" else s.get("indicators", [])):
                counts[label] = counts.get(label, 0) + 1
        gtm_count = counts.get("GTM bootstrap", 0) + counts.get("GTM", 0)
        adsense_loaders = counts.get("AdSense Loader", 0) + counts.get("AdSense loader reference", 0)
        direct_gtag = counts.get("Direct Analytics Loader", 0) + counts.get("Direct gtag loader/config", 0)
        earliest_h1 = min(inv.h1) if inv.h1 else None
        ads_before_h1 = [a for a in inv.ads if earliest_h1 is not None and a["position"] < earliest_h1]
        return {"url": url, "finalUrl": final_url, "httpStatus": status,
                "htmlBytes": len(html.encode("utf-8")), "counts": counts,
                "scriptCount": len(inv.scripts), "iframeCategories": inv.iframes,
                "adPlacements": inv.ads, "firstH1Position": earliest_h1,
                "adPlacementsBeforeH1": ads_before_h1,
                "duplicateSignals": {
                    "multipleGtmBootstraps": gtm_count > 1,
                    "multipleAdSenseLoaders": adsense_loaders > 1,
                    "directGtagAlongsideGtm": gtm_count > 0 and direct_gtag > 0
                },
                "limitations": "Server HTML only; JavaScript may inject loaders or ads later. "
                               "Missing markers are not proof that a network resource never loads."}
    except Exception as exc:
        return {"url": url, "error": type(exc).__name__ + ": " + str(exc)[:250]}

def main():
    results = [analyze(url) for url in URLS]
    (REPORT / "summary.json").write_text(json.dumps({"results": results}, indent=2, ensure_ascii=False))
    lines = ["# Public Script & Ad Placement Audit", "",
             "Read-only inspection of public server HTML. No CMS data or configuration was changed.",
             ""]
    for res in results:
        lines.append("## " + res["url"])
        if "error" in res:
            lines.append("Error: " + res["error"])
            continue
        lines.extend(["HTTP status: " + str(res["httpStatus"]),
                      "External/inline script tags: " + str(res["scriptCount"]),
                      "Categories: " + json.dumps(res["counts"]),
                      "Duplicate markers: " + json.dumps(res["duplicateSignals"]),
                      "CMS ad slots: " + json.dumps(res["adPlacements"]),
                      "Ad placements before H1: " + json.dumps(res["adPlacementsBeforeH1"]),
                      ""])
    lines += ["## Interpret cautiously", "",
              "A detected duplicate is a high-priority finding to inspect, not a license to remove production monetization.",
              "GTM Preview is still required to verify GA4 firing count. CMS custom HTML and late-injected requests "
              "may not be visible in HTML source. This is not a Lighthouse speed test."]
    (REPORT / "summary.md").write_text("\n".join(lines) + "\n")
    print("\n".join(lines))

if __name__ == "__main__":
    main()
