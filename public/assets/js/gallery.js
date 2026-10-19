/* Template gallery: filters, lazy previews, preview dialog. Also used by the creation wizard. */
(function () {
  'use strict';
  const LANG_NAMES = { fr: 'Français', en: 'English', ar: 'العربية', zh: '简体中文' };

  function lazyFrames(root = document) {
    // Idempotent: a frame is observed once, and loads whatever data-src holds when it scrolls into view.
    const frames = [...root.querySelectorAll('iframe[data-src]')].filter((f) => !f.dataset.lazy);
    const load = (f) => { if (f.dataset.src) { f.src = f.dataset.src; f.removeAttribute('data-src'); } };
    if (!('IntersectionObserver' in window)) { frames.forEach(load); return; }
    const io = new IntersectionObserver((entries) => entries.forEach((en) => {
      if (en.isIntersecting) { load(en.target); io.unobserve(en.target); }
    }), { rootMargin: '300px' });
    frames.forEach((f) => { f.dataset.lazy = '1'; io.observe(f); });
  }

  function initFilters() {
    const grid = document.getElementById('tpl-grid');
    if (!grid) return;
    const cards = [...grid.querySelectorAll('.tpl-card')];
    const chips = [...document.querySelectorAll('.chip[data-country]')];
    const style = document.getElementById('tpl-style');
    const search = document.getElementById('tpl-search');
    const count = document.getElementById('tpl-count');
    const empty = document.getElementById('tpl-empty');
    let country = chips.find((c) => c.getAttribute('aria-pressed') === 'true')?.dataset.country || '';

    const apply = () => {
      const q = (search?.value || '').trim().toLowerCase();
      const cat = style?.value || '';
      let shown = 0;
      cards.forEach((card) => {
        const ok = (!country || card.dataset.country === country) && (!cat || card.dataset.category === cat) && (!q || card.dataset.search.includes(q));
        card.hidden = !ok;
        if (ok) shown++;
      });
      if (count) count.textContent = `${shown} template${shown === 1 ? '' : 's'}`;
      if (empty) empty.hidden = shown !== 0;
      const url = new URL(location.href);
      country ? url.searchParams.set('country', country) : url.searchParams.delete('country');
      history.replaceState(null, '', url);
      window.App?.initThumbs(grid);
    };
    chips.forEach((chip) => chip.addEventListener('click', () => {
      country = chip.dataset.country;
      chips.forEach((c) => c.setAttribute('aria-pressed', String(c === chip)));
      apply();
    }));
    style?.addEventListener('change', apply);
    search?.addEventListener('input', apply);
    document.getElementById('tpl-reset')?.addEventListener('click', () => {
      country = ''; if (style) style.value = ''; if (search) search.value = '';
      chips.forEach((c) => c.setAttribute('aria-pressed', String(c.dataset.country === '')));
      apply();
    });
    apply();
  }

  function initPreviewDialog() {
    const dlg = document.getElementById('tpl-dialog');
    if (!dlg) return;
    const frame = document.getElementById('tpl-frame');
    const wrap = document.getElementById('tpl-frame-wrap');
    const langSel = document.getElementById('tpl-lang');
    const use = document.getElementById('tpl-use');
    let slug = '';
    let device = 'desktop';

    const fit = () => {
      const width = device === 'mobile' ? 360 : Math.min(wrap.clientWidth - 40, 794);
      const scale = width / 794;
      frame.style.transform = `scale(${scale})`;
      frame.style.marginBottom = `${-(1 - scale) * 1123}px`;
      frame.style.marginLeft = frame.style.marginRight = `${-(1 - scale) * 397}px`;
    };
    const load = () => { frame.src = `${document.querySelector('meta[name="app-base"]')?.content || ''}/templates/${slug}/preview?lang=${langSel.value}`; };

    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-preview]');
      if (!btn) return;
      slug = btn.dataset.preview;
      document.getElementById('tpl-dialog-title').textContent = btn.dataset.name;
      langSel.innerHTML = btn.dataset.langs.split(',').map((l) => `<option value="${l}">${LANG_NAMES[l] || l}</option>`).join('');
      if (use) use.href = btn.closest('.tpl-card')?.querySelector('a.btn-primary')?.href || '#';
      load();
      dlg.showModal();
      requestAnimationFrame(fit);
    });
    langSel.addEventListener('change', load);
    dlg.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => dlg.close()));
    dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
    dlg.querySelectorAll('[data-device]').forEach((b) => b.addEventListener('click', () => {
      device = b.dataset.device;
      dlg.querySelectorAll('[data-device]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
      fit();
    }));
    window.addEventListener('resize', () => dlg.open && fit());
  }

  document.addEventListener('DOMContentLoaded', () => { lazyFrames(); initFilters(); initPreviewDialog(); });
  window.Gallery = { lazyFrames };
})();
