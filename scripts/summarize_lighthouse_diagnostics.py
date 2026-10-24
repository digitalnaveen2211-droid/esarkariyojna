#!/usr/bin/env python3
"""Summarize Lighthouse artifact JSON without requesting private CMS data."""
import glob
import json
import os

OUT = "lighthouse-diagnostics"
os.makedirs(OUT, exist_ok=True)
lines = ["# Lighthouse LCP diagnostics", ""]
for mode in ("mobile", "desktop"):
    paths = glob.glob(os.path.join(OUT, mode + "*.json"))
    reports = [p for p in paths if "trace" not in p and "devtools" not in p and "artifacts" not in p]
    lines.append("## " + mode.title())
    if not reports:
        lines.append("Lighthouse report JSON not created; see exit-codes.txt and workflow job log.")
        lines.append("")
        continue
    with open(reports[0], encoding="utf-8") as file:
        report = json.load(file)
    lines.extend([
        "- Lighthouse version: " + str(report.get("lighthouseVersion", "unknown")),
        "- Run warnings: " + str(report.get("runWarnings", [])),
        "- Runtime error: " + str(report.get("runtimeError")),
        "- Performance score: " + str((report.get("categories", {}).get("performance") or {}).get("score")),
    ])
    for audit_id in ("first-contentful-paint", "largest-contentful-paint",
                     "total-blocking-time", "cumulative-layout-shift",
                     "speed-index", "lcp-discovery-insight", "lcp-breakdown-insight"):
        audit = report.get("audits", {}).get(audit_id, {})
        lines.append("- " + audit_id + ": " + str(audit.get("displayValue", audit.get("errorMessage", "missing"))))
    lines.append("")
path = os.path.join(OUT, "summary.md")
with open(path, "w", encoding="utf-8") as file:
    file.write("\n".join(lines) + "\n")
print("\n".join(lines))
