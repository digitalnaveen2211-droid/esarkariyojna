import json
from pathlib import Path
from statistics import median
root=Path("applied-mobile-ab")
modes=["baseline","analytics","ads","both"]
keys=["largest-contentful-paint","total-blocking-time","first-contentful-paint","cumulative-layout-shift"]
rows=[]
for mode in modes:
 for run in (1,2):
  p=root/f"{mode}-{run}.report.json"
  if not p.exists():
   rows.append({"mode":mode,"run":run,"error":"missing report"});continue
  try:
   data=json.loads(p.read_text())
   rows.append({"mode":mode,"run":run,"score":(data.get("categories",{}).get("performance") or {}).get("score"),
      "throttling":data.get("configSettings",{}).get("throttlingMethod"),
      "metrics":{k:(data.get("audits",{}).get(k) or {}).get("numericValue") for k in keys},
      "runtimeError":data.get("runtimeError")})
  except Exception as e: rows.append({"mode":mode,"run":run,"error":str(e)})
summary={}
for mode in modes:
 group=[r for r in rows if r["mode"]==mode and r.get("metrics",{}).get("largest-contentful-paint") is not None]
 def med(vals):
  v=[x for x in vals if isinstance(x,(int,float))]
  return round(median(v),2) if v else None
 summary[mode]={"valid":len(group),"score":med([r.get("score")*100 if r.get("score") is not None else None for r in group]),**{k:med([r["metrics"].get(k) for r in group]) for k in keys}}
baseline=summary["baseline"]["largest-contentful-paint"]
for mode in modes:
 val=summary[mode]["largest-contentful-paint"]
 summary[mode]["lcpGainMs"]=round(baseline-val,2) if baseline is not None and val is not None else None
(root/"comparison.json").write_text(json.dumps({"summary":summary,"runs":rows,"note":"2 runs per condition, applied throttling; request blocking can alter page layout and LCP element."},indent=2))
lines=["# Mobile applied Lighthouse A/B","", "All blocking occurs in an isolated test browser; production tracking and ads unchanged.","","| Mode | Valid | Score | LCP ms | TBT ms | FCP ms | LCP gain ms |","|---|---:|---:|---:|---:|---:|---:|"]
for mode,s in summary.items():
 lines.append(f"| {mode} | {s['valid']}/2 | {s['score']} | {s['largest-contentful-paint']} | {s['total-blocking-time']} | {s['first-contentful-paint']} | {s['lcpGainMs']} |")
(root/"summary.md").write_text("\n".join(lines)+"\n")
print("\n".join(lines))
