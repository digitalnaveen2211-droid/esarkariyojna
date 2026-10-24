#!/usr/bin/env python3
"""Summarize Chrome trace LCP candidate and paint timeline from Lighthouse --save-assets.
Read-only: operates on locally downloaded public-page traces in Actions artifacts.
"""
from __future__ import annotations

import glob
import json
import os
import re
from urllib.parse import urlsplit

ROOT = "lighthouse-diagnostics"
os.makedirs(ROOT, exist_ok=True)


def trace_files(mode: str) -> list[str]:
    return sorted(p for p in glob.glob(os.path.join(ROOT, "**", "*"), recursive=True)
                  if os.path.isfile(p) and
                  os.path.basename(p).lower().startswith(mode.lower()) and
                  ("trace" in os.path.basename(p).lower()) and p.endswith(".json"))


def load_events(path: str) -> list[dict]:
    with open(path, encoding="utf-8") as handle:
        raw = json.load(handle)
    if isinstance(raw, list):
        return raw
    return raw.get("traceEvents", [])


def event_data(event: dict) -> dict:
    args = event.get("args") or {}
    return args.get("data") or args.get("beginData") or args


def redacted_url(value) -> str | None:
    if not isinstance(value, str) or not value.startswith(("http://", "https://")):
        return None
    parsed = urlsplit(value)
    return parsed.hostname + parsed.path[:130] if parsed.hostname else None


def extract(mode: str) -> dict:
    report = {
        "mode": mode, "traceFound": False, "traceFile": None,
        "eventsProcessed": 0, "navigationStartCount": 0,
        "lcpCandidates": [], "lcpInvalidations": [],
        "paintEvents": [], "largestMainThreadTasks": [],
        "lcpDiagnosticFromLighthouse": None,
    }
    names = trace_files(mode)
    if not names:
        report["note"] = "No Lighthouse trace file was saved; inspect artifact and workflow logs."
        return report
    path = names[0]
    report["traceFound"] = True
    report["traceFile"] = os.path.relpath(path, ROOT)
    events = load_events(path)
    report["eventsProcessed"] = len(events)

    navigations = [e for e in events if str(e.get("name", "")).lower() in
                   ("navigationstart", "marknavigationstart")]
    report["navigationStartCount"] = len(navigations)
    # Use navigationStart's timestamp for the same renderer process when possible.
    nav_by_process = {}
    for e in navigations:
        pid = e.get("pid")
        if pid is not None:
            nav_by_process[pid] = min(nav_by_process.get(pid, float("inf")), e.get("ts", float("inf")))
    all_nav = min((e.get("ts", float("inf")) for e in navigations), default=None)

    def ms_from_navigation(e):
        ts = e.get("ts")
        if not isinstance(ts, (int, float)):
            return None
        base = nav_by_process.get(e.get("pid"), all_nav)
        return round((ts - base) / 1000, 1) if base is not None else None

    for e in events:
        name = str(e.get("name", ""))
        key = name.lower()
        data = event_data(e)
        ms = ms_from_navigation(e)
        if "largestcontentfulpaint" in key:
            item = {
                "event": name, "atMs": ms, "pid": e.get("pid"),
                "frame": data.get("frame") or data.get("frameId"),
                "nodeId": data.get("nodeId") or data.get("backendNodeId"),
                "candidateIndex": data.get("candidateIndex"),
                "size": data.get("size"),
                "type": data.get("type"),
                "url": redacted_url(data.get("imageUrl") or data.get("url")),
                "rawData": {k: v for k, v in data.items() if k in
                            ("isMainFrame", "isOutermostMainFrame", "navigationId",
                             "candidateIndex", "nodeId", "backendNodeId", "size", "type")},
            }
            if "invalidate" in key:
                report["lcpInvalidations"].append(item)
            else:
                report["lcpCandidates"].append(item)
        elif key in ("firstcontentfulpaint", "firstpaint", "markdomcontent", "markload",
                     "paint", "compositeLayers".lower()):
            if len(report["paintEvents"]) < 100:
                report["paintEvents"].append({"event": name, "atMs": ms, "pid": e.get("pid")})
        if name in ("RunTask", "ThreadControllerImpl::RunTask", "TaskQueueManager::ProcessTaskFromWorkQueue",
                    "EvaluateScript", "FunctionCall") and isinstance(e.get("dur"), (int, float)):
            duration = round(e["dur"] / 1000, 1)
            if duration >= 50:
                report["largestMainThreadTasks"].append({
                    "event": name, "atMs": ms, "durationMs": duration,
                    "hostAndPath": redacted_url(data.get("url") or data.get("scriptName"))
                })
    report["largestMainThreadTasks"] = sorted(
        report["largestMainThreadTasks"], key=lambda e: e["durationMs"], reverse=True)[:25]
    report["lcpCandidates"] = sorted(report["lcpCandidates"], key=lambda e: e["atMs"] or -1)
    report["lcpInvalidations"] = sorted(report["lcpInvalidations"], key=lambda e: e["atMs"] or -1)

    # Lighthouse's audited LCP and DOM node attribution are reported separately:
    matches = sorted(glob.glob(os.path.join(ROOT, mode + "*.report.json")))
    if not matches:
        matches = sorted(glob.glob(os.path.join(ROOT, mode + "*.json")))
        matches = [p for p in matches if "trace" not in p]
    if matches:
        with open(matches[0], encoding="utf-8") as handle:
            lh = json.load(handle)
        audits = lh.get("audits", {})
        lcp = audits.get("largest-contentful-paint") or {}
        element = audits.get("largest-contentful-paint-element") or {}
        report["lcpDiagnosticFromLighthouse"] = {
            "numericValueMs": lcp.get("numericValue"),
            "displayValue": lcp.get("displayValue"),
            "errorMessage": lcp.get("errorMessage"),
            "elementItems": (element.get("details") or {}).get("items", [])[:3],
            "runWarnings": lh.get("runWarnings", []),
            "runtimeError": lh.get("runtimeError"),
        }
    if not report["lcpCandidates"]:
        report["note"] = ("No Chrome trace LCP candidate events found. Do not label visible "
                          "page elements as the LCP element without actual event evidence.")
    return report


