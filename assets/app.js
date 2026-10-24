(function () {
  var html = document.documentElement;
  var lang = html.lang === 'en' ? 'en' : 'hi'; // language comes from the URL (/hi/ or /en/)

  function beacon(url, obj) {
    var data = JSON.stringify(obj);
    try {
      if (navigator.sendBeacon) navigator.sendBeacon(url, new Blob([data], { type: 'application/json' }));
      else fetch(url, { method: 'POST', body: data, keepalive: true, headers: { 'Content-Type': 'application/json' } });
    } catch (err) {}
  }

  /* Mobile menu */
  var toggle = document.querySelector('.nav-toggle'), nav = document.getElementById('mainNav');
  if (toggle && nav) toggle.addEventListener('click', function () {
    var open = nav.classList.toggle('open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  /* Declaration modal */
  var modal = document.getElementById('declModal');
  document.querySelectorAll('[data-open-modal]').forEach(function (b) { b.addEventListener('click', function () { modal.hidden = false; }); });
  document.querySelectorAll('[data-close-modal]').forEach(function (b) { b.addEventListener('click', function () { modal.hidden = true; }); });
  if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) modal.hidden = true; });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal) modal.hidden = true; });

  /* Search + category filter (home) */
  var grid = document.getElementById('grid'), qi = document.getElementById('q'), countEl = document.getElementById('count'), empty = document.getElementById('empty');
  var active = grid ? grid.dataset.active : 'all';
  var govFilter = 'all';
  var stateFilter = document.getElementById('stateFilter');
  var categoryFilter = document.getElementById('categoryFilter');
  var initialParams = new URLSearchParams(window.location.search);
  if (initialParams.get('gov') === 'central' || initialParams.get('gov') === 'state') govFilter = initialParams.get('gov');
  if (stateFilter && initialParams.get('state')) {
    var requestedState = initialParams.get('state');
    if (Array.from(stateFilter.options).some(function (opt) { return opt.value === requestedState; })) {
      stateFilter.value = requestedState;
      govFilter = 'state';
    }
  }
  function syncGovTabs() {
    document.querySelectorAll('[data-level]').forEach(function (button) {
      var selected = button.dataset.level === govFilter;
      button.classList.toggle('on', selected);
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
  }
  syncGovTabs();
  document.querySelectorAll('[data-level]').forEach(function (button) {
    button.addEventListener('click', function () {
      govFilter = button.dataset.level;
      if (govFilter !== 'state' && stateFilter) stateFilter.value = '';
      syncGovTabs();
      filter();
    });
  });
  if (stateFilter) stateFilter.addEventListener('change', function () {
    if (stateFilter.value) govFilter = 'state';
    syncGovTabs(); filter();
  });
  if (categoryFilter) categoryFilter.addEventListener('change', function () {
    // Category landing page has a canonical category URL; navigate to avoid inconsistent SEO.
    if (active !== 'all') {
      window.location.href = (html.lang === 'en' ? '/en/' : '/hi/') +
        (categoryFilter.value === 'all' ? 'yojnayein' : 'category/' + encodeURIComponent(categoryFilter.value));
      return;
    }
    active = categoryFilter.value;
    filter();
  });

  var limit = grid && grid.dataset.limit ? +grid.dataset.limit : 0;          // home: show only N, then "View more"
  var page = grid && grid.dataset.page ? +grid.dataset.page : 0;            // all-schemes page: N first, then "Load more"
  var step = grid && grid.dataset.step ? +grid.dataset.step : 8;
  var shown = page, matches = 0;
  var viewMore = document.getElementById('viewMore'), loadMore = document.getElementById('loadMore');
  if (loadMore) loadMore.addEventListener('click', function () { shown += step; filter(true); });
  function updateCount() {
    if (!grid || !countEl) return;
    var n = matches;
    countEl.textContent = (countEl.dataset[lang] || '{n}').replace('{n}', n);
    if (empty) empty.hidden = n !== 0;
  }
  function filter(keepPage) {
    if (!grid) return;
    if (keepPage !== true) shown = page;
    var term = (qi ? qi.value : '').trim().toLowerCase();
    var filtering = !!term || active !== 'all' || govFilter !== 'all' || (stateFilter && !!stateFilter.value);
    var max = page ? shown : (limit && !filtering ? limit : Infinity);
    matches = 0;
    grid.querySelectorAll('.card').forEach(function (c) {
      var okCat = active === 'all' || c.dataset.cat === active;
      var okQ = !term || term.split(/\s+/).every(function (w) { return c.dataset.search.indexOf(w) !== -1; });
      var okGov = govFilter === 'all' || c.dataset.gov === govFilter;
      var okState = !stateFilter || !stateFilter.value || c.dataset.state === stateFilter.value;
      var ok = okCat && okQ && okGov && okState;
      if (ok) matches++;
      c.hidden = !ok || matches > max;
    });
    grid.querySelectorAll('.ad').forEach(function (a) {
      var prev = a.previousElementSibling;
      a.hidden = filtering || !prev || prev.hidden;
    });
    if (viewMore) viewMore.hidden = filtering || matches <= limit;
    if (loadMore) loadMore.hidden = matches <= shown;
    var feat = document.querySelector('.featured');
    if (feat) feat.hidden = filtering;
    updateCount();
  }
  if (qi) {
    qi.addEventListener('input', filter);
    document.getElementById('searchForm').addEventListener('submit', function (e) {
      e.preventDefault(); filter();
      if (grid) grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }
  /* Category links use server navigation so URLs, canonical, title and description stay aligned. */
  // Measuring scrollWidth/clientWidth during parsing can force layout before first paint.
  // Keep rail controls responsive, but move the first geometry read off the critical path.
  function deferRailMeasurement(callback) {
    function enqueue() {
      if ('requestIdleCallback' in window) window.requestIdleCallback(callback, { timeout: 2500 });
      else window.setTimeout(callback, 0);
    }
    if (document.readyState === 'complete') enqueue();
    else window.addEventListener('load', enqueue, { once: true });
  }
  function throttledRailMeasurement(callback) {
    var queued = false;
    return function () {
      if (queued) return;
      queued = true;
      window.requestAnimationFrame(function () {
        queued = false;
        callback();
      });
    };
  }

  /* Horizontal homepage category rail: swipe/trackpad and arrow controls. */
  var catRail = document.querySelector('.home-categories-shell #cats');
  if (catRail) {
    var catPrev = document.querySelector('[data-cat-scroll="-1"]');
    var catNext = document.querySelector('[data-cat-scroll="1"]');
    function updateCatArrows() {
      var remaining = catRail.scrollWidth - catRail.clientWidth;
      if (catPrev) catPrev.disabled = catRail.scrollLeft <= 2;
      if (catNext) catNext.disabled = catRail.scrollLeft >= remaining - 2;
    }
    function moveCatRail(direction) {
      var first = catRail.querySelector('.cat');
      var distance = first ? (first.getBoundingClientRect().width + 10) * 3 : catRail.clientWidth * .65;
      catRail.scrollBy({ left: direction * distance, behavior: 'smooth' });
    }
    if (catPrev) catPrev.addEventListener('click', function () { moveCatRail(-1); });
    if (catNext) catNext.addEventListener('click', function () { moveCatRail(1); });
    var scheduleCatArrows = throttledRailMeasurement(updateCatArrows);
    catRail.addEventListener('scroll', scheduleCatArrows, { passive: true });
    window.addEventListener('resize', scheduleCatArrows);
    deferRailMeasurement(scheduleCatArrows);
  }

  /* Single-row category and state rails: arrows, keyboard focus, mobile swipe. */
  document.querySelectorAll('.esy-horizontal-rail[id]').forEach(function (rail) {
    var controls = Array.from(document.querySelectorAll('[data-rail-target]')).filter(function (b) {
      return b.dataset.railTarget === rail.id;
    });
    function updateRailControls() {
      var max = rail.scrollWidth - rail.clientWidth;
      controls.forEach(function (b) {
        b.disabled = max < 3 ||
          (+b.dataset.railDirection < 0 && rail.scrollLeft <= 2) ||
          (+b.dataset.railDirection > 0 && rail.scrollLeft >= max - 2);
      });
    }
    controls.forEach(function (button) {
      button.addEventListener('click', function () {
        var firstCard = rail.firstElementChild;
        var distance = firstCard ? firstCard.getBoundingClientRect().width + 12 : rail.clientWidth * .65;
        var dx = +button.dataset.railDirection * distance * 2;
        rail.scrollBy({ left: dx, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
      });
    });
    var scheduleRailControls = throttledRailMeasurement(updateRailControls);
    rail.addEventListener('scroll', scheduleRailControls, { passive: true });
    window.addEventListener('resize', scheduleRailControls);
    deferRailMeasurement(scheduleRailControls);
  });

  var showAll = document.getElementById('showAll');
  if (showAll) showAll.addEventListener('click', function () {
    if (qi) qi.value = '';
    govFilter = 'all';
    if (stateFilter) stateFilter.value = '';
    if (categoryFilter && active === 'all') categoryFilter.value = 'all';
    syncGovTabs();
    if (grid && grid.classList.contains('esy-scheme-grid')) { if (active !== 'all') { window.location.href = (html.lang === 'en' ? '/en/yojnayein' : '/hi/yojnayein'); return; } filter(); return; }
    var all = document.querySelector('.cat[data-cat="all"]');
    if (all) all.click(); else { active = 'all'; filter(); }
  });

  /* Ads: count a view when at least half of the ad is on screen (once per page view) */
  var ads = document.querySelectorAll('.ad[data-ad]');
  function adView(el) {
    if (el.dataset.seen) return;
    el.dataset.seen = '1';
    beacon('/t/ad', { a: +el.dataset.ad, e: 'view', s: el.dataset.slot, p: location.pathname });
  }
  if (ads.length) {
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) { if (en.isIntersecting && !en.target.hidden) { adView(en.target); io.unobserve(en.target); } });
      }, { threshold: 0.5 });
      ads.forEach(function (el) { io.observe(el); });
    } else ads.forEach(adView);
  }
  document.querySelectorAll('[data-ad-close]').forEach(function (b) {
    b.addEventListener('click', function () { b.closest('.ad').remove(); });
  });

  /* Click tracking: every link click is sent to /t/click (first-party, stored in the CMS database) */
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a) return;
    var ad = a.closest('.ad[data-ad]');
    if (ad && ad.dataset.type === 'html') beacon('/t/ad', { a: +ad.dataset.ad, e: 'click', s: ad.dataset.slot, p: location.pathname });
    var href = a.href;
    if (!href || href.indexOf('javascript:') === 0 || a.pathname.indexOf('/go/') === 0 || a.pathname.indexOf('/ad/') === 0) return; // logged server-side
    beacon('/t/click', { u: href, t: (a.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 200), p: location.pathname });
  }, true);

  if (grid) filter();

  /* Share box: copy link + native share sheet (mobile) */
  var copyBtn = document.getElementById('shareCopyBtn');
  if (copyBtn) copyBtn.addEventListener('click', function () {
    var url = copyBtn.dataset.url, done = function () {
      var old = copyBtn.title;
      copyBtn.classList.add('copied'); copyBtn.title = copyBtn.dataset.copiedLabel || 'Copied';
      setTimeout(function () { copyBtn.classList.remove('copied'); copyBtn.title = old; }, 2000);
    };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(url).then(done, function () { window.prompt('', url); });
    else { var t = document.createElement('textarea'); t.value = url; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); done(); } catch (e) { window.prompt('', url); } t.remove(); }
  });
  document.querySelectorAll('[data-share-native]').forEach(function (b) {
    if (!navigator.share) return;
    b.hidden = false;
    b.addEventListener('click', function () { navigator.share({ title: b.dataset.title, url: b.dataset.url }).catch(function () {}); });
  });
})();
