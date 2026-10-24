const { chromium } = require('playwright');
const fs = require('node:fs');
(async () => {
  fs.mkdirSync('lcp-lifecycle', {recursive:true});
  const browser = await chromium.launch({headless:true, args:['--no-sandbox','--disable-dev-shm-usage']});
  const result = [];
  const cases = [
    {label:'mobile-default', viewport:{width:390,height:844}, block:false},
    {label:'mobile-no-google-scripts', viewport:{width:390,height:844}, block:true},
    {label:'desktop-default', viewport:{width:1365,height:768}, block:false}
  ];
  for (const scenario of cases) {
    const context = await browser.newContext({viewport:scenario.viewport,deviceScaleFactor:1,isMobile:scenario.label.startsWith('mobile'),hasTouch:scenario.label.startsWith('mobile')});
    if (scenario.block) await context.route(/googletagmanager\.com|googlesyndication\.com|doubleclick\.net|google-analytics\.com|googleadservices\.com/, route=>route.abort());
    await context.addInitScript(() => {
      window.__lcpAudit = {installedAt:performance.now(), support:!!window.PerformanceObserver, lcp:[], paints:[], states:[], errors:[]};
      const rec = window.__lcpAudit;
      const observe = (type, output) => {
        try { new PerformanceObserver(list=>{for (const e of list.getEntries()) output.push({name:e.name,startTime:Math.round(e.startTime),size:e.size||null,tag:e.element?.tagName||null,id:e.element?.id||null,renderTime:e.renderTime||null,loadTime:e.loadTime||null});}).observe({type,buffered:true}); }
        catch(e){rec.errors.push(type+': '+String(e));}
      };
      observe('largest-contentful-paint',rec.lcp);
      observe('paint',rec.paints);
      const recordState = (event)=>rec.states.push({event,ms:Math.round(performance.now()),visibility:document.visibilityState,hidden:document.hidden});
      document.addEventListener('visibilitychange',()=>recordState('visibilitychange'));
      document.addEventListener('DOMContentLoaded',()=>recordState('DOMContentLoaded'));
      window.addEventListener('load',()=>recordState('load'));
      window.addEventListener('pagehide',()=>recordState('pagehide'));
      window.addEventListener('pageshow',()=>recordState('pageshow'));
      ['pointerdown','keydown','touchstart'].forEach(type=>document.addEventListener(type,()=>recordState(type),{once:true,capture:true}));
    });
    const page=await context.newPage();
    const errors=[];
    page.on('pageerror', e=>errors.push(String(e).slice(0,300)));
    const started=Date.now();
    let status=null, navigationError=null;
    try {const res=await page.goto('https://esarkariyojna.com/hi/',{waitUntil:'domcontentloaded',timeout:45000});status=res?.status();}catch(e){navigationError=String(e);}
    await page.waitForTimeout(7000);
    const data=await page.evaluate(()=>{
      const h1=document.querySelector('.esy-hero-content h1')||document.querySelector('h1');
      const rect=h1?.getBoundingClientRect();
      return {audit:window.__lcpAudit,visibility:document.visibilityState,
        readyState:document.readyState, nav:performance.getEntriesByType('navigation').map(e=>({type:e.type,domContentLoaded:e.domContentLoadedEventEnd,load:e.loadEventEnd})),
        h1: h1? {textLength:h1.textContent.trim().length,rect:rect?{x:rect.x,y:rect.y,w:rect.width,h:rect.height}:null,visible:!!rect&&rect.width>0&&rect.height>0}:null,
        documentHeight:document.documentElement.scrollHeight,
        adsIns:document.querySelectorAll('ins.adsbygoogle').length,
        scriptElements:document.querySelectorAll('script').length};
    });
    result.push({label:scenario.label,status,elapsedMs:Date.now()-started,navigationError,pageErrors:errors.slice(0,10),...data});
    await context.close();
  }
  await browser.close();
  fs.writeFileSync('lcp-lifecycle/results.json', JSON.stringify({note:'Synthetic unthrottled browser check. Not a Lighthouse score or real-user LCP measurement.',cases:result},null,2));
  let summary=['# Browser LCP observer and lifecycle checks','','All visits read-only. LCP is a browser-observed candidate, not a Lighthouse score.','',
    '| Test | HTTP | LCP candidates | Last LCP ms | FCP ms | H1 visible | Page hidden |',
    '|---|---:|---:|---:|---:|---|---|'];
  for(const r of result){const lc=r.audit?.lcp||[]; const paint=(r.audit?.paints||[]).find(p=>p.name==='first-contentful-paint');
    summary.push('| '+r.label+' | '+(r.status||'err')+' | '+lc.length+' | '+(lc.at(-1)?.startTime??'none')+' | '+(paint?.startTime??'none')+' | '+String(r.h1?.visible||false)+' | '+String(r.visibility!=='visible')+' |');
  }
  summary.push('','Review results.json for observer errors, lifecycle changes, H1 bounds and JS errors. Blocking Google requests is only a diagnostic browser condition, not a production recommendation.');
  fs.writeFileSync('lcp-lifecycle/summary.md',summary.join('\n')+'\n');
  console.log(summary.join('\n'));
})().catch(e=>{console.error(e); process.exitCode=1;});