def main():
    output = {mode: extract(mode) for mode in ("mobile", "desktop")}
    with open(os.path.join(ROOT, "lcp-trace-events.json"), "w", encoding="utf-8") as handle:
        json.dump(output, handle, ensure_ascii=False, indent=2)
    lines = ["# Chrome trace LCP event diagnosis", "",
             "All events come from an isolated Lighthouse browser run; no production changes.", ""]
    for mode, result in output.items():
        lines.extend([
            f"## {mode.title()}", "",
            f"- Trace: {result['traceFile'] or 'not found'}",
            f"- Events scanned: {result['eventsProcessed']}",
            f"- Navigation starts: {result['navigationStartCount']}",
            f"- LCP candidates: {len(result['lcpCandidates'])}",
            f"- LCP invalidations: {len(result['lcpInvalidations'])}",
        ])
        audit = result.get("lcpDiagnosticFromLighthouse") or {}
        lines.extend([
            f"- Lighthouse LCP: {audit.get('displayValue') or audit.get('numericValueMs') or 'unavailable'}",
            f"- Runtime error: {audit.get('runtimeError')}",
        ])
        for c in result["lcpCandidates"][-8:]:
            lines.append(f"  - Candidate: {c['atMs']}ms; nodeId={c['nodeId']}; "
                         f"size={c['size']}; type={c['type']}; URL={c['url']}")
        for t in result["largestMainThreadTasks"][:10]:
            lines.append(f"  - Long task: {t['durationMs']}ms at {t['atMs']}ms ({t['event']})")
        if result.get("note"):
            lines.append(f"- Note: {result['note']}")
        lines.append("")
    with open(os.path.join(ROOT, "lcp-trace-summary.md"), "w", encoding="utf-8") as handle:
        handle.write("\n".join(lines) + "\n")
    print("\n".join(lines))


if __name__ == "__main__":
    main()
