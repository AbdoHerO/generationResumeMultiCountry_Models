/* ResumeAtlas — CV editor.
 *
 * One state object (doc) holds everything the server stores: title, country, language, template,
 * content and design. Inputs mutate it directly; every change goes through change(), which records
 * undo history, refreshes the live preview (debounced) and schedules an autosave (debounced, with
 * optimistic concurrency on `version`). Structural edits re-render the content pane.
 */
(function () {
  'use strict';

  const stateEl = document.getElementById('editor-state');
  const root = document.getElementById('editor');
  if (!stateEl || !root) return;

  const S = JSON.parse(stateEl.textContent);
  const API = root.dataset.api;
  const { api, toast, confirmDialog, promptDialog, dialog, icon, escapeHtml } = window.App;

  /* ───────────────────────── state ───────────────────────── */
  const doc = {
    title: S.cv.title,
    country: S.cv.country,
    language: S.cv.language,
    templateId: S.cv.templateId,
    content: S.cv.content,
    design: S.cv.design,
  };
  let version = S.cv.version;
  let hasPhoto = S.cv.hasPhoto;
  let photoUrl = hasPhoto ? `${root.dataset.download.replace(/\/download$/, '')}/photo?v=${Date.now()}` : null;
  const openSections = new Set(['personal']);
  const openItems = new Set();
  let showMorePersonal = false;

  const BACKUP_KEY = `resumeatlas.backup.${S.cv.id}`;
  const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const SECTION_ICONS = {
    personal: 'user', summary: 'message-square-quote', experience: 'briefcase', education: 'graduation-cap', skills: 'sparkles',
    languages: 'languages', certifications: 'badge-check', projects: 'layers', internships: 'building-2', volunteer: 'hand-heart',
    awards: 'award', publications: 'newspaper', interests: 'lightbulb', hobbies: 'heart', references: 'users',
    additional: 'info', custom: 'list-plus',
  };
  const SECTION_NAMES = {
    summary: 'Professional summary', experience: 'Work experience', education: 'Education', skills: 'Skills', languages: 'Languages',
    certifications: 'Certifications', projects: 'Projects', internships: 'Internships', volunteer: 'Volunteer experience',
    awards: 'Awards & achievements', publications: 'Publications', interests: 'Professional interests', hobbies: 'Hobbies',
    references: 'References', additional: 'Additional information', custom: 'Custom section',
  };
  const KIND = {
    summary: 'text', additional: 'text', experience: 'job', internships: 'job', volunteer: 'job', education: 'education',
    certifications: 'certification', projects: 'project', awards: 'award', publications: 'publication', references: 'reference',
    skills: 'skills', languages: 'languages', interests: 'tags', hobbies: 'tags', custom: 'custom',
  };
  const EMPLOYMENT = { full_time: 'Full-time', part_time: 'Part-time', contract: 'Contract', freelance: 'Freelance', internship: 'Internship', apprenticeship: 'Apprenticeship', temporary: 'Temporary' };
  const SKILL_CATS = { technical: 'Technical', soft: 'Interpersonal', tools: 'Tools' };
  const SKILL_LEVELS = { '': 'No level', beginner: 'Beginner', intermediate: 'Intermediate', advanced: 'Advanced', expert: 'Expert' };
  const LANG_LEVELS = ['native', 'fluent', 'professional', 'conversational', 'basic'];
  const CEFR = ['C2', 'C1', 'B2', 'B1', 'A2', 'A1'];

  const template = () => S.templates.find((t) => t.id === doc.templateId) || S.templates[0];
  const country = () => S.countries[doc.country];
  const labels = () => S.labels[doc.language] || S.labels.en;
  const dirAttr = () => (labels().dir === 'rtl' ? 'rtl' : 'auto');
  const uid = (p) => `${p}_${Math.random().toString(16).slice(2, 12)}`;
  const snapshot = () => JSON.stringify(doc);

  /* ───────────────────────── DOM helper ───────────────────────── */
  function h(tag, attrs, ...children) {
    const el = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs || {})) {
      if (v === null || v === undefined || v === false) continue;
      if (k === 'class') el.className = v;
      else if (k === 'html') el.innerHTML = v;
      else if (k === 'text') el.textContent = v;
      else if (k.startsWith('on')) el.addEventListener(k.slice(2).toLowerCase(), v);
      else if (k === 'value') el.value = v;
      else if (k === 'checked') el.checked = !!v;
      else el.setAttribute(k, v === true ? '' : v);
    }
    for (const c of children.flat()) {
      if (c === null || c === undefined || c === false) continue;
      el.append(c instanceof Node ? c : document.createTextNode(String(c)));
    }
    return el;
  }
  const ico = (name, size = 16) => { const s = h('span', { html: icon(name, size) }); return s.firstElementChild; };
  let fieldSeq = 0;
  const fid = () => `f${++fieldSeq}`;

  /* ───────────────────────── history (undo / redo) ───────────────────────── */
  const undoStack = [];
  const redoStack = [];
  let lastKey = null;
  let lastTime = 0;
  const undoBtn = document.getElementById('ed-undo');
  const redoBtn = document.getElementById('ed-redo');

  function record(key) {
    const now = Date.now();
    if (key === null || key !== lastKey || now - lastTime > 1200) {
      undoStack.push(snapshot());
      if (undoStack.length > 120) undoStack.shift();
      redoStack.length = 0;
    }
    lastKey = key;
    lastTime = now;
    updateHistoryButtons();
  }
  function updateHistoryButtons() {
    undoBtn.disabled = undoStack.length === 0;
    redoBtn.disabled = redoStack.length === 0;
  }
  function applySnapshot(s) {
    Object.assign(doc, JSON.parse(s));
    lastKey = null;
    renderAll();
    afterChange();
  }
  function undo() {
    if (!undoStack.length) return;
    redoStack.push(snapshot());
    applySnapshot(undoStack.pop());
    updateHistoryButtons();
    announce('Change undone');
  }
  function redo() {
    if (!redoStack.length) return;
    undoStack.push(snapshot());
    applySnapshot(redoStack.pop());
    updateHistoryButtons();
    announce('Change redone');
  }
  undoBtn.addEventListener('click', undo);
  redoBtn.addEventListener('click', redo);

  /**
   * Every edit goes through here. `key` groups consecutive keystrokes in one field into a single
   * undo step; structural edits pass null.
   */
  function change(key, mutate, { rerender = false } = {}) {
    record(key);
    mutate();
    if (rerender) renderContent();
    afterChange();
  }
  function afterChange() {
    changeSeq++;
    setStatus('dirty');
    schedulePreview();
    scheduleSave();
    scheduleExactPages();
    updateProgress();
  }

  /* ───────────────────────── save status ───────────────────────── */
  const statusEl = document.getElementById('ed-status');
  let changeSeq = 0;
  let savedSeq = 0;
  function setStatus(state, text) {
    statusEl.dataset.state = state;
    statusEl.querySelector('.txt').textContent = text || {
      saved: 'All changes saved', dirty: 'Unsaved changes', saving: 'Saving…', error: 'Not saved',
    }[state];
  }
  function announce(msg) {
    statusEl.querySelector('.txt').textContent = msg;
    setTimeout(() => setStatus(statusEl.dataset.state === 'dirty' ? 'dirty' : statusEl.dataset.state || 'saved'), 1500);
  }

  /* ───────────────────────── autosave ───────────────────────── */
  let saveTimer = null;
  let saving = null;
  let retryDelay = 0;
  let blocked = false; // a conflict or expired session needs the user's decision first

  function scheduleSave(delay = 1200) {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(save, delay);
  }

  function payload() {
    return { version, title: doc.title, country: doc.country, language: doc.language, templateId: doc.templateId, content: doc.content, design: doc.design };
  }

  function backup() {
    try { localStorage.setItem(BACKUP_KEY, JSON.stringify({ version, at: Date.now(), doc })); } catch (_) { /* storage unavailable */ }
  }
  function clearBackup() {
    try { localStorage.removeItem(BACKUP_KEY); } catch (_) { /* ignore */ }
  }

  async function save({ manual = false } = {}) {
    clearTimeout(saveTimer);
    if (blocked) return false;
    if (saving) { await saving.catch(() => {}); if (changeSeq === savedSeq) return true; }
    if (changeSeq === savedSeq && !manual) return true;
    const seq = changeSeq;
    setStatus('saving');
    saving = api(`${API}/save`, { body: payload() });
    try {
      const res = await saving;
      version = res.version;
      savedSeq = seq;
      retryDelay = 0;
      if (res.title && res.title !== doc.title) { doc.title = res.title; renderTitle(); }
      if (changeSeq === savedSeq) { setStatus('saved'); clearBackup(); } else { setStatus('dirty'); scheduleSave(); }
      if (manual) toast('All changes saved.', 'success', 2500);
      return true;
    } catch (err) {
      backup();
      handleSaveError(err, manual);
      return false;
    } finally {
      saving = null;
    }
  }

  async function handleSaveError(err, manual) {
    if (err.status === 409) {
      blocked = true;
      setStatus('error', 'Changed elsewhere');
      const choice = await dialog({
        title: 'This CV was changed somewhere else',
        message: 'It was saved from another tab or device after you opened it. Your edits here are kept as a local backup.',
        confirmText: 'Keep my version', cancelText: 'Load the other version', iconName: 'triangle-alert',
      });
      if (choice === null) { unloadGuard = false; location.reload(); return; }
      try {
        const latest = await api(API, { method: 'GET' });
        version = latest.version;
        blocked = false;
        save({ manual: true });
      } catch (e) { blocked = false; setStatus('error'); toast(e.message, 'error', 7000); }
      return;
    }
    if (err.status === 401) {
      blocked = true;
      setStatus('error', 'Signed out');
      const ok = await confirmDialog({
        title: 'Your session has ended',
        message: 'Your changes are kept in this browser. Sign in again in a new tab, then come back and choose “Retry”.',
        confirmText: 'Retry', cancelText: 'Open sign-in', iconName: 'lock',
      });
      if (!ok) window.open(document.querySelector('meta[name="app-base"]').content + '/login', '_blank', 'noopener');
      await refreshSession();
      blocked = false;
      scheduleSave(ok ? 0 : 4000);
      return;
    }
    if (err.status === 419) {
      if (await refreshSession()) { scheduleSave(0); return; }
    }
    if (err.status === 0 || err.status >= 500) {
      retryDelay = Math.min(retryDelay ? retryDelay * 2 : 4000, 60000);
      setStatus('error', err.status === 0 ? 'Offline — retrying' : 'Save failed — retrying');
      scheduleSave(retryDelay);
      if (manual) toast(err.message, 'error', 6000);
      return;
    }
    setStatus('error');
    toast(err.message, 'error', 8000);
  }

  async function refreshSession() {
    try {
      const s = await api(`${document.querySelector('meta[name="app-base"]').content}/api/session`, { method: 'GET' });
      if (s.csrf) document.querySelector('meta[name="csrf-token"]').content = s.csrf;
      return true;
    } catch (_) { return false; }
  }

  let unloadGuard = true;
  window.addEventListener('beforeunload', (e) => {
    if (unloadGuard && (changeSeq !== savedSeq || saving)) { backup(); e.preventDefault(); e.returnValue = ''; }
  });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden' && changeSeq !== savedSeq && !blocked) {
      backup();
      save();
    }
  });

  /* ───────────────────────── live preview ───────────────────────── */
  const stage = document.getElementById('ed-stage');
  const paper = document.getElementById('ed-paper');
  const guides = document.getElementById('ed-guides');
  const loading = document.getElementById('ed-preview-loading');
  const pageCountEl = document.getElementById('page-count');
  const PAGE_H = 1122.52; // 297 mm at 96 dpi
  let frame = document.getElementById('preview-frame');
  let previewTimer = null;
  let previewCtl = null;
  let contentHeight = PAGE_H;
  let zoom = 'fit';
  let exactPages = null;

  function schedulePreview(delay = 300) {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(refreshPreview, delay);
  }

  async function refreshPreview() {
    previewCtl?.abort();
    previewCtl = new AbortController();
    const slow = setTimeout(() => { loading.hidden = false; }, 350);
    try {
      const res = await api(`${API}/preview`, {
        body: { country: doc.country, language: doc.language, templateId: doc.templateId, content: doc.content, design: doc.design },
        signal: previewCtl.signal,
      });
      swapFrame(res.html);
    } catch (err) {
      if (err.name !== 'AbortError' && err.status !== 422) toast(err.message, 'error', 5000);
    } finally {
      clearTimeout(slow);
      loading.hidden = true;
    }
  }

  // Double buffering: render into a hidden frame, swap when loaded (no flicker, scroll kept).
  function swapFrame(html) {
    const next = document.createElement('iframe');
    next.title = 'Preview of your CV';
    next.setAttribute('sandbox', 'allow-same-origin');
    next.setAttribute('tabindex', '-1');
    next.style.position = 'absolute';
    next.style.visibility = 'hidden';
    next.style.left = '0';
    next.style.top = '0';
    next.addEventListener('load', () => {
      const d = next.contentDocument;
      const measure = () => {
        const page = d?.getElementById('cv-page');
        contentHeight = Math.max(PAGE_H, page ? page.scrollHeight : d.documentElement.scrollHeight);
        next.style.height = contentHeight + 'px';
        next.style.position = '';
        next.style.visibility = '';
        frame.replaceWith(next);
        next.id = 'preview-frame';
        frame = next;
        layoutPreview();
      };
      // Fonts change line breaks: measure once they are ready.
      (d?.fonts?.ready || Promise.resolve()).then(measure, measure);
    }, { once: true });
    paper.appendChild(next);
    next.srcdoc = html;
  }

  function currentZoom() {
    if (zoom !== 'fit') return zoom;
    const avail = stage.clientWidth - 48;
    return Math.max(0.3, Math.min(1.25, avail / 794));
  }

  function layoutPreview() {
    const z = currentZoom();
    frame.style.height = contentHeight + 'px';
    frame.style.transform = `scale(${z})`;
    paper.style.width = 794 * z + 'px';
    paper.style.height = contentHeight * z + 'px';
    document.getElementById('zoom-label').textContent = Math.round(z * 100) + '%';
    const pages = Math.max(1, Math.ceil((contentHeight - 4) / PAGE_H));
    guides.innerHTML = '';
    if (document.getElementById('page-guides').checked) {
      for (let p = 1; p < pages; p++) {
        guides.append(h('div', { class: 'brk', style: `top:${PAGE_H * p * z}px` }, h('span', {}, `Page ${p + 1}`)));
      }
    }
    updatePageCount(pages);
  }

  function updatePageCount(estimate) {
    const n = exactPages ?? estimate;
    pageCountEl.textContent = `${n} page${n === 1 ? '' : 's'}`;
    const target = doc.design.pageTarget;
    const over = (target === 'one' && n > 1) || (target === 'two' && n > 2);
    pageCountEl.className = over ? 'badge badge-warning' : 'badge';
    pageCountEl.title = over
      ? `Longer than your ${target === 'one' ? 'one-page' : 'two-page'} target. Shorten content or reduce spacing in Design.`
      : exactPages !== null ? 'Page count of the PDF' : 'Estimated from the preview';
  }

  let pagesTimer = null;
  let pagesCtl = null;
  function scheduleExactPages() {
    exactPages = null;
    clearTimeout(pagesTimer);
    pagesTimer = setTimeout(async () => {
      pagesCtl?.abort();
      pagesCtl = new AbortController();
      try {
        const r = await api(`${API}/pages`, { body: { country: doc.country, language: doc.language, templateId: doc.templateId, content: doc.content, design: doc.design }, signal: pagesCtl.signal });
        exactPages = r.pages;
        updatePageCount(r.pages);
      } catch (_) { /* the estimate stays */ }
    }, 4000);
  }

  document.getElementById('zoom-in').addEventListener('click', () => { zoom = Math.min(2, Math.round((currentZoom() + 0.1) * 10) / 10); layoutPreview(); });
  document.getElementById('zoom-out').addEventListener('click', () => { zoom = Math.max(0.3, Math.round((currentZoom() - 0.1) * 10) / 10); layoutPreview(); });
  document.getElementById('zoom-fit').addEventListener('click', () => { zoom = zoom === 'fit' ? 1 : 'fit'; layoutPreview(); });
  document.getElementById('page-guides').addEventListener('change', layoutPreview);
  if ('ResizeObserver' in window) new ResizeObserver(() => { if (zoom === 'fit') layoutPreview(); }).observe(stage);

  /* ───────────────────────── form building blocks ───────────────────────── */
  function textField(label, value, onInput, { key, multiline = false, type = 'text', placeholder = '', max = 200, hint = '', rows = 4, span2 = false, optional = false, inputmode, autocomplete } = {}) {
    const id = fid();
    const attrs = { id, class: multiline ? 'textarea' : 'input', placeholder, maxlength: max, dir: type === 'email' || type === 'url' ? 'ltr' : dirAttr(), inputmode, autocomplete };
    if (!multiline) attrs.type = type;
    else attrs.rows = rows;
    const input = h(multiline ? 'textarea' : 'input', attrs);
    input.value = value ?? '';
    const count = multiline && max > 300 ? h('div', { class: 'char-count', 'aria-hidden': 'true' }) : null;
    const updateCount = () => { if (count) count.textContent = `${input.value.length} / ${max}`; };
    updateCount();
    input.addEventListener('input', () => { change(key || id, () => onInput(input.value)); updateCount(); });
    if (type === 'email' || type === 'url') {
      input.addEventListener('blur', () => {
        const v = input.value.trim();
        const bad = v && (type === 'email' ? !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) : !/^(https?:\/\/)?[^\s.]+\.[^\s]{2,}$/i.test(v));
        input.setAttribute('aria-invalid', bad ? 'true' : 'false');
        err.hidden = !bad;
      });
    }
    const err = h('span', { class: 'error-text', hidden: true }, type === 'email' ? 'This does not look like an e-mail address.' : 'This does not look like a web address.');
    return h('div', { class: 'field' + (span2 ? ' span-2' : '') },
      h('label', { class: 'label', for: id }, label, optional ? h('span', { class: 'opt' }, ' (optional)') : null),
      input, count, hint ? h('span', { class: 'hint' }, hint) : null, err);
  }

  function selectField(label, value, options, onChange, { key, span2 = false } = {}) {
    const id = fid();
    const sel = h('select', { id, class: 'select' }, Object.entries(options).map(([v, l]) => h('option', { value: v }, l)));
    sel.value = value ?? '';
    sel.addEventListener('change', () => change(key || id, () => onChange(sel.value)));
    return h('div', { class: 'field' + (span2 ? ' span-2' : '') }, h('label', { class: 'label', for: id }, label), sel);
  }

  function yearOptions() {
    const now = new Date().getFullYear();
    const o = { '': 'Year' };
    for (let y = now + 6; y >= 1950; y--) o[y] = String(y);
    return o;
  }
  const YEARS = yearOptions();

  /** Month + year selects; value is "YYYY-MM", "YYYY" or "". */
  function dateField(label, value, onChange, { key, disabled = false } = {}) {
    const id = fid();
    const [y, m] = (value || '').split('-');
    const month = h('select', { id, class: 'select', 'aria-label': `${label} — month`, disabled },
      h('option', { value: '' }, 'Month'), MONTHS.map((n, i) => h('option', { value: String(i + 1).padStart(2, '0') }, n)));
    const year = h('select', { class: 'select', 'aria-label': `${label} — year`, disabled },
      Object.entries(YEARS).sort((a, b) => (a[0] === '' ? -1 : b[0] === '' ? 1 : b[0] - a[0])).map(([v, l]) => h('option', { value: v }, l)));
    month.value = m || '';
    year.value = y || '';
    const update = () => change(key || id, () => onChange(year.value ? (month.value ? `${year.value}-${month.value}` : year.value) : ''));
    month.addEventListener('change', update);
    year.addEventListener('change', update);
    return h('div', { class: 'field' }, h('label', { class: 'label', for: id }, label), h('div', { class: 'date-pair' }, month, year));
  }

  function switchField(label, checked, onChange) {
    const input = h('input', { type: 'checkbox', checked });
    input.addEventListener('change', () => change(null, () => onChange(input.checked)));
    return h('label', { class: 'switch' }, input, label);
  }

  /** A reorderable list of one-line bullets. */
  function bulletsField(label, list, onChangeList, { key, placeholder = 'Describe an achievement, ideally with a number' } = {}) {
    const wrap = h('div', { class: 'field' });
    const rows = h('div', { class: 'rows' });
    const draw = (focusIndex) => {
      rows.innerHTML = '';
      list.forEach((text, i) => {
        const input = h('input', { class: 'input grow', value: text, maxlength: 400, dir: dirAttr(), placeholder, 'aria-label': `${label} ${i + 1}` });
        input.addEventListener('input', () => change(`${key}.${i}`, () => { list[i] = input.value; }));
        input.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') {
            e.preventDefault();
            if (list.length >= 20) return;
            change(null, () => list.splice(i + 1, 0, ''));
            draw(i + 1);
          } else if (e.key === 'Backspace' && input.value === '' && list.length > 0) {
            e.preventDefault();
            change(null, () => list.splice(i, 1));
            draw(Math.max(0, i - 1));
          }
        });
        const row = h('div', { class: 'rowi' },
          h('span', { 'aria-hidden': 'true', class: 'muted' }, '•'), input,
          h('button', { type: 'button', class: 'btn btn-ghost btn-icon', 'aria-label': `Move up`, disabled: i === 0 ? true : null, onclick: () => { change(null, () => list.splice(i - 1, 0, list.splice(i, 1)[0])); draw(i - 1); } }, ico('arrow-up', 15)),
          h('button', { type: 'button', class: 'btn btn-ghost btn-icon', 'aria-label': `Remove bullet ${i + 1}`, onclick: () => { change(null, () => list.splice(i, 1)); draw(Math.min(i, list.length - 1)); } }, ico('x', 15)));
        rows.append(row);
      });
      if (focusIndex !== undefined) rows.querySelectorAll('input')[focusIndex]?.focus();
      add.disabled = list.length >= 20;
    };
    const add = h('button', { type: 'button', class: 'btn btn-secondary btn-sm', style: 'align-self:flex-start;margin-top:8px', onclick: () => { change(null, () => list.push('')); draw(list.length - 1); } }, ico('plus', 15), ' Add bullet');
    wrap.append(h('span', { class: 'label' }, label, h('span', { class: 'opt' }, ' (Enter adds a line)')), rows, add);
    draw();
    onChangeList?.();
    return wrap;
  }

  /* ───────────────────────── drag and drop (sections and items) ───────────────────────── */
  function makeSortable(container, selector, getList, { onDone } = {}) {
    let dragEl = null;
    container.addEventListener('dragstart', (e) => {
      const el = e.target.closest(selector);
      if (!el || el.parentElement !== container) return;
      dragEl = el;
      el.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', el.dataset.index);
    });
    container.addEventListener('dragover', (e) => {
      if (!dragEl) return;
      const over = e.target.closest(selector);
      if (!over || over.parentElement !== container || over === dragEl) return;
      e.preventDefault();
      container.querySelectorAll('.drop-before, .drop-after').forEach((x) => x.classList.remove('drop-before', 'drop-after'));
      const r = over.getBoundingClientRect();
      over.classList.add(e.clientY < r.top + r.height / 2 ? 'drop-before' : 'drop-after');
    });
    container.addEventListener('drop', (e) => {
      if (!dragEl) return;
      const over = e.target.closest(selector);
      e.preventDefault();
      if (over && over !== dragEl && over.parentElement === container) {
        const list = getList();
        const from = Number(dragEl.dataset.index);
        let to = Number(over.dataset.index) + (over.classList.contains('drop-after') ? 1 : 0);
        if (from < to) to--;
        if (from !== to) change(null, () => list.splice(to, 0, list.splice(from, 1)[0]), { rerender: !onDone });
        onDone?.();
      }
    });
    container.addEventListener('dragend', () => {
      container.querySelectorAll('.dragging, .drop-before, .drop-after').forEach((x) => x.classList.remove('dragging', 'drop-before', 'drop-after'));
      container.querySelectorAll('[draggable="true"]').forEach((x) => x.setAttribute('draggable', 'false'));
      dragEl = null;
    });
  }
  // Only the grip starts a drag, so text inside fields stays selectable.
  function grip(label, target) {
    const g = h('span', { class: 'sec-grip', title: 'Drag to reorder', 'aria-hidden': 'true' }, ico('grip-vertical', 16));
    g.addEventListener('mousedown', () => target().setAttribute('draggable', 'true'));
    g.addEventListener('touchstart', () => target().setAttribute('draggable', 'true'), { passive: true });
    g.dataset.label = label;
    return g;
  }

  /* ───────────────────────── content pane ───────────────────────── */
  const form = document.getElementById('editor-form');

  function sectionTitle(s) {
    return s.title || labels().sections[s.type] || SECTION_NAMES[s.type];
  }

  function renderContent({ focus } = {}) {
    const scroll = form.scrollTop;
    fieldSeq = 0;
    form.innerHTML = '';
    form.append(personalCard());
    const list = h('div', { id: 'sections' });
    doc.content.sections.forEach((s, i) => list.append(sectionCard(s, i)));
    makeSortable(list, '.sec-card', () => doc.content.sections);
    form.append(list, addSectionBlock());
    form.scrollTop = scroll;
    if (focus) form.querySelector(focus)?.focus();
  }

  function cardShell({ id, iconName, title, subtitle, tools, body, index, draggable }) {
    const open = openSections.has(id);
    const bodyId = `body-${id}`;
    const card = h('section', { class: 'sec-card' + (open ? ' open' : ''), 'data-id': id, 'data-index': index ?? '', 'aria-label': title });
    const titleEl = h('span', { class: 'sec-head-title' }, title, subtitle ? h('small', {}, subtitle) : null);
    card._title = titleEl;
    const toggle = h('button', { type: 'button', class: 'sec-toggle', 'aria-expanded': String(open), 'aria-controls': bodyId, 'data-focus': `toggle-${id}` },
      h('span', { class: 'sec-ico' }, ico(iconName, 16)), titleEl, h('span', { class: 'sec-chev' }, ico('chevron-down', 16)));
    const bodyEl = h('div', { class: 'sec-body', id: bodyId, hidden: !open });
    toggle.addEventListener('click', () => {
      const isOpen = !openSections.has(id);
      if (isOpen) openSections.add(id); else openSections.delete(id);
      card.classList.toggle('open', isOpen);
      toggle.setAttribute('aria-expanded', String(isOpen));
      bodyEl.hidden = !isOpen;
      if (isOpen && !bodyEl.childElementCount) bodyEl.append(...[].concat(body(card)));
    });
    if (open) bodyEl.append(...[].concat(body(card)));
    card.append(h('div', { class: 'sec-head' }, draggable ? grip(title, () => card) : h('span', { style: 'width:4px' }), toggle, tools ? h('div', { class: 'sec-tools' }, tools) : null), bodyEl);
    return card;
  }

  /* Personal details */
  function personalCard() {
    const p = doc.content.personal;
    const c = country();
    const set = (k) => (v) => { p[k] = v; };
    const optional = c.optionalFields || [];
    const OPTIONAL_LABELS = { dateOfBirth: 'Date of birth', nationality: 'Nationality', workAuthorization: 'Work authorization', drivingLicense: 'Driving licence', hometown: 'Hometown' };
    const OPTIONAL_HINTS = {
      dateOfBirth: doc.country === 'US' || doc.country === 'CA' ? 'Usually left out in this country.' : '',
      workAuthorization: 'For example “Authorized to work in the US” or “Work permit holder”.',
      hometown: 'Common on Chinese CVs (籍贯).',
    };
    return cardShell({
      id: 'personal', iconName: 'user', title: 'Personal details', subtitle: p.fullName || 'Name, contact, links',
      body: () => {
        const nodes = [];
        nodes.push(h('div', { class: 'form-grid' },
          textField('Full name', p.fullName, set('fullName'), { key: 'p.fullName', max: 120, span2: true, autocomplete: 'name' }),
          textField('Professional title', p.headline, set('headline'), { key: 'p.headline', max: 160, span2: true, placeholder: 'e.g. Senior Data Analyst' }),
          textField('E-mail', p.email, set('email'), { key: 'p.email', type: 'email', max: 190, span2: true, autocomplete: 'email' }),
          phoneField(p),
          textField('City', p.city, set('city'), { key: 'p.city', max: 80 }),
          textField('Country / region', p.country, set('country'), { key: 'p.country', max: 80, optional: true }),
          textField('Address', p.address, set('address'), { key: 'p.address', max: 200, span2: true, optional: true, hint: 'A city is usually enough. Leave the street address out unless it is expected.' }),
          textField('LinkedIn', p.linkedin, set('linkedin'), { key: 'p.linkedin', type: 'url', max: 300, optional: true, placeholder: 'linkedin.com/in/…' }),
          textField('Portfolio / GitHub', p.portfolio, set('portfolio'), { key: 'p.portfolio', type: 'url', max: 300, optional: true }),
          textField('Website', p.website, set('website'), { key: 'p.website', type: 'url', max: 300, optional: true, span2: true }),
        ));
        nodes.push(linksField(p));
        if (template().photo) nodes.push(photoBox());
        const shown = optional.filter((k) => OPTIONAL_LABELS[k]);
        const others = Object.keys(OPTIONAL_LABELS).filter((k) => !shown.includes(k) && p[k]);
        const optFields = [...shown, ...others];
        if (optFields.length) {
          const more = h('div', { class: 'form-grid', hidden: !showMorePersonal && !optFields.some((k) => p[k]) });
          optFields.forEach((k) => more.append(textField(OPTIONAL_LABELS[k], p[k], set(k), { key: `p.${k}`, max: k === 'workAuthorization' ? 120 : 80, optional: true, hint: OPTIONAL_HINTS[k] || '' })));
          const btn = h('button', { type: 'button', class: 'link-btn small more-toggle', 'aria-expanded': String(!more.hidden) }, more.hidden ? `Show details often included in ${c.name}` : `Details often included in ${c.name}`);
          btn.addEventListener('click', () => { more.hidden = !more.hidden; showMorePersonal = !more.hidden; btn.setAttribute('aria-expanded', String(!more.hidden)); });
          nodes.push(btn, more);
        }
        return nodes;
      },
    });
  }

  function phoneField(p) {
    const id = fid();
    const codes = [...new Set([p.phoneCode, ...Object.values(S.countries).map((c) => c.phoneCode)].filter(Boolean))];
    const sel = h('select', { class: 'select', 'aria-label': 'Country calling code', dir: 'ltr' }, h('option', { value: '' }, '—'), codes.map((c) => h('option', { value: c }, c)));
    sel.value = p.phoneCode || '';
    sel.addEventListener('change', () => change(null, () => { p.phoneCode = sel.value; }));
    const input = h('input', { id, class: 'input', type: 'tel', value: p.phone, maxlength: 40, dir: 'ltr', placeholder: country().phoneExample, inputmode: 'tel', autocomplete: 'tel-national' });
    input.addEventListener('input', () => change('p.phone', () => { p.phone = input.value; }));
    return h('div', { class: 'field span-2' }, h('label', { class: 'label', for: id }, 'Phone'), h('div', { class: 'input-group' }, sel, input));
  }

  function linksField(p) {
    const wrap = h('div', { class: 'field', style: 'margin-top:14px' });
    const rows = h('div', { class: 'rows' });
    const draw = () => {
      rows.innerHTML = '';
      p.links.forEach((l, i) => {
        const label = h('input', { class: 'input', style: 'max-width:38%', value: l.label, maxlength: 40, placeholder: 'Label', 'aria-label': `Link ${i + 1} label`, dir: dirAttr() });
        const url = h('input', { class: 'input grow', type: 'url', value: l.url, maxlength: 300, placeholder: 'https://…', 'aria-label': `Link ${i + 1} address`, dir: 'ltr' });
        label.addEventListener('input', () => change(`link.${i}.l`, () => { l.label = label.value; }));
        url.addEventListener('input', () => change(`link.${i}.u`, () => { l.url = url.value; }));
        rows.append(h('div', { class: 'rowi' }, label, url,
          h('button', { type: 'button', class: 'btn btn-ghost btn-icon', 'aria-label': `Remove link ${i + 1}`, onclick: () => { change(null, () => p.links.splice(i, 1)); draw(); } }, ico('x', 15))));
      });
      add.hidden = p.links.length >= 5;
    };
    const add = h('button', { type: 'button', class: 'btn btn-secondary btn-sm', style: 'align-self:flex-start;margin-top:8px', onclick: () => { change(null, () => p.links.push({ label: '', url: '' })); draw(); rows.querySelector('.rowi:last-child input')?.focus(); } }, ico('link', 15), ' Add a link');
    wrap.append(h('span', { class: 'label' }, 'Other links ', h('span', { class: 'opt' }, '(optional, up to 5)')), rows, add);
    draw();
    return wrap;
  }

  function photoBox() {
    const c = country();
    const policy = { common: 'A photo is common in this country.', optional: 'A photo is optional in this country.', discouraged: 'Photos are usually left out in this country to avoid bias; leave it off unless asked.' }[c.photo] || '';
    const file = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', class: 'sr-only', id: 'photo-input' });
    const img = photoUrl ? h('img', { src: photoUrl, alt: 'Your photo' }) : h('div', { class: 'ph' }, ico('image', 24));
    const remove = hasPhoto ? h('button', { type: 'button', class: 'btn btn-ghost btn-sm', onclick: removePhoto }, ico('trash-2', 15), ' Remove') : null;
    file.addEventListener('change', () => file.files[0] && uploadPhoto(file.files[0]));
    return h('div', {},
      h('div', { class: 'photo-box' }, img,
        h('div', { class: 'grow' },
          h('div', { class: 'row', style: 'gap:6px' },
            h('label', { class: 'btn btn-secondary btn-sm', for: 'photo-input', tabindex: '0', onkeydown: (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); file.click(); } } }, ico('upload', 15), hasPhoto ? ' Replace photo' : ' Upload photo'),
            remove, file),
          h('div', { class: 'hint', style: 'margin-top:6px' }, 'JPEG, PNG or WebP, up to 2 MB. Cropped to a square.'))),
      h('div', { style: 'margin-top:10px' }, switchField('Show the photo on this CV', !!doc.design.showPhoto, (v) => { doc.design.showPhoto = v; })),
      policy ? h('div', { class: 'tip' }, ico('info', 15), h('span', {}, policy)) : null);
  }

  async function uploadPhoto(f) {
    if (f.size > 2 * 1024 * 1024) { toast('This image is larger than 2 MB. Choose a smaller one.', 'error', 6000); return; }
    const fd = new FormData();
    fd.append('photo', f);
    const done = toast('Uploading photo…', 'info', 0);
    try {
      const r = await api(`${API}/photo`, { formData: fd });
      hasPhoto = true;
      photoUrl = r.photo;
      done();
      toast('Photo updated.', 'success', 2500);
      if (!doc.design.showPhoto) change(null, () => { doc.design.showPhoto = true; });
      renderContent();
      schedulePreview(0);
    } catch (err) { done(); toast(err.message, 'error', 7000); }
  }
  async function removePhoto() {
    if (!(await confirmDialog({ title: 'Remove the photo?', message: 'It is deleted from this CV.', confirmText: 'Remove', danger: true }))) return;
    try {
      await api(`${API}/photo/delete`, { body: {} });
      hasPhoto = false;
      photoUrl = null;
      renderContent();
      schedulePreview(0);
      toast('Photo removed.', 'success', 2500);
    } catch (err) { toast(err.message, 'error', 6000); }
  }

  /* Sections */
  function sectionCard(s, index) {
    const n = doc.content.sections.length;
    const count = Array.isArray(s.items) ? s.items.length : 0;
    const subtitle = s.hidden ? 'Hidden from the CV' : KIND[s.type] === 'text' ? (s.text ? `${s.text.length} characters` : 'Empty') : `${count} item${count === 1 ? '' : 's'}`;
    const move = (d) => () => {
      change(null, () => doc.content.sections.splice(index + d, 0, doc.content.sections.splice(index, 1)[0]), { rerender: false });
      renderContent({ focus: `[data-focus="${d < 0 ? 'up' : 'down'}-${s.id}"]:not(:disabled)` });
      if (!form.contains(document.activeElement) || document.activeElement === document.body) form.querySelector(`[data-focus="toggle-${s.id}"]`)?.focus();
    };
    const tools = [
      h('button', { type: 'button', class: 'btn btn-ghost btn-icon btn-sm', 'aria-label': `Move “${sectionTitle(s)}” up`, title: 'Move up', disabled: index === 0 ? true : null, 'data-focus': `up-${s.id}`, onclick: move(-1) }, ico('arrow-up', 15)),
      h('button', { type: 'button', class: 'btn btn-ghost btn-icon btn-sm', 'aria-label': `Move “${sectionTitle(s)}” down`, title: 'Move down', disabled: index === n - 1 ? true : null, 'data-focus': `down-${s.id}`, onclick: move(1) }, ico('arrow-down', 15)),
      h('button', { type: 'button', class: 'btn btn-ghost btn-icon btn-sm', 'aria-label': s.hidden ? `Show “${sectionTitle(s)}” on the CV` : `Hide “${sectionTitle(s)}” from the CV`, 'aria-pressed': String(!!s.hidden), title: s.hidden ? 'Show on CV' : 'Hide from CV', 'data-focus': `vis-${s.id}`,
        onclick: () => { change(null, () => { s.hidden = !s.hidden; }); renderContent({ focus: `[data-focus="vis-${s.id}"]` }); } }, ico(s.hidden ? 'eye-off' : 'eye', 15)),
      h('button', { type: 'button', class: 'btn btn-ghost btn-icon btn-sm', 'aria-label': `Delete “${sectionTitle(s)}”`, title: 'Delete section', onclick: () => deleteSection(index) }, ico('trash-2', 15)),
    ];
    const card = cardShell({ id: s.id, iconName: SECTION_ICONS[s.type] || 'list', title: sectionTitle(s), subtitle, tools, index, draggable: true, body: (c) => sectionBody(s, c) });
    if (s.hidden) card.classList.add('is-hidden');
    return card;
  }

  async function deleteSection(index) {
    const s = doc.content.sections[index];
    const hasData = (s.text && s.text.trim()) || (s.items && s.items.length);
    if (hasData && !(await confirmDialog({ title: `Delete “${sectionTitle(s)}”?`, message: 'Its content is removed from this CV. You can undo this with Ctrl+Z.', confirmText: 'Delete section', danger: true }))) return;
    change(null, () => doc.content.sections.splice(index, 1), { rerender: true });
    toast(`Section deleted. Press Ctrl+Z to undo.`, 'info', 4000);
  }

  function sectionBody(s, card) {
    const nodes = [];
    const id = fid();
    const titleInput = h('input', { id, class: 'input', maxlength: 80, value: s.title, placeholder: labels().sections[s.type] || SECTION_NAMES[s.type], dir: dirAttr() });
    titleInput.addEventListener('input', () => change(`t.${s.id}`, () => { s.title = titleInput.value; }));
    titleInput.addEventListener('change', () => { card._title.firstChild.textContent = sectionTitle(s); });
    nodes.push(h('div', { class: 'field' }, h('label', { class: 'label', for: id }, 'Section heading ', h('span', { class: 'opt' }, '(leave empty for the standard heading)')), titleInput));

    const kind = KIND[s.type];
    if (kind === 'text') {
      nodes.push(textField(s.type === 'summary' ? 'Summary' : 'Text', s.text, (v) => { s.text = v; }, {
        key: `x.${s.id}`, multiline: true, max: 3000, rows: s.type === 'summary' ? 5 : 4,
        hint: s.type === 'summary' ? 'Two to four sentences: who you are, your strongest results, what you are looking for.' : '',
      }));
      if (s.type === 'summary' && country().tips?.length) nodes.push(h('div', { class: 'tip' }, ico('lightbulb', 15), h('span', {}, country().tips[0])));
    } else if (kind === 'custom') {
      nodes.push(textField('Text', s.text, (v) => { s.text = v; }, { key: `x.${s.id}`, multiline: true, max: 3000, optional: true }));
      s.items = s.items || [];
      nodes.push(bulletsField('Bullet points', s.items, null, { key: `b.${s.id}`, placeholder: 'One point per line' }));
    } else if (kind === 'skills') {
      nodes.push(skillsEditor(s));
    } else if (kind === 'languages') {
      nodes.push(languagesEditor(s));
    } else if (kind === 'tags') {
      nodes.push(tagsEditor(s));
    } else {
      if (s.type === 'references') {
        nodes.push(h('div', { style: 'margin-top:14px' }, switchField('Write “References available upon request” instead of listing them', !!s.onRequest, (v) => { s.onRequest = v; })));
      }
      nodes.push(entriesEditor(s, kind));
    }
    return nodes;
  }

  /* Entries: experience, education, projects… */
  const ENTRY_DEFAULTS = {
    job: { title: '', organization: '', location: '', employmentType: '', startDate: '', endDate: '', current: false, description: '', bullets: [] },
    education: { degree: '', institution: '', location: '', field: '', grade: '', startDate: '', endDate: '', current: false, description: '', bullets: [] },
    certification: { name: '', issuer: '', date: '', url: '', description: '' },
    project: { name: '', role: '', url: '', startDate: '', endDate: '', current: false, description: '', bullets: [] },
    award: { title: '', issuer: '', date: '', description: '' },
    publication: { title: '', publisher: '', date: '', url: '', description: '' },
    reference: { name: '', position: '', organization: '', email: '', phone: '' },
  };
  const ADD_LABEL = { experience: 'Add a position', internships: 'Add an internship', volunteer: 'Add a role', education: 'Add a qualification', certifications: 'Add a certification', projects: 'Add a project', awards: 'Add an award', publications: 'Add a publication', references: 'Add a reference' };

  function entrySummary(kind, it) {
    const fmt = (d) => { if (!d) return ''; const [y, m] = d.split('-'); return m ? `${MONTHS[Number(m) - 1]} ${y}` : y; };
    const range = it.startDate || it.endDate || it.current ? [fmt(it.startDate), it.current ? 'Present' : fmt(it.endDate)].filter(Boolean).join(' – ') : fmt(it.date);
    const main = { job: it.title, education: it.degree, certification: it.name, project: it.name, award: it.title, publication: it.title, reference: it.name }[kind];
    const sub = { job: it.organization, education: it.institution, certification: it.issuer, project: it.role, award: it.issuer, publication: it.publisher, reference: it.organization }[kind];
    return { main: main || 'Untitled', sub: [sub, range].filter(Boolean).join(' · ') };
  }

  function entriesEditor(s, kind) {
    const wrap = h('div', {});
    const list = h('div', { class: 'items' });
    s.items.forEach((it, i) => list.append(entryCard(s, kind, it, i)));
    makeSortable(list, '.item', () => s.items);
    const add = h('button', { type: 'button', class: 'btn btn-secondary add-btn', disabled: s.items.length >= 50 ? true : null },
      ico('plus', 16), ' ', ADD_LABEL[s.type] || 'Add an entry');
    add.addEventListener('click', () => {
      const it = { id: uid('i'), ...structuredClone(ENTRY_DEFAULTS[kind]) };
      change(null, () => s.items.push(it));
      openItems.add(it.id);
      openSections.add(s.id);
      renderContent({ focus: `[data-item="${it.id}"] .item-body input` });
    });
    wrap.append(list, add);
    return wrap;
  }

  function entryCard(s, kind, it, i) {
    const open = openItems.has(it.id);
    const sum = entrySummary(kind, it);
    const bodyId = `item-${it.id}`;
    const card = h('div', { class: 'item', 'data-index': i, 'data-item': it.id });
    const mainEl = h('strong', {}, sum.main);
    const subEl = h('span', {}, sum.sub);
    const refresh = () => { const x = entrySummary(kind, it); mainEl.textContent = x.main; subEl.textContent = x.sub; };
    const toggle = h('button', { type: 'button', class: 'item-toggle', 'aria-expanded': String(open), 'aria-controls': bodyId }, mainEl, subEl);
    const body = h('div', { class: 'item-body', id: bodyId, hidden: !open });
    const fill = () => body.append(...entryFields(s, kind, it, refresh));
    toggle.addEventListener('click', () => {
      const o = !openItems.has(it.id);
      if (o) openItems.add(it.id); else openItems.delete(it.id);
      toggle.setAttribute('aria-expanded', String(o));
      body.hidden = !o;
      if (o && !body.childElementCount) fill();
    });
    if (open) fill();
    const n = s.items.length;
    const move = (d) => () => { change(null, () => s.items.splice(i + d, 0, s.items.splice(i, 1)[0])); renderContent({ focus: `[data-item="${it.id}"] [data-move="${d}"]:not(:disabled)` }); };
    card.append(h('div', { class: 'item-head' }, grip(sum.main, () => card), toggle,
      h('button', { type: 'button', class: 'btn btn-ghost btn-icon btn-sm', 'aria-label': `Move “${sum.main}” up`, 'data-move': '-1', disabled: i === 0 ? true : null, onclick: move(-1) }, ico('arrow-up', 15)),
      h('button', { type: 'button', class: 'btn btn-ghost btn-icon btn-sm', 'aria-label': `Move “${sum.main}” down`, 'data-move': '1', disabled: i === n - 1 ? true : null, onclick: move(1) }, ico('arrow-down', 15)),
      h('button', { type: 'button', class: 'btn btn-ghost btn-icon btn-sm', 'aria-label': `Duplicate “${sum.main}”`, title: 'Duplicate', disabled: n >= 50 ? true : null, onclick: () => {
        const copy = { ...structuredClone(it), id: uid('i') };
        change(null, () => s.items.splice(i + 1, 0, copy));
        openItems.add(copy.id);
        renderContent({ focus: `[data-item="${copy.id}"] .item-toggle` });
      } }, ico('copy', 15)),
      h('button', { type: 'button', class: 'btn btn-ghost btn-icon btn-sm', 'aria-label': `Delete “${sum.main}”`, title: 'Delete', onclick: async () => {
        const empty = !Object.entries(it).some(([k, v]) => k !== 'id' && (Array.isArray(v) ? v.some(Boolean) : typeof v === 'string' && v.trim()));
        if (!empty && !(await confirmDialog({ title: `Delete “${sum.main}”?`, message: 'You can undo this with Ctrl+Z.', confirmText: 'Delete', danger: true }))) return;
        change(null, () => s.items.splice(i, 1), { rerender: true });
      } }, ico('trash-2', 15))), body);
    return card;
  }

  function entryFields(s, kind, it, refresh) {
    const set = (k) => (v) => { it[k] = v; refresh(); };
    const key = (k) => `${it.id}.${k}`;
    const t = (label, k, o = {}) => textField(label, it[k], set(k), { key: key(k), ...o });
    const dates = () => {
      const end = dateField('End', it.current ? '' : it.endDate, set('endDate'), { key: key('end'), disabled: it.current });
      const cur = h('input', { type: 'checkbox', checked: it.current });
      cur.addEventListener('change', () => {
        change(null, () => { it.current = cur.checked; if (cur.checked) it.endDate = ''; refresh(); });
        end.querySelectorAll('select').forEach((x) => { x.disabled = cur.checked; if (cur.checked) x.value = ''; });
      });
      const label = kind === 'education' ? 'I am currently studying here' : kind === 'project' ? 'Ongoing' : 'I currently work here';
      return [h('div', { class: 'date-row', style: 'margin-top:12px' }, dateField('Start', it.startDate, set('startDate'), { key: key('start') }), end),
        h('label', { class: 'check', style: 'margin-top:10px' }, cur, label)];
    };
    const desc = (hint) => textField('Description', it.description, set('description'), { key: key('d'), multiline: true, max: kind === 'job' ? 2000 : kind === 'education' || kind === 'project' ? 1500 : 600, rows: 3, optional: true, hint });
    const bullets = () => { it.bullets = it.bullets || []; return bulletsField(kind === 'education' ? 'Highlights' : 'Achievements', it.bullets, null, { key: key('b') }); };
    switch (kind) {
      case 'job': {
        const org = s.type === 'volunteer' ? 'Organization' : 'Employer';
        return [h('div', { class: 'form-grid' },
          t(s.type === 'volunteer' ? 'Role' : s.type === 'internships' ? 'Internship title' : 'Job title', 'title', { max: 120, span2: true }),
          t(org, 'organization', { max: 140 }), t('Location', 'location', { max: 120, optional: true }),
          selectField('Contract type', it.employmentType, { '': 'Not specified', ...EMPLOYMENT }, set('employmentType'), { key: key('et'), span2: true })),
        ...dates(), desc('A sentence of context, if useful. Put results in the achievements below.'), bullets()];
      }
      case 'education':
        return [h('div', { class: 'form-grid' },
          t('Degree / diploma', 'degree', { max: 160, span2: true }), t('Field of study', 'field', { max: 160, span2: true, optional: true }),
          t('School / university', 'institution', { max: 160 }), t('Location', 'location', { max: 120, optional: true }),
          t('Grade / honours', 'grade', { max: 80, span2: true, optional: true, hint: 'e.g. “Mention Bien”, “GPA 3.8/4.0”, “First-class honours”.' })),
        ...dates(), desc(''), bullets()];
      case 'certification':
        return [h('div', { class: 'form-grid' }, t('Certification', 'name', { max: 160, span2: true }), t('Issued by', 'issuer', { max: 140 }),
          dateField('Date', it.date, set('date'), { key: key('date') }), t('Credential link', 'url', { type: 'url', max: 300, span2: true, optional: true })), desc('')];
      case 'project':
        return [h('div', { class: 'form-grid' }, t('Project name', 'name', { max: 160, span2: true }), t('Your role', 'role', { max: 120, optional: true }),
          t('Link', 'url', { type: 'url', max: 300, optional: true })), ...dates(), desc(''), bullets()];
      case 'award':
        return [h('div', { class: 'form-grid' }, t('Award', 'title', { max: 160, span2: true }), t('Awarded by', 'issuer', { max: 140, optional: true }),
          dateField('Date', it.date, set('date'), { key: key('date') })), desc('')];
      case 'publication':
        return [h('div', { class: 'form-grid' }, t('Title', 'title', { max: 300, span2: true }), t('Publisher / journal', 'publisher', { max: 160 }),
          dateField('Date', it.date, set('date'), { key: key('date') }), t('Link (DOI or URL)', 'url', { type: 'url', max: 300, span2: true, optional: true })), desc('')];
      case 'reference':
        return [h('div', { class: 'form-grid' }, t('Name', 'name', { max: 120, span2: true }), t('Position', 'position', { max: 120 }), t('Organization', 'organization', { max: 140 }),
          t('E-mail', 'email', { type: 'email', max: 190, optional: true }), t('Phone', 'phone', { max: 40, optional: true, inputmode: 'tel' })),
        h('div', { class: 'tip' }, ico('shield', 15), h('span', {}, 'Ask your references before sharing their contact details.'))];
      default:
        return [];
    }
  }

  /* Skills */
  function skillsEditor(s) {
    const wrap = h('div', {});
    const rows = h('div', { class: 'rows' });
    const draw = (focusIndex) => {
      rows.innerHTML = '';
      s.items.forEach((it, i) => {
        const name = h('input', { class: 'input grow', value: it.name, maxlength: 80, placeholder: 'Skill', 'aria-label': `Skill ${i + 1}`, dir: dirAttr() });
        name.addEventListener('input', () => change(`${it.id}.n`, () => { it.name = name.value; }));
        name.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); addSkill(i + 1, it.category); } });
        const cat = h('select', { class: 'select', 'aria-label': `Category of skill ${i + 1}` }, Object.entries(SKILL_CATS).map(([v, l]) => h('option', { value: v }, l)));
        cat.value = it.category || 'technical';
        cat.addEventListener('change', () => change(null, () => { it.category = cat.value; }));
        const lvl = h('select', { class: 'select', 'aria-label': `Level of skill ${i + 1}` }, Object.entries(SKILL_LEVELS).map(([v, l]) => h('option', { value: v }, l)));
        lvl.value = it.level || '';
        lvl.addEventListener('change', () => change(null, () => { it.level = lvl.value; }));
        const row = h('div', { class: 'rowi', 'data-index': i }, grip(it.name, () => row), name, cat, lvl,
          h('button', { type: 'button', class: 'btn btn-ghost btn-icon', 'aria-label': `Remove skill ${it.name || i + 1}`, onclick: () => { change(null, () => s.items.splice(i, 1)); draw(Math.min(i, s.items.length - 1)); } }, ico('x', 15)));
        rows.append(row);
      });
      if (focusIndex !== undefined && focusIndex >= 0) rows.querySelectorAll('input')[focusIndex]?.focus();
    };
    const addSkill = (at = s.items.length, category = 'technical') => {
      if (s.items.length >= 50) return;
      change(null, () => s.items.splice(at, 0, { id: uid('i'), name: '', category, level: '' }));
      draw(at);
    };
    makeSortable(rows, '.rowi', () => s.items, { onDone: () => draw() });
    wrap.append(rows,
      h('button', { type: 'button', class: 'btn btn-secondary add-btn', onclick: () => addSkill() }, ico('plus', 16), ' Add a skill'),
      h('p', { class: 'hint', style: 'margin:8px 0 0' }, 'Levels are optional. Templates group skills by category where it helps.'));
    draw();
    return wrap;
  }

  /* Languages */
  function languagesEditor(s) {
    const wrap = h('div', {});
    const rows = h('div', { class: 'rows' });
    const lv = labels().levels || {};
    const draw = (focusIndex) => {
      rows.innerHTML = '';
      s.items.forEach((it, i) => {
        const name = h('input', { class: 'input grow', value: it.name, maxlength: 60, placeholder: 'Language', 'aria-label': `Language ${i + 1}`, dir: dirAttr() });
        name.addEventListener('input', () => change(`${it.id}.n`, () => { it.name = name.value; }));
        const opts = { '': 'Level' };
        LANG_LEVELS.forEach((k) => { opts[k] = lv[k] && lv[k] !== k ? lv[k] : k; });
        CEFR.forEach((k) => { opts[k] = `${k} (CEFR)`; });
        if (it.level && !(it.level in opts)) opts[it.level] = it.level;
        opts.__other = 'Other…';
        const lvl = h('select', { class: 'select', 'aria-label': `Level of language ${i + 1}` }, Object.entries(opts).map(([v, l]) => h('option', { value: v }, l)));
        lvl.value = it.level || '';
        lvl.addEventListener('change', async () => {
          if (lvl.value === '__other') {
            const v = await promptDialog({ title: 'Language level', input: { label: 'Describe your level', value: '', maxlength: 40 }, confirmText: 'Use this level' });
            if (v) change(null, () => { it.level = v; });
            draw();
            return;
          }
          change(null, () => { it.level = lvl.value; });
        });
        const row = h('div', { class: 'rowi', 'data-index': i }, grip(it.name, () => row), name, lvl,
          h('button', { type: 'button', class: 'btn btn-ghost btn-icon', 'aria-label': `Remove language ${it.name || i + 1}`, onclick: () => { change(null, () => s.items.splice(i, 1)); draw(); } }, ico('x', 15)));
        rows.append(row);
      });
      if (focusIndex !== undefined) rows.querySelectorAll('input')[focusIndex]?.focus();
    };
    makeSortable(rows, '.rowi', () => s.items, { onDone: () => draw() });
    wrap.append(rows, h('button', { type: 'button', class: 'btn btn-secondary add-btn', onclick: () => {
      if (s.items.length >= 50) return;
      change(null, () => s.items.push({ id: uid('i'), name: '', level: '' }));
      draw(s.items.length - 1);
    } }, ico('plus', 16), ' Add a language'));
    draw();
    return wrap;
  }

  /* Interests / hobbies as tags */
  function tagsEditor(s) {
    const wrap = h('div', {});
    const tags = h('div', { class: 'tags', role: 'list' });
    const id = fid();
    const input = h('input', { id, class: 'input', maxlength: 80, placeholder: 'Type and press Enter', dir: dirAttr() });
    const draw = () => {
      tags.innerHTML = '';
      s.items.forEach((it, i) => tags.append(h('span', { class: 'tag', role: 'listitem' }, it.name,
        h('button', { type: 'button', 'aria-label': `Remove ${it.name}`, onclick: () => { change(null, () => s.items.splice(i, 1)); draw(); input.focus(); } }, ico('x', 13)))));
    };
    const commit = () => {
      const parts = input.value.split(/[,;，、]/).map((x) => x.trim()).filter(Boolean);
      if (!parts.length) return;
      change(null, () => parts.forEach((p) => s.items.length < 50 && s.items.push({ id: uid('i'), name: p.slice(0, 80) })));
      input.value = '';
      draw();
    };
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); commit(); }
      if (e.key === 'Backspace' && !input.value && s.items.length) { change(null, () => s.items.pop()); draw(); }
    });
    input.addEventListener('blur', commit);
    wrap.append(h('div', { class: 'field' }, h('label', { class: 'label', for: id }, 'Add items'), input, h('span', { class: 'hint' }, 'Separate several with commas.')), tags);
    draw();
    return wrap;
  }

  /* Add a section */
  function addSectionBlock() {
    const present = new Set(doc.content.sections.map((s) => s.type));
    const available = S.sectionTypes.filter((t) => t === 'custom' || !S.uniqueTypes.includes(t) || !present.has(t));
    const grid = h('div', { class: 'add-section-grid' }, available.map((type) => h('button', {
      type: 'button',
      disabled: doc.content.sections.length >= 30 ? true : null,
      onclick: () => {
        const s = newSection(type);
        change(null, () => doc.content.sections.push(s));
        openSections.add(s.id);
        renderContent({ focus: `[data-id="${s.id}"] .sec-body input, [data-id="${s.id}"] .sec-body textarea` });
        form.querySelector(`[data-id="${s.id}"]`)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      },
    }, ico(SECTION_ICONS[type] || 'list', 16), labels().sections[type] && doc.language === 'en' ? labels().sections[type] : SECTION_NAMES[type])));
    return h('div', { class: 'card card-pad add-section', style: 'padding:16px' },
      h('h3', { style: 'font-size:.95rem;margin:0' }, 'Add a section'),
      h('p', { class: 'hint', style: 'margin:4px 0 0' }, 'Each section can be reordered, renamed or hidden.'), grid);
  }

  function newSection(type) {
    const s = { id: uid('s'), type, title: '', hidden: false };
    const k = KIND[type];
    if (k === 'text') s.text = '';
    else if (k === 'custom') { s.text = ''; s.items = []; s.title = 'Additional section'; }
    else s.items = [];
    if (type === 'references') s.onRequest = false;
    return s;
  }

  /* ───────────────────────── design pane ───────────────────────── */
  const designPane = document.getElementById('pane-design');

  function contrast(hex) {
    const c = hex.replace('#', '').match(/../g).map((x) => parseInt(x, 16) / 255).map((v) => (v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4));
    const l = 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
    return 1.05 / (l + 0.05);
  }

  function seg(label, value, options, onPick) {
    const g = h('div', { class: 'seg', role: 'group', 'aria-label': label });
    Object.entries(options).forEach(([v, l]) => g.append(h('button', { type: 'button', 'aria-pressed': String(String(value) === v), onclick: () => { change(null, () => onPick(v)); renderDesign(); } }, l)));
    return h('div', { class: 'ds-group' }, h('span', { class: 'label' }, label), g);
  }

  function renderDesign() {
    const d = doc.design;
    const t = template();
    designPane.innerHTML = '';
    const sw = h('div', { class: 'swatches' });
    Object.entries(S.design.palette).forEach(([hex, name]) => sw.append(h('button', {
      type: 'button', class: 'swatch', style: `background:${hex}`, 'aria-label': name, title: name, 'aria-pressed': String(d.accent.toUpperCase() === hex.toUpperCase()),
      onclick: () => { change(null, () => { d.accent = hex; }); renderDesign(); },
    })));
    const custom = h('input', { type: 'color', value: d.accent, 'aria-label': 'Custom colour' });
    const warn = h('span', { class: 'error-text', hidden: true }, 'Too light to read on white. Choose a darker colour.');
    custom.addEventListener('change', () => {
      const v = custom.value.toUpperCase();
      if (contrast(v) < 4.5) { warn.hidden = false; return; }
      warn.hidden = true;
      change(null, () => { d.accent = v; });
      renderDesign();
    });
    designPane.append(h('div', { class: 'ds-group' }, h('span', { class: 'label' }, 'Accent colour'), sw, h('label', { class: 'custom-color' }, custom, 'Custom colour'), warn));

    const fontOpts = {};
    S.design.fonts.forEach((f) => { fontOpts[f.key] = f.label; });
    if (!fontOpts[d.font]) fontOpts[d.font] = d.font;
    const fid1 = fid();
    const fontSel = h('select', { id: fid1, class: 'select' }, Object.entries(fontOpts).map(([v, l]) => h('option', { value: v }, l)));
    fontSel.value = d.font;
    fontSel.addEventListener('change', () => change(null, () => { d.font = fontSel.value; }));
    const note = doc.language === 'ar' || doc.language === 'zh' ? h('span', { class: 'hint' }, `For ${labels().name} text, a matching ${doc.language === 'ar' ? 'Arabic' : 'Chinese'} font is used automatically.`) : null;
    designPane.append(h('div', { class: 'ds-group' }, h('label', { class: 'label', for: fid1 }, 'Font'), fontSel, note));

    const range = (label, value, min, max, step, unit, onSet) => {
      const id = fid();
      const out = h('output', { for: id }, `${value}${unit}`);
      const r = h('input', { id, type: 'range', min, max, step, value });
      r.addEventListener('input', () => { out.textContent = `${r.value}${unit}`; change(`range.${label}`, () => onSet(Number(r.value))); });
      return h('div', { class: 'ds-group' }, h('label', { class: 'label', for: id }, label), h('div', { class: 'range-row' }, r, out));
    };
    designPane.append(range('Text size', d.fontSize, S.design.fontSize.min, S.design.fontSize.max, S.design.fontSize.step || 0.5, ' pt', (v) => { d.fontSize = v; }));
    designPane.append(range('Line spacing', d.lineHeight, S.design.lineHeight.min, S.design.lineHeight.max, 0.05, '', (v) => { d.lineHeight = v; }));
    designPane.append(seg('Page margins', d.margins, { narrow: 'Narrow', normal: 'Normal', wide: 'Wide' }, (v) => { d.margins = v; }));
    designPane.append(seg('Space between sections', d.spacing, { compact: 'Compact', normal: 'Normal', relaxed: 'Relaxed' }, (v) => { d.spacing = v; }));
    designPane.append(seg('Date format', d.dateStyle, { month_year: 'Mar 2024', numeric: '03/2024', year: '2024' }, (v) => { d.dateStyle = v; }));
    designPane.append(seg('Length target', d.pageTarget, { auto: 'No target', one: 'One page', two: 'Two pages' }, (v) => { d.pageTarget = v; layoutPreview(); }));
    if (t.variants && t.variants.length > 1) {
      const names = t.layout === 'sidebar'
        ? { left: 'Sidebar left', right: 'Sidebar right' }
        : { left: 'Left-aligned', centered: 'Centred', split: 'Split', band: 'Colour band', 'photo-right': 'Photo on the right' };
      const o = {};
      t.variants.forEach((v) => { o[v] = names[v] || v; });
      designPane.append(seg(t.layout === 'sidebar' ? 'Sidebar position' : 'Header style', d.variant, o, (v) => { d.variant = v; }));
    }
    if (t.photo) {
      designPane.append(h('div', { class: 'ds-group' }, switchField('Show photo', !!d.showPhoto, (v) => { d.showPhoto = v; renderContent(); })));
    }
    designPane.append(h('div', { class: 'ds-group' }, h('button', { type: 'button', class: 'btn btn-secondary btn-sm', onclick: () => {
      change(null, () => { doc.design = structuredClone(t.defaults); });
      renderDesign();
      toast('Design reset to the template’s defaults.', 'info', 3000);
    } }, ico('rotate-ccw', 15), ' Reset to template defaults')));
  }

  /* ───────────────────────── template pane ───────────────────────── */
  const templatePane = document.getElementById('pane-template');

  function renderTemplates() {
    const c = country();
    templatePane.innerHTML = '';
    const cid = fid();
    const csel = h('select', { id: cid, class: 'select' }, Object.values(S.countries).map((x) => h('option', { value: x.code }, x.native && x.native !== x.name ? `${x.name} · ${x.native}` : x.name)));
    csel.value = doc.country;
    csel.addEventListener('change', () => switchCountry(csel.value, csel));
    const lid = fid();
    const lsel = h('select', { id: lid, class: 'select' }, c.languages.map((l) => h('option', { value: l }, S.labels[l]?.name || l)));
    lsel.value = doc.language;
    lsel.addEventListener('change', () => {
      change(null, () => { doc.language = lsel.value; });
      renderAll();
      toast(`Headings, dates and labels now use ${S.labels[lsel.value]?.name}. Your own text is not translated.`, 'info', 5000);
    });
    templatePane.append(h('div', { class: 'form-grid' },
      h('div', { class: 'field' }, h('label', { class: 'label', for: cid }, 'Country conventions'), csel),
      h('div', { class: 'field' }, h('label', { class: 'label', for: lid }, 'CV language'), lsel)));
    if (c.tips?.length) {
      templatePane.append(h('details', { class: 'tip', style: 'display:block' }, h('summary', { style: 'cursor:pointer;font-weight:600' }, `Tips for ${c.name} · ${c.lengthHint}`),
        h('ul', { style: 'margin:8px 0 0;padding-left:18px' }, c.tips.map((tp) => h('li', {}, tp)))));
    }
    const grid = h('div', { class: 'tpl-pick', role: 'group', 'aria-label': 'Templates' });
    const base = document.querySelector('meta[name="app-base"]').content;
    S.templates.filter((t) => t.country === doc.country).forEach((t) => {
      const supports = !t.languages?.length || t.languages.includes(doc.language);
      grid.append(h('button', {
        type: 'button', class: 'tpl-opt', 'aria-pressed': String(t.id === doc.templateId), 'aria-label': `${t.name}${t.id === doc.templateId ? ' (current)' : ''}`,
        onclick: () => pickTemplate(t),
      },
      h('div', { class: 'tpl-thumb' }, h('iframe', { src: `${base}/templates/${t.slug}/preview?lang=${doc.language}`, title: '', loading: 'lazy', tabindex: '-1', 'aria-hidden': 'true' })),
      h('span', { class: 'check-badge' }, ico('check', 14)),
      h('div', { class: 'meta' }, h('strong', {}, t.name), h('span', {}, [t.category, t.photo ? 'photo' : null, t.recommended ? 'recommended' : null, supports ? null : `best in ${t.languages.map((l) => S.labels[l]?.name || l).join('/')}`].filter(Boolean).join(' · ')))));
    });
    templatePane.append(h('h3', { style: 'font-size:.95rem;margin:20px 0 0' }, `Templates for ${c.name}`),
      h('p', { class: 'hint', style: 'margin:4px 0 0' }, 'Switching keeps all your content. The design resets to the new template’s defaults (undo with Ctrl+Z).'), grid);
    window.App.initThumbs(templatePane);
  }

  function pickTemplate(t) {
    if (t.id === doc.templateId) return;
    change(null, () => {
      doc.templateId = t.id;
      doc.design = structuredClone(t.defaults);
    });
    renderAll();
    toast(`Template changed to ${t.name}.`, 'success', 2500);
  }

  async function switchCountry(code, select) {
    const target = S.countries[code];
    const ok = await confirmDialog({
      title: `Use ${target.name} conventions?`,
      message: `Your content is kept. The template switches to one designed for ${target.name}, and dates, headings and photo guidance follow its conventions.`,
      confirmText: 'Switch country', iconName: 'globe',
    });
    if (!ok) { select.value = doc.country; return; }
    const old = country();
    const t = S.templates.find((x) => x.country === code && x.recommended) || S.templates.find((x) => x.country === code);
    if (!t) { toast('No template is available for this country.', 'error'); select.value = doc.country; return; }
    change(null, () => {
      doc.country = code;
      if (!target.languages.includes(doc.language)) doc.language = target.defaultLanguage;
      doc.templateId = t.id;
      doc.design = structuredClone(t.defaults);
      const p = doc.content.personal;
      if (!p.phoneCode || p.phoneCode === old.phoneCode) p.phoneCode = target.phoneCode;
    });
    renderAll();
    toast(`Now using ${target.name} conventions with the ${t.name} template.`, 'success', 4000);
  }

  /* ───────────────────────── title, progress, tabs ───────────────────────── */
  const titleBtn = document.getElementById('ed-title');
  function renderTitle() { titleBtn.textContent = doc.title; document.title = `${doc.title} · ${document.title.split(' · ').pop()}`; }
  async function rename() {
    const v = await promptDialog({ title: 'Rename this CV', input: { label: 'Name (only you see it)', value: doc.title, maxlength: 120 }, confirmText: 'Rename', iconName: 'pencil' });
    if (!v || v === doc.title) return;
    change(null, () => { doc.title = v; });
    renderTitle();
  }
  titleBtn.addEventListener('click', rename);

  function updateProgress() {
    const p = doc.content.personal;
    const by = {};
    doc.content.sections.forEach((s) => { by[s.type] = s; });
    const checks = [
      [p.fullName, 'your name'], [p.headline, 'a professional title'], [p.email, 'an e-mail'], [p.phone, 'a phone number'],
      [by.summary?.text, 'a summary'], [by.experience?.items?.length || by.internships?.items?.length, 'experience'],
      [by.education?.items?.length, 'education'], [(by.skills?.items?.length || 0) >= 3, 'three skills'], [by.languages?.items?.length, 'languages'],
    ];
    const done = checks.filter(([v]) => v && String(v).trim() !== '').length;
    const pct = Math.round((100 * done) / checks.length);
    const next = checks.find(([v]) => !(v && String(v).trim() !== ''));
    const el = document.getElementById('ed-progress');
    el.innerHTML = '';
    el.append(h('span', { class: 'nowrap', style: 'font-weight:600' }, `${pct}% complete`), h('div', { class: 'bar', role: 'progressbar', 'aria-valuenow': pct, 'aria-valuemin': 0, 'aria-valuemax': 100, 'aria-label': 'CV completeness' }, h('span', { style: `width:${pct}%` })),
      h('span', { class: 'truncate muted', style: 'max-width:45%' }, next ? `Next: add ${next[1]}` : 'Looks complete'));
  }

  const tabs = [...document.querySelectorAll('.ed-tabs [role="tab"]')];
  const panes = { 'tab-content': form, 'tab-design': designPane, 'tab-template': templatePane };
  function selectTab(tab) {
    tabs.forEach((t) => {
      const on = t === tab;
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
      panes[t.id].hidden = !on;
    });
  }
  tabs.forEach((t, i) => {
    t.addEventListener('click', () => selectTab(t));
    t.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
        const n = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
        selectTab(n); n.focus();
      }
    });
  });

  // Mobile edit / preview switch
  const body = document.querySelector('.ed-body');
  document.querySelectorAll('.ed-mobile-switch [data-view]').forEach((b) => b.addEventListener('click', () => {
    body.dataset.view = b.dataset.view;
    document.querySelectorAll('.ed-mobile-switch [data-view]').forEach((x) => x.setAttribute('aria-selected', String(x === b)));
    if (b.dataset.view === 'preview') requestAnimationFrame(layoutPreview);
  }));

  /* ───────────────────────── actions ───────────────────────── */
  async function download() {
    const ok = await save({ manual: false });
    if (!ok && changeSeq !== savedSeq) { toast('Save your changes before downloading.', 'error'); return; }
    window.App.download(root.dataset.download);
  }
  document.getElementById('ed-download').addEventListener('click', download);
  document.getElementById('ed-save').addEventListener('click', () => save({ manual: true }));
  document.getElementById('ed-menu').addEventListener('click', async (e) => {
    const b = e.target.closest('[data-action]');
    if (!b) return;
    const a = b.dataset.action;
    if (a === 'rename') rename();
    if (a === 'save') save({ manual: true });
    if (a === 'duplicate') {
      await save();
      try {
        const r = await api(`${API}/duplicate`, { body: {} });
        toast('Copy created. Opening it…', 'success', 2000);
        unloadGuard = false;
        setTimeout(() => { location.href = r.editUrl; }, 600);
      } catch (err) { toast(err.message, 'error', 6000); }
    }
    if (a === 'delete') {
      if (!(await confirmDialog({ title: 'Delete this CV?', message: `“${doc.title}” and its photo are deleted permanently.`, confirmText: 'Delete CV', danger: true }))) return;
      try {
        await api(`${API}/delete`, { body: {} });
        unloadGuard = false;
        clearBackup();
        location.href = root.dataset.dashboard;
      } catch (err) { toast(err.message, 'error', 6000); }
    }
  });

  document.addEventListener('keydown', (e) => {
    const mod = e.ctrlKey || e.metaKey;
    if (!mod || document.querySelector('dialog[open]')) return;
    const k = e.key.toLowerCase();
    if (k === 's') { e.preventDefault(); save({ manual: true }); }
    else if (k === 'z' && !e.shiftKey) { e.preventDefault(); undo(); }
    else if (k === 'y' || (k === 'z' && e.shiftKey)) { e.preventDefault(); redo(); }
  });

  /* ───────────────────────── start ───────────────────────── */
  function renderAll() {
    renderContent();
    renderDesign();
    renderTemplates();
    renderTitle();
    updateProgress();
  }

  async function offerBackup() {
    let b = null;
    try { b = JSON.parse(localStorage.getItem(BACKUP_KEY) || 'null'); } catch (_) { /* ignore */ }
    if (!b || !b.doc) return;
    if (JSON.stringify(b.doc) === snapshot()) { clearBackup(); return; }
    const when = new Date(b.at).toLocaleString();
    const restore = await confirmDialog({
      title: 'Restore unsaved changes?',
      message: `This browser kept changes from ${when} that were not saved to your account.`,
      confirmText: 'Restore them', cancelText: 'Discard', iconName: 'rotate-ccw',
    });
    if (restore) {
      record(null);
      Object.assign(doc, b.doc);
      renderAll();
      afterChange();
      toast('Changes restored. They will be saved now.', 'success', 3500);
    } else {
      clearBackup();
    }
  }

  setStatus('saved');
  updateHistoryButtons();
  renderAll();
  refreshPreview();
  scheduleExactPages();
  offerBackup();
  void escapeHtml;
})();
