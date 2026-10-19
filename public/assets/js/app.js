/* ResumeAtlas — shared UI behaviour: toasts, dialogs, menus, API helper, thumbnails. */
(function () {
  'use strict';

  const icon = (name, size = 18) =>
    `<svg class="icon" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${(window.ICONS || {})[name] || ''}</svg>`;

  const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /* ───────── Toasts ───────── */
  let toastRoot;
  function toast(message, type = 'info', timeout = 4500) {
    if (!toastRoot) {
      toastRoot = document.createElement('div');
      toastRoot.className = 'toasts';
      toastRoot.setAttribute('role', 'status');
      toastRoot.setAttribute('aria-live', 'polite');
      document.body.appendChild(toastRoot);
    }
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    const iconName = type === 'success' ? 'circle-check' : type === 'error' ? 'triangle-alert' : 'info';
    el.innerHTML = `${icon(iconName)}<div class="toast-msg">${escapeHtml(message)}</div><button type="button" aria-label="Dismiss">${icon('x', 16)}</button>`;
    const close = () => { el.classList.add('leaving'); setTimeout(() => el.remove(), 200); };
    el.querySelector('button').addEventListener('click', close);
    toastRoot.appendChild(el);
    if (timeout) setTimeout(close, timeout);
    return close;
  }

  /* ───────── Dialogs (native <dialog>, focus handled by the browser) ───────── */
  function dialog({ title, message = '', confirmText = 'Confirm', cancelText = 'Cancel', danger = false, iconName, input = null, html = '' }) {
    return new Promise((resolve) => {
      const d = document.createElement('dialog');
      d.className = 'dialog';
      const id = 'dlg-' + Math.random().toString(36).slice(2);
      d.setAttribute('aria-labelledby', id + '-t');
      d.innerHTML = `
        <form method="dialog">
          <div class="dialog-body">
            <div class="dialog-icon ${danger ? 'danger' : ''}">${icon(iconName || (danger ? 'triangle-alert' : 'info'), 22)}</div>
            <h2 id="${id}-t" style="font-size:1.15rem">${escapeHtml(title)}</h2>
            ${message ? `<p class="muted mb-0">${escapeHtml(message)}</p>` : ''}
            ${html}
            ${input ? `<div class="field" style="margin-top:16px"><label class="label" for="${id}-i">${escapeHtml(input.label)}</label>
              <input class="input" id="${id}-i" maxlength="${input.maxlength || 120}" value="${escapeHtml(input.value || '')}" required></div>` : ''}
          </div>
          <div class="dialog-actions">
            <button type="button" class="btn btn-secondary" value="cancel">${escapeHtml(cancelText)}</button>
            <button type="submit" class="btn ${danger ? 'btn-danger' : 'btn-primary'}" value="ok">${escapeHtml(confirmText)}</button>
          </div>
        </form>`;
      document.body.appendChild(d);
      const field = d.querySelector('input.input');
      d.querySelector('button[value="cancel"]').addEventListener('click', () => d.close('cancel'));
      d.querySelector('form').addEventListener('submit', (e) => {
        if (field && !field.value.trim()) { e.preventDefault(); field.setAttribute('aria-invalid', 'true'); field.focus(); return; }
        d.returnValue = 'ok';
      });
      d.addEventListener('close', () => {
        const ok = d.returnValue === 'ok';
        const value = field ? field.value.trim() : true;
        d.remove();
        resolve(ok ? value : null);
      });
      d.showModal();
      if (field) { field.focus(); field.select(); }
    });
  }
  const confirmDialog = (opts) => dialog(opts).then((v) => v !== null);
  const promptDialog = (opts) => dialog(opts);

  /* ───────── API helper ───────── */
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
  class ApiError extends Error {
    constructor(message, status, code, data) { super(message); this.status = status; this.code = code; this.data = data; }
  }
  async function api(url, { method = 'POST', body, formData, signal } = {}) {
    const headers = { 'Accept': 'application/json', 'X-CSRF-Token': csrf() };
    let payload;
    if (formData) payload = formData;
    else if (body !== undefined) { headers['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }
    let res;
    try {
      res = await fetch(url, { method, headers, body: payload, credentials: 'same-origin', signal });
    } catch (e) {
      if (e.name === 'AbortError') throw e;
      throw new ApiError('You appear to be offline. Check your connection.', 0, 'network');
    }
    let json = null;
    try { json = await res.json(); } catch (_) { /* not JSON */ }
    if (!res.ok || !json || json.ok === false) {
      throw new ApiError(json?.message || `Request failed (${res.status}).`, res.status, json?.code || '', json);
    }
    return json.data || {};
  }

  /* ───────── Menus ───────── */
  function closeMenus(except) {
    document.querySelectorAll('[data-menu-button][aria-expanded="true"]').forEach((b) => {
      if (b !== except) { b.setAttribute('aria-expanded', 'false'); document.getElementById(b.getAttribute('aria-controls'))?.setAttribute('hidden', ''); }
    });
  }
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-menu-button]');
    if (btn) {
      e.preventDefault();
      const menu = document.getElementById(btn.getAttribute('aria-controls'));
      const open = btn.getAttribute('aria-expanded') !== 'true';
      closeMenus(btn);
      btn.setAttribute('aria-expanded', String(open));
      menu?.toggleAttribute('hidden', !open);
      if (open) menu?.querySelector('button, a')?.focus();
      return;
    }
    if (!e.target.closest('.menu')) closeMenus();
  });
  document.addEventListener('keydown', (e) => {
    const menu = e.target.closest?.('.menu');
    if (e.key === 'Escape') {
      const open = document.querySelector('[data-menu-button][aria-expanded="true"]');
      if (open) { closeMenus(); open.focus(); }
    }
    if (menu && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
      e.preventDefault();
      const items = [...menu.querySelectorAll('button, a')];
      const i = items.indexOf(document.activeElement);
      items[(i + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length]?.focus();
    }
  });

  /* ───────── Scaled A4 thumbnails (iframes are 794 × 1123 px = A4 at 96 dpi) ───────── */
  const thumbObserver = 'ResizeObserver' in window ? new ResizeObserver((entries) => {
    entries.forEach((en) => {
      const frame = en.target.querySelector('iframe');
      if (frame) frame.style.transform = `scale(${en.contentRect.width / 794})`;
    });
  }) : null;
  function initThumbs(root = document) {
    root.querySelectorAll('.tpl-thumb').forEach((el) => {
      if (el.dataset.thumbInit) return;
      el.dataset.thumbInit = '1';
      const frame = el.querySelector('iframe');
      if (!frame) return;
      frame.style.transform = `scale(${el.clientWidth / 794})`;
      thumbObserver?.observe(el);
      frame.addEventListener('load', () => el.querySelector('.skeleton')?.remove(), { once: true });
    });
  }

  /* ───────── Layout toggles ───────── */
  function initShell() {
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.backdrop');
    document.querySelectorAll('[data-sidebar-toggle]').forEach((b) => b.addEventListener('click', () => {
      const open = !sidebar.classList.contains('open');
      sidebar.classList.toggle('open', open);
      backdrop?.classList.toggle('open', open);
      b.setAttribute('aria-expanded', String(open));
    }));
    backdrop?.addEventListener('click', () => { sidebar.classList.remove('open'); backdrop.classList.remove('open'); });
    document.querySelectorAll('[data-nav-toggle]').forEach((b) => b.addEventListener('click', () => {
      const nav = document.querySelector('.nav');
      const open = !nav.classList.contains('open');
      nav.classList.toggle('open', open);
      b.setAttribute('aria-expanded', String(open));
    }));
    // Show / hide password
    document.querySelectorAll('[data-toggle-password]').forEach((b) => b.addEventListener('click', () => {
      const input = document.getElementById(b.dataset.togglePassword);
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      b.setAttribute('aria-pressed', String(show));
      b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      b.innerHTML = icon(show ? 'eye-off' : 'eye');
    }));
    // Password strength hint
    document.querySelectorAll('[data-strength]').forEach((input) => {
      const bar = document.getElementById(input.dataset.strength)?.querySelector('span');
      const label = document.getElementById(input.dataset.strength + '-label');
      input.addEventListener('input', () => {
        const v = input.value;
        let score = 0;
        if (v.length >= 8) score++;
        if (v.length >= 12) score++;
        if (/[a-z]/i.test(v) && /\d/.test(v)) score++;
        if (/[^A-Za-z0-9]/.test(v) || /[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
        const pct = [0, 25, 50, 75, 100][score];
        const color = ['#C4302B', '#C4302B', '#B54708', '#157F3D', '#157F3D'][score];
        if (bar) { bar.style.width = pct + '%'; bar.style.background = color; }
        if (label) label.textContent = v ? ['Too weak', 'Weak', 'Fair', 'Good', 'Strong'][score] : '';
      });
    });
    // Confirmation for plain forms
    document.querySelectorAll('form[data-confirm]').forEach((form) => form.addEventListener('submit', async (e) => {
      if (form.dataset.confirmed) return;
      e.preventDefault();
      const ok = await confirmDialog({
        title: form.dataset.confirm, message: form.dataset.confirmMessage || '',
        confirmText: form.dataset.confirmButton || 'Confirm', danger: form.dataset.confirmDanger === '1',
      });
      if (ok) { form.dataset.confirmed = '1'; form.requestSubmit ? form.requestSubmit() : form.submit(); }
    }));
    // Submit buttons show progress once
    document.querySelectorAll('form:not([data-no-busy])').forEach((form) => form.addEventListener('submit', () => {
      const b = form.querySelector('button[type="submit"]:not([data-no-busy])');
      if (b && !form.dataset.busy && (!form.dataset.confirm || form.dataset.confirmed)) {
        form.dataset.busy = '1';
        setTimeout(() => { b.disabled = true; b.insertAdjacentHTML('afterbegin', '<span class="spinner" aria-hidden="true"></span>'); }, 0);
      }
    }));
  }


  /* ───────── PDF downloads with progress and the server's file name ───────── */
  async function download(url) {
    const done = toast('Preparing your PDF…', 'info', 0);
    try {
      const res = await fetch(url, { credentials: 'same-origin' });
      if (!res.ok) throw new Error(res.status === 401 ? 'Your session has ended. Please sign in again.' : 'The PDF could not be generated. Please try again.');
      const blob = await res.blob();
      const cd = res.headers.get('Content-Disposition') || '';
      const m = /filename\*=UTF-8''([^;]+)/.exec(cd) || /filename="([^"]+)"/.exec(cd);
      const name = m ? decodeURIComponent(m[1]) : 'CV.pdf';
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob); a.download = name; document.body.appendChild(a); a.click();
      setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
      done();
      const pages = res.headers.get('X-Page-Count');
      toast(`Downloaded ${name}${pages ? ` (${pages} page${pages === '1' ? '' : 's'})` : ''}.`, 'success');
      return true;
    } catch (err) {
      done();
      toast(err.message, 'error', 7000);
      return false;
    }
  }
  document.addEventListener('click', (e) => {
    const link = e.target.closest('a[data-download]');
    if (!link || e.defaultPrevented) return;
    e.preventDefault();
    download(link.href);
  });

  /* ───────── Copy to clipboard: <button data-copy="element-id"> ───────── */
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-copy]');
    if (!btn) return;
    const text = document.getElementById(btn.dataset.copy)?.textContent.trim() || '';
    try {
      await navigator.clipboard.writeText(text);
      toast('Copied.', 'success', 2000);
    } catch (_) {
      const range = document.createRange();
      range.selectNodeContents(document.getElementById(btn.dataset.copy));
      getSelection().removeAllRanges();
      getSelection().addRange(range);
      toast('Press Ctrl+C to copy.', 'info', 3000);
    }
  });

  function showFlash() {
    const el = document.getElementById('flash-data');
    if (!el) return;
    try { JSON.parse(el.textContent).forEach((f) => toast(f.message, f.type === 'error' ? 'error' : f.type === 'success' ? 'success' : 'info', f.type === 'error' ? 7000 : 5000)); } catch (_) { /* ignore */ }
  }

  document.addEventListener('DOMContentLoaded', () => { initShell(); initThumbs(); showFlash(); });

  window.App = { download, toast, confirmDialog, promptDialog, dialog, api, ApiError, icon, escapeHtml, initThumbs, csrf };
})();
