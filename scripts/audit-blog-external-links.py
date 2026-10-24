#!/usr/bin/env python3
"""Read-only audit of external anchors inside live blog article bodies."""
import csv, json, re, ssl
from concurrent.futures import ThreadPoolExecutor
from html.parser import HTMLParser
from pathlib import Path
from urllib.request import urlopen, Request
from urllib.parse import urljoin, urlparse

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "blog-external-audit"
OUT.mkdir(exist_ok=True)
BASE = "https://esarkariyojna.com"

def slugs():
    source=(ROOT/"app/seo_editorial_links.php").read_text(encoding="utf-8")
    section=source.split("static $blogLinks = [",1)[1].split("\n    ];",1)[0]
    names=re.findall(r"^\s*'([a-z0-9-]+)'\s*=>\s*\[",section,re.M)
    assert len(names)==21,(len(names),names)
    return names

class Parser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.stack=[]
        self.prose_depth=0
        self.link=None
        self.links=[]
    def handle_starttag(self,tag,attrs):
        props=dict(attrs)
        self.stack.append(tag)
        if tag=="div" and "prose" in props.get("class","").split() and self.prose_depth==0:
            self.prose_depth=len(self.stack)
        if tag=="a" and self.prose_depth:
            self.link={"href":props.get("href",""),"text":"","rel":props.get("rel",""),"target":props.get("target","")}
    def handle_data(self,text):
        if self.link is not None: self.link["text"]+=text
    def handle_endtag(self,tag):
        if tag=="a" and self.link is not None:
            self.links.append(self.link)
            self.link=None
        for i in range(len(self.stack)-1,-1,-1):
            if self.stack[i]==tag:
                if self.prose_depth and i<self.prose_depth: self.prose_depth=0
                self.stack=self.stack[:i]
                break

def audit(url):
    row={"url":url,"status":"","total_external_links":0,"distinct_external_domains":0,"domains":"","external_details":"","error":""}
    try:
        with urlopen(Request(url,headers={"User-Agent":"Mozilla/5.0 (compatible; ESYEditorialAudit/1.0)"}),timeout=30) as resp:
            row["status"]=resp.status
            resolved=resp.geturl()
            html=resp.read(2500000).decode("utf-8","replace")
        p=Parser();p.feed(html)
        external=[]
        for x in p.links:
            href=urljoin(resolved,x["href"])
            host=(urlparse(href).hostname or "").lower()
            if not host or host in ("esarkariyojna.com","www.esarkariyojna.com"): continue
            if urlparse(href).scheme not in ("http","https"):continue
            external.append({**x,"url":href,"domain":host})
        row["total_external_links"]=len(external)
        row["distinct_external_domains"]=len(set(x["domain"] for x in external))
        row["domains"]=" | ".join(sorted(set(x["domain"] for x in external)))
        row["external_details"]=json.dumps(external,ensure_ascii=False)
    except Exception as exc:
        row["status"]="ERROR";row["error"]=repr(exc)[:300]
    return row

if __name__=="__main__":
    urls=[f"{BASE}/{language}/blog/{slug}" for slug in slugs() for language in ("hi","en")]
    with ThreadPoolExecutor(max_workers=5) as pool: results=list(pool.map(audit,urls))
    with (OUT/"external-links.csv").open("w",encoding="utf-8-sig",newline="") as f:
        w=csv.DictWriter(f,fieldnames=list(results[0]));w.writeheader();w.writerows(results)
    (OUT/"external-links.json").write_text(json.dumps(results,indent=2,ensure_ascii=False),encoding="utf-8")
    total=sum(x["total_external_links"] for x in results)
    over=[x for x in results if x["total_external_links"]>1]
    zero=[x for x in results if x["total_external_links"]==0 and x["status"]==200]
    summary=["# Live external-link inventory (blog article body only)","",f"- URLs: {len(results)}",f"- HTTP 200: {sum(x['status']==200 for x in results)}",f"- Total external links: {total}",f"- Pages with >1 external link: {len(over)}",f"- Pages with 0 external links: {len(zero)}","", "## Pages with >1 external link"]
    summary.extend(f"- {x['url']}: {x['total_external_links']} links; {x['domains']}" for x in over)
    summary+=["","## Pages with zero external link"]
    summary.extend(f"- {x['url']}" for x in zero)
    summary+=["","This is an observational audit, not evidence that external links cause bounce. Retain important government/official references before any editorial reduction."]
    text="\n".join(summary)+"\n"
    (OUT/"summary.md").write_text(text,encoding="utf-8")
    print(text)
