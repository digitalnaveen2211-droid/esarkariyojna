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

  /* Native visual CMS editor: edits the existing HTML DOM directly, without
     Quill import/export. That preserves tables, attributes, links and lists. */
  window.esyEditors = function () {
    document.querySelectorAll('[data-editor]').forEach(function (holder) {
      var src = holder.parentNode.querySelector('textarea.editor-src');
      if (!src || holder.dataset.ready === '1') return;
      holder.dataset.ready = '1';
      var wrap = document.createElement('div');
      wrap.className = 'editor-wrap esy-visual-wrap';
      holder.parentNode.insertBefore(wrap, holder);
      wrap.appendChild(holder);
      wrap.appendChild(src);

      holder.classList.add('esy-visual-content');
      holder.setAttribute('contenteditable', 'true');
      holder.setAttribute('role', 'textbox');
      holder.setAttribute('aria-multiline', 'true');
      holder.setAttribute('aria-label', 'Visual article editor');
      holder.innerHTML = src.value || '<p></p>';
      var sourceMode = false;
      var originalHTML = src.value || '';
      var changed = false;
      var lastSelection = null;

      var toolbar = document.createElement('div');
      toolbar.className = 'esy-visual-toolbar';
      var commands = [
        ['p', 'Paragraph'], ['h2', 'Heading 2'], ['h3', 'Heading 3'],
        ['bold', 'Bold'], ['italic', 'Italic'], ['underline', 'Underline'],
        ['ul', 'Bullet list'], ['ol', 'Numbered list'], ['link', 'Link'],
        ['table', 'Insert table'], ['row', 'Add table row'], ['delrow', 'Delete row'],
        ['undo', 'Undo'], ['redo', 'Redo']
      ];
      commands.forEach(function (item) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'esy-visual-tool';
        button.dataset.command = item[0];
        button.textContent = item[1];
        button.setAttribute('aria-label', item[1]);
        toolbar.appendChild(button);
      });
      wrap.insertBefore(toolbar, holder);
      var bottom = document.createElement('div');
      bottom.className = 'editor-mode';
      var toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'btn ghost sm';
      bottom.appendChild(toggle);
      wrap.appendChild(bottom);

      function selectionInsideEditor() {
        var sel = window.getSelection();
        return sel && sel.rangeCount && holder.contains(sel.anchorNode);
      }
      function selectedCell() {
        var sel = window.getSelection();
        var node = sel && sel.anchorNode;
        if (node && node.nodeType === 3) node = node.parentElement;
        return node && node.closest ? node.closest('td,th') : null;
      }
      function saveSelection() {
        var sel = window.getSelection();
        if (selectionInsideEditor()) lastSelection = sel.getRangeAt(0).cloneRange();
      }
      function restoreSelection() {
        if (!lastSelection || !holder.contains(lastSelection.commonAncestorContainer)) return;
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(lastSelection);
      }
      function exec(command, value) {
        holder.focus();
        restoreSelection();
        document.execCommand(command, false, value || null);
        saveSelection();
        changed = true;
      }
      function activateSourceMode(enable) {
        if (enable === sourceMode) return;
        if (enable) {
          src.value = holder.innerHTML;
        } else {
          holder.innerHTML = src.value;
        }
        sourceMode = enable;
        src.hidden = !sourceMode;
        holder.hidden = sourceMode;
        toolbar.hidden = sourceMode;
        toggle.textContent = sourceMode ? '✎ Visual editor' : '</> HTML code';
        changed = true;
      }
      // The original content is loaded directly as HTML; no lossy rich-text
      // conversion takes place. Visual view is always the default.
      src.hidden = true;
      toggle.textContent = '</> HTML code';
      toggle.addEventListener('click', function () { activateSourceMode(!sourceMode); });

      holder.addEventListener('input', function () { changed = true; });
      holder.addEventListener('keyup', saveSelection);
      holder.addEventListener('mouseup', saveSelection);
      toolbar.addEventListener('mousedown', function (e) {
        if (e.target.closest('button')) e.preventDefault();
      });
      toolbar.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-command]');
        if (!btn) return;
        var command = btn.dataset.command;
        if (command === 'p' || command === 'h2' || command === 'h3') {
          exec('formatBlock', command);
        } else if (command === 'bold' || command === 'italic' || command === 'underline') {
          exec(command);
        } else if (command === 'ul' || command === 'ol') {
          exec(command === 'ul' ? 'insertUnorderedList' : 'insertOrderedList');
        } else if (command === 'undo' || command === 'redo') {
          exec(command);
        } else if (command === 'link') {
          var url = window.prompt('Enter link URL (https:// or /hi/...):', 'https://');
          if (url === null) return;
          url = url.trim();
          if (!/^https:\/\/[^ ]+$/i.test(url) && !/^\/(hi|en)\/[a-z0-9/_-]+(?:\?[^ ]*)?$/i.test(url)) {
            window.alert('Only HTTPS or internal /hi/ and /en/ URLs are allowed.');
            return;
          }
          exec('createLink', url);
        } else if (command === 'table') {
          exec('insertHTML', '<table><thead><tr><th>Heading 1</th><th>Heading 2</th></tr></thead><tbody><tr><td>Value 1</td><td>Value 2</td></tr></tbody></table><p></p>');
        } else if (command === 'row' || command === 'delrow') {
          holder.focus();
          restoreSelection();
          var cell = selectedCell();
          if (!cell || !holder.contains(cell)) { window.alert('First place the cursor inside a table cell.'); return; }
          var row = cell.closest('tr');
          if (command === 'delrow') {
            if (row.parentNode.rows.length < 2) { window.alert('Cannot delete the final row in this section.'); return; }
            row.remove();
          } else {
            var clone = row.cloneNode(true);
            clone.querySelectorAll('td,th').forEach(function (c) { c.textContent = ''; });
            row.after(clone);
          }
          changed = true;
        }
      });

      // Pasting plain text prevents hidden scripts/styles from external
      // documents. It does not modify existing table-rich article HTML.
      holder.addEventListener('paste', function (e) {
        var text = e.clipboardData && e.clipboardData.getData('text/plain');
        if (typeof text !== 'string') return;
        e.preventDefault();
        document.execCommand('insertText', false, text);
      });

      src.form.addEventListener('submit', function () {
        // Always serialize precisely what the editor shows, not a Quill
        // approximation. A view-only visit does not rewrite the original.
        if (!sourceMode) src.value = changed ? holder.innerHTML : originalHTML;
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

  /* Keep the editor toolbar just under the sticky top bar */
  var tb = document.querySelector('.topbar');
  function setTop() { if (tb) document.documentElement.style.setProperty('--topbar-h', tb.offsetHeight + 'px'); }
  setTop(); window.addEventListener('resize', setTop);
})();
