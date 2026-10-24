// Diagnostic only: records real mobile paint candidates, screenshots and CPU/network timing.
// All operations run in an isolated GitHub runner browser; the production site is unchanged.
const { chromium, devices } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');

const out = 'mobile-lcp-timeline';
fs.mkdirSync(out, { recursive: true });
const target = 'https://esarkariyojna.com/hi/';
function safeNum(x) { return typeof x === 'number' && Number.isFinite(x) ? Math.round(x) : null; }

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const report = { target, generatedAt: new Date().toISOString(), runs: [] };
  try {
    for (let run = 1; run <= 2; run++) {
      const context = await browser.newContext({ ...devices['Pixel 5'], serviceWorkers: 'block' });
      const page = await context.newPage();
      await page.addInitScript(() => {
        window.__lcpTimeline = { candidates: [], shifts: [], tasks: [], errors: [] };
        try {
          new PerformanceObserver(list => {
            for (const e of list.getEntries()) {
              const el = e.element;
              window.__lcpTimeline.candidates.push({
                atMs: e.startTime, size: e.size, id: e.id || null,
                url: e.url || null, tag: el?.tagName || null,
                className: typeof el?.className === 'string' ? el.className.slice(0, 200) : null,
                text: (el?.textContent || '').trim().slice(0, 180),
                boundingBox: el ? (() => { const r=el.getBoundingClientRect(); return {x:r.x,y:r.y,width:r.width,height:r.height}; })() : null
              });
            }
          }).observe({ type:'largest-contentful-paint', buffered:true });
        } catch (e) { window.__lcpTimeline.errors.push('LCP observer: ' + e); }
        try {
          new PerformanceObserver(list => {
            for (const e of list.getEntries()) if(!e.hadRecentInput) {
              window.__lcpTimeline.shifts.push({ atMs:e.startTime, value:e.value });
            }
          }).observe({ type:'layout-shift', buffered:true });
        } catch (e) { window.__lcpTimeline.errors.push('CLS observer: ' + e); }
        try {
          new PerformanceObserver(list => {
            for (const e of list.getEntries()) window.__lcpTimeline.tasks.push({
              atMs:e.startTime, durationMs:e.duration
            });
          }).observe({ type:'longtask', buffered:true });
        } catch (e) { window.__lcpTimeline.errors.push('longtask observer: ' + e); }
      });
      const cdp = await context.newCDPSession(page);
      await cdp.send('Network.enable');
      await cdp.send('Network.emulateNetworkConditions', {
        offline:false, latency:150, downloadThroughput:1.6*1024*1024/8,
        uploadThroughput:750*1024/8
      });
      await cdp.send('Emulation.setCPUThrottlingRate', { rate:4 });
      const requests = [];
      page.on('requestfinished', async req => {
        const url = req.url();
        const timing = req.timing();
        requests.push({
          host: (() => {try {return new URL(url).hostname;}catch{return ''}})(),
          path: (() => {try {return new URL(url).pathname;}catch{return ''}})().slice(0,180),
          type:req.resourceType(), responseEndMs:safeNum(timing.responseEnd),
          startTimeMs:safeNum(timing.startTime)
        });
      });
      const snapshots = [];
      let status = null, error = null;
      try {
        const res = await page.goto(target, { waitUntil:'domcontentloaded', timeout:60000 });
        status = res?.status() ?? null;
        // Grab both early and late DOM/visual state even if PerformanceObserver produces no entries.
        for (const at of [2500, 6000, 12000, 16000]) {
          const previous = snapshots.length ? snapshots.at(-1).targetTime : 0;
          await page.waitForTimeout(at - previous);
          await page.screenshot({ path:path.join(out, 'run-'+run+'-'+at+'ms.png'), fullPage:false });
          const data = await page.evaluate(() => {
            const perf = window.__lcpTimeline || {};
            const fcp = performance.getEntriesByType('paint')
              .find(e => e.name === 'first-contentful-paint')?.startTime ?? null;
            const els = [...document.querySelectorAll('h1,h2,img,p,.esy-portal-hero,.esy-hero-content')];
            const visible = els.map(el => {
              const box=el.getBoundingClientRect();
              return {
                tag:el.tagName, id:el.id||null,
                cls: typeof el.className === 'string' ? el.className.slice(0,100) : null,
                text:(el.textContent||'').trim().slice(0,130),
                src:el.tagName==='IMG' ? el.currentSrc : null,
                x:Math.round(box.x), y:Math.round(box.y),
                width:Math.round(box.width),height:Math.round(box.height),
                area:Math.round(Math.max(0,Math.min(box.right,innerWidth)-Math.max(0,box.left)) *
                    Math.max(0,Math.min(box.bottom,innerHeight)-Math.max(0,box.top)))
              };
            }).filter(e => e.area > 0).sort((a,b)=>b.area-a.area).slice(0,12);
            return {
              perfNowMs:performance.now(), fcpMs:fcp,
              lcpCandidates:perf.candidates||[], lcpObserverErrors:perf.errors||[],
              clsEntries:perf.shifts||[], longTasks:perf.tasks||[],
              visibleCandidates:visible, readyState:document.readyState
            };
          });
          snapshots.push({ targetTime:at, ...data });
        }
      } catch (e) { error=String(e); }
      report.runs.push({ run, httpStatus:status,error,snapshots,requests });
      console.log('Run '+run+': status '+status+', candidate count '+
        (snapshots.at(-1)?.lcpCandidates?.length ?? 0)+', error '+String(error));
      await context.close();
    }
  } finally { await browser.close(); }
  fs.writeFileSync(path.join(out,'report.json'), JSON.stringify(report,null,2));
  let md='# Mobile LCP paint timeline\n\nAll runs use a separate isolated Chrome mobile browser, with 4x CPU throttling and 1.6Mbps download; no changes to production.\n\n';
  for (const r of report.runs) {
    md+='## Run '+r.run+'\n\nHTTP '+r.httpStatus+', error: '+(r.error||'none')+'\n\n';
    for (const s of r.snapshots) {
      const last=s.lcpCandidates.at(-1);
      md+='- '+s.targetTime+'ms snapshot: FCP '+safeNum(s.fcpMs)+
        'ms, LCP candidates '+s.lcpCandidates.length+
        (last ? ', last candidate '+last.tag+' at '+safeNum(last.atMs)+'ms, '+last.text : ', none observed')+
        ', largest visible DOM item '+(s.visibleCandidates[0]?.tag||'none')+
        ' ('+(s.visibleCandidates[0]?.text||'').slice(0,75)+')\n';
    }
    md+='\n';
  }
  md+='NOTE: browser PerformanceObserver may not report LCP in all headless runs. Visible DOM elements are fallback evidence only and **not** confirmed LCP candidates. Compare screenshots and raw JSON against Lighthouse trace.\n';
  fs.writeFileSync(path.join(out,'summary.md'),md);
})().catch(e=>{console.error(e);process.exitCode=1});
