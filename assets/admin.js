/* e-Sarkari Yojna admin helpers */
(function () {
  var PALETTE = ['#4338ca', '#f97316', '#16a34a', '#0ea5e9'];

  window.esyLineChart = function (id, labels, sets) {
    var el = document.getElementById(id);
    if (!el || !window.Chart) return;
    new Chart(el, {
      type: 'line',
      data: { labels: labels, datasets: sets.map(function (s, i) {
        return { label: s.label, data: s.data, borderColor: PALETTE[i], backgroundColor: PALETTE[i] + '22', fill: i === 0, tension: .3, pointRadius: 2 };
      }) },
      options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
  };
  window.esyBarChart = function (id, labels, data) {
    var el = document.getElementById(id);
    if (!el || !window.Chart) return;
    new Chart(el, {
      type: 'bar',
      data: { labels: labels, datasets: [{ data: data, backgroundColor: PALETTE[0], borderRadius: 4 }] },
      options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
  };

  /* Rich text editors (Quill) with an HTML source toggle */
  window.esyEditors = function () {
    if (!window.Quill) { // CDN blocked: fall back to plain HTML textareas
      document.querySelectorAll('textarea.editor-src').forEach(function (t) { t.hidden = false; t.rows = 14; });
      return;
    }
    document.querySelectorAll('[data-editor]').forEach(function (holder) {
      var src = holder.parentNode.querySelector('textarea.editor-src');
      var wrap = document.createElement('div');
      wrap.className = 'editor-wrap';
      holder.parentNode.insertBefore(wrap, holder);
      wrap.appendChild(holder);
      var q = new Quill(holder, {
        theme: 'snow',
        modules: { toolbar: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline', 'link'], [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote', { align: [] }], ['image', 'clean']] }
      });
      q.clipboard.dangerouslyPasteHTML(src.value || '');
      var htmlMode = false;
      wrap.appendChild(src);
      var bar = document.createElement('div');
      bar.className = 'editor-mode';
      bar.innerHTML = '<button type="button" class="btn ghost sm">&lt;/&gt; HTML code</button>';
      wrap.appendChild(bar);
      bar.firstChild.addEventListener('click', function () {
        htmlMode = !htmlMode;
        if (htmlMode) { src.value = q.root.innerHTML; }
        else { q.clipboard.dangerouslyPasteHTML(src.value); }
        src.hidden = !htmlMode;
        holder.style.display = htmlMode ? 'none' : '';
        wrap.querySelector('.ql-toolbar').style.display = htmlMode ? 'none' : '';
        this.textContent = htmlMode ? '✎ Visual editor' : '</> HTML code';
      });
      src.form.addEventListener('submit', function () {
        if (!htmlMode) src.value = q.root.innerText.trim() === '' && !q.root.querySelector('img') ? '' : q.root.innerHTML;
      });
    });
  };

  /* Hindi / English tabs in the editor */
  document.querySelectorAll('[data-tabs]').forEach(function (tabs) {
    var form = tabs.closest('form') || document;
    tabs.querySelectorAll('[data-tab]').forEach(function (b) {
      b.addEventListener('click', function () {
        tabs.querySelectorAll('[data-tab]').forEach(function (x) { x.classList.toggle('on', x === b); });
        form.querySelectorAll('[data-pane]').forEach(function (p) { p.hidden = p.dataset.pane !== b.dataset.tab; });
      });
    });
  });
  // Show the tab that contains an invalid required field
  document.querySelectorAll('form').forEach(function (f) {
    f.addEventListener('invalid', function (e) {
      var pane = e.target.closest('[data-pane]');
      if (pane && pane.hidden) { var b = f.querySelector('[data-tab="' + pane.dataset.pane + '"]'); if (b) b.click(); }
    }, true);
  });

  /* Image fields: live preview, upload preview, media picker */
  document.querySelectorAll('.img-field').forEach(function (f) {
    var txt = f.querySelector('input[type=text]'), img = f.querySelector('.img-prev'), file = f.querySelector('input[type=file]');
    txt.addEventListener('input', function () { img.src = txt.value; });
    file.addEventListener('change', function () {
      if (file.files[0]) { img.src = URL.createObjectURL(file.files[0]); txt.value = ''; txt.placeholder = file.files[0].name + ' (Save par upload hoga)'; }
    });
  });
  document.addEventListener('click', function (e) {
    var pick = e.target.closest('[data-pick]');
    if (!pick) return;
    var field = pick.closest('.img-field');
    fetch('/admin/?p=media_json', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (items) {
      var ov = document.createElement('div');
      ov.className = 'picker';
      ov.innerHTML = '<div class="picker-box"><div class="card-head"><h2>Image chunein</h2><button type="button" class="btn ghost sm" data-x>✕ Band</button></div>' +
        (items.length ? '' : '<p class="muted">Media library khali hai. Pehle Media me upload karein.</p>') + '<div class="picker-grid"></div></div>';
      var grid = ov.querySelector('.picker-grid');
      items.forEach(function (m) {
        var im = document.createElement('img');
        im.src = m.path; im.title = m.name; im.loading = 'lazy';
        im.addEventListener('click', function () {
          field.querySelector('input[type=text]').value = m.path;
          field.querySelector('.img-prev').src = m.path;
          ov.remove();
        });
        grid.appendChild(im);
      });
      ov.addEventListener('click', function (ev) { if (ev.target === ov || ev.target.closest('[data-x]')) ov.remove(); });
      document.body.appendChild(ov);
    });
  });

  /* Menu editor rows */
  document.querySelectorAll('.menu-editor').forEach(function (form) {
    var rows = form.querySelector('[data-menu-rows]');
    function reindex() {
      rows.querySelectorAll('.menu-row').forEach(function (r, i) {
        r.querySelectorAll('[name]').forEach(function (inp) { inp.name = inp.name.replace(/items\[\d+\]/, 'items[' + i + ']'); });
      });
    }
    form.querySelector('[data-add-row]').addEventListener('click', function () {
      var last = rows.lastElementChild, row = last.cloneNode(true);
      row.querySelectorAll('input').forEach(function (i) { if (i.type === 'checkbox') i.checked = false; else i.value = ''; });
      rows.appendChild(row); reindex(); row.querySelector('input').focus();
    });
    rows.addEventListener('click', function (e) {
      var row = e.target.closest('.menu-row');
      if (e.target.closest('[data-del]')) { if (rows.children.length > 1) row.remove(); else row.querySelectorAll('input').forEach(function (i) { i.value = ''; }); reindex(); }
      if (e.target.closest('[data-up]') && row.previousElementSibling) { rows.insertBefore(row, row.previousElementSibling); reindex(); }
    });
    form.addEventListener('submit', reindex);
  });
})();
