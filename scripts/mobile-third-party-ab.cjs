// Read-only controlled mobile browser diagnostic; NEVER changes the site.
const { chromium, devices } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');

const TARGET = 'https://esarkariyojna.com/hi/';
const OUT = 'performance-ab-results';
fs.mkdirSync(OUT, { recursive: true });

// Keep business logic, styles, images and Google Fonts intact.
// Block only known tracking/advertising resource domains in isolated test pages.
const blockedHosts = [
  'googletagmanager.com', 'google-analytics.com', 'analytics.google.com',
  'doubleclick.net', 'googlesyndication.com', 'googleadservices.com',
  'googleads.g.doubleclick.net', 'pagead2.googlesyndication.com'
];
function shouldBlock(url) {
  try {
    const host = new URL(url).hostname.toLowerCase();
    return blockedHosts.some(domain => host === domain || host.endsWith('.' + domain));
  } catch (_) { return false; }
}
function rounded(value) {
  return value == null || !Number.isFinite(value) ? null : Math.round(value);
}
function median(values) {
  const sorted = values.filter(Number.isFinite).sort((a,b) => a - b);
  return sorted.length ? sorted[Math.floor(sorted.length / 2)] : null;
}

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const runs = [];
  try {
    // Alternating order reduces warm-cache and test-order bias.
    const sequence = ['baseline', 'blocked', 'blocked', 'baseline', 'baseline', 'blocked'];
    for (const [idx, mode] of sequence.entries()) {
      const context = await browser.newContext({
        ...devices['Pixel 5'],
        serviceWorkers: 'block',
        // Fresh isolated browser context per test.
        locale: 'hi-IN'
      });
      const page = await context.newPage();
      const blockedCounts = {};
      const thirdPartyCounts = {};
      if (mode === 'blocked') {
        await page.route('**/*', async route => {
          const url = route.request().url();
          if (shouldBlock(url)) {
            const host = new URL(url).hostname;
            blockedCounts[host] = (blockedCounts[host] || 0) + 1;
            await route.abort('blockedbyclient');
          } else {
            await route.continue();
          }
        });
      }
      page.on('request', req => {
        try {
          const host = new URL(req.url()).hostname;
          if (host !== 'esarkariyojna.com' && !host.endsWith('.esarkariyojna.com')) {
            thirdPartyCounts[host] = (thirdPartyCounts[host] || 0) + 1;
          }
        } catch (_) {}
      });
      await page.addInitScript(() => {
        window.__perfDiagnostics = { lcp: [], cls: 0, longTasks: [] };
        try {
          new PerformanceObserver(list => {
            for (const e of list.getEntries()) {
              window.__perfDiagnostics.lcp.push({
                startTime: e.startTime, size: e.size,
                element: e.element ? e.element.tagName : null,
                id: e.id || null, url: e.url || null
              });
            }
          }).observe({ type: 'largest-contentful-paint', buffered: true });
          new PerformanceObserver(list => {
            for (const e of list.getEntries()) {
              if (!e.hadRecentInput) window.__perfDiagnostics.cls += e.value;
            }
          }).observe({ type: 'layout-shift', buffered: true });
          new PerformanceObserver(list => {
            for (const e of list.getEntries()) {
              window.__perfDiagnostics.longTasks.push(e.duration);
            }
          }).observe({ type: 'longtask', buffered: true });
        } catch (err) {
          window.__perfDiagnostics.observerError = String(err);
        }
      });
      const cdp = await context.newCDPSession(page);
      await cdp.send('Network.enable');
      await cdp.send('Network.emulateNetworkConditions', {
        offline: false, latency: 150,
        downloadThroughput: 1.6 * 1024 * 1024 / 8,
        uploadThroughput: 750 * 1024 / 8
      });
      await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });
      const started = Date.now();
      let error = null, status = null;
      try {
        const response = await page.goto(TARGET, { waitUntil: 'domcontentloaded', timeout: 45000 });
        status = response ? response.status() : null;
        await page.waitForTimeout(15000);
      } catch (e) {
        error = String(e);
      }
      const measured = await page.evaluate(() => {
        const nav = performance.getEntriesByType('navigation')[0];
        const paints = performance.getEntriesByType('paint');
        const firstPaint = paints.find(e => e.name === 'first-contentful-paint');
        const perf = window.__perfDiagnostics || {};
        return {
          fcp: firstPaint?.startTime ?? null,
          lcp: perf.lcp?.length ? perf.lcp.at(-1) : null,
          lcpEntries: perf.lcp?.length ?? 0,
          cls: perf.cls ?? null,
          longTaskCount: perf.longTasks?.length ?? 0,
          longTaskTotalMs: perf.longTasks?.reduce((a,b) => a+b, 0) ?? null,
          domContentLoaded: nav?.domContentLoadedEventEnd ?? null,
          load: nav?.loadEventEnd ?? null,
          observerError: perf.observerError ?? null,
          title: document.title,
          h1: document.querySelector('h1')?.textContent?.trim() ?? null
        };
      }).catch(e => ({ evaluationError: String(e) }));
      const run = {
        iteration: idx + 1, mode, httpStatus: status, error,
        elapsedMs: Date.now() - started,
        measurements: measured,
        blockedRequests: blockedCounts,
        thirdPartyRequests: thirdPartyCounts
      };
      runs.push(run);
      console.log(JSON.stringify({
        iteration: run.iteration, mode, status,
        fcp: rounded(measured.fcp), lcp: rounded(measured.lcp?.startTime),
        cls: measured.cls, longTaskTotalMs: rounded(measured.longTaskTotalMs),
        blockedRequests: Object.values(blockedCounts).reduce((a,b)=>a+b,0),
        error
      }));
      await context.close();
    }
  } finally {
    await browser.close();
  }
  const summary = {};
  for (const mode of ['baseline', 'blocked']) {
    const subset = runs.filter(r => r.mode === mode && r.httpStatus === 200 && !r.error);
    summary[mode] = {
      successfulRuns: subset.length,
      medianLcpMs: median(subset.map(r => r.measurements.lcp?.startTime)),
      medianFcpMs: median(subset.map(r => r.measurements.fcp)),
      medianCls: median(subset.map(r => r.measurements.cls)),
      medianLongTaskTotalMs: median(subset.map(r => r.measurements.longTaskTotalMs))
    };
  }
  const delta = summary.baseline.medianLcpMs != null && summary.blocked.medianLcpMs != null
    ? summary.baseline.medianLcpMs - summary.blocked.medianLcpMs : null;
  const report = {
    generatedAt: new Date().toISOString(), target: TARGET,
    methodology: '3 browser-isolated repetitions each, alternating baseline vs third-party-blocked, Pixel 5, CDP 4x CPU and 1.6 Mbps download, 150 ms latency. Uses PerformanceObserver LCP/FCP and long tasks, NOT a Lighthouse score. Blocking ads/analytics can change page rendering; result is diagnostic, not causal proof.',
    hostsBlockedOnlyInTestBrowser: blockedHosts, summary,
    lcpDifferenceBaselineMinusBlockedMs: delta,
    runs
  };
  fs.writeFileSync(path.join(OUT, 'comparison.json'), JSON.stringify(report, null, 2));
  const lines = [
    '# Mobile third-party A/B diagnostics', '',
    'Live website / CMS / Google Ads are **not** changed by this test.',
    '', '| Metric | Baseline | Third parties blocked |', '|---|---:|---:|'
  ];
  for (const [label, key] of [
    ['Successful runs', 'successfulRuns'], ['Median LCP ms','medianLcpMs'],
    ['Median FCP ms','medianFcpMs'], ['Median CLS','medianCls'],
    ['Median long task ms', 'medianLongTaskTotalMs']
  ]) {
    lines.push('| '+label+' | '+String(summary.baseline[key])+' | '+String(summary.blocked[key])+' |');
  }
  lines.push('', 'Median LCP baseline minus blocked: **'+String(delta)+' ms**',
    '', 'Interpret carefully: blocking scripts can also remove ad placeholders and change LCP candidate, so this is a controlled diagnostic, not proof that tracking scripts cause the entire LCP.',
    'Inspect comparison.json for LCP element, HTTP status, blocked hosts, per-run metrics, and variance.');
  fs.writeFileSync(path.join(OUT, 'summary.md'), lines.join('\n')+'\n');
})().catch(error => { console.error(error); process.exitCode = 1; });
