(function () {
  var html = document.documentElement;

  /* Language switch */
  function setLang(l) {
    html.dataset.lang = l; html.lang = l;
    try { localStorage.setItem('esy_lang', l); } catch (e) {}
    var q = document.getElementById('q');
    if (q) q.placeholder = q.dataset['ph' + (l === 'hi' ? 'Hi' : 'En')] || '';
    updateCount();
  }
  document.querySelectorAll('[data-lang-btn]').forEach(function (b) {
    b.addEventListener('click', function () { setLang(b.dataset.langBtn); });
  });

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
    countEl.textContent = (countEl.dataset[html.dataset.lang] || '{n}').replace('{n}', n);
    if (empty) empty.hidden = n !== 0;
  }
  function filter() {
    if (!grid) return;
    var term = (qi ? qi.value : '').trim().toLowerCase();
    if (term) setLang(/[ऀ-ॿ]/.test(term) ? 'hi' : (/[a-z]/.test(term) ? 'en' : html.dataset.lang));
    grid.querySelectorAll('.card').forEach(function (c) {
      var okCat = active === 'all' || c.dataset.cat === active;
      var okQ = !term || term.split(/\s+/).every(function (w) { return c.dataset.search.indexOf(w) !== -1; });
      c.hidden = !(okCat && okQ);
    });
    var feat = document.querySelector('.featured');
    if (feat) feat.hidden = !!term || active !== 'all';
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
      history.replaceState(null, '', active === 'all' ? '/' : '/category/' + active);
      filter();
    });
  });
  var showAll = document.getElementById('showAll');
  if (showAll) showAll.addEventListener('click', function () {
    if (qi) qi.value = '';
    var all = document.querySelector('.cat[data-cat="all"]');
    if (all) all.click(); else { active = 'all'; filter(); }
  });

  /* Click tracking: every link click is sent to /t/click (first-party, stored in the CMS database) */
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a) return;
    var href = a.href;
    if (!href || href.indexOf('javascript:') === 0 || a.pathname.indexOf('/go/') === 0) return; // /go/ links are logged server-side
    var data = JSON.stringify({ u: href, t: (a.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 200), p: location.pathname });
    try {
      if (navigator.sendBeacon) navigator.sendBeacon('/t/click', new Blob([data], { type: 'application/json' }));
      else fetch('/t/click', { method: 'POST', body: data, keepalive: true, headers: { 'Content-Type': 'application/json' } });
    } catch (err) {}
  }, true);

  setLang(html.dataset.lang || 'hi');
  if (grid) filter();
})();
