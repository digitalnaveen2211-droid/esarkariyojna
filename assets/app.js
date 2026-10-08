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
  function updateCount() {
    if (!grid || !countEl) return;
    var n = grid.querySelectorAll('.card:not([hidden])').length;
    countEl.textContent = (countEl.dataset[lang] || '{n}').replace('{n}', n);
    if (empty) empty.hidden = n !== 0;
  }
  function filter() {
    if (!grid) return;
    var term = (qi ? qi.value : '').trim().toLowerCase();
    grid.querySelectorAll('.card').forEach(function (c) {
      var okCat = active === 'all' || c.dataset.cat === active;
      var okQ = !term || term.split(/\s+/).every(function (w) { return c.dataset.search.indexOf(w) !== -1; });
      c.hidden = !(okCat && okQ);
    });
    var filtering = !!term || active !== 'all';
    grid.querySelectorAll('.ad').forEach(function (a) { a.hidden = filtering; });
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
  document.querySelectorAll('.cat').forEach(function (b) {
    b.addEventListener('click', function () {
      active = b.dataset.cat;
      document.querySelectorAll('.cat').forEach(function (x) { x.classList.toggle('on', x === b); });
      history.replaceState(null, '', '/' + lang + '/' + (active === 'all' ? '' : 'category/' + active));
      filter();
    });
  });
  var showAll = document.getElementById('showAll');
  if (showAll) showAll.addEventListener('click', function () {
    if (qi) qi.value = '';
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
})();
