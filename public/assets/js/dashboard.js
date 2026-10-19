/* Dashboard: filters, rename, duplicate, delete and download feedback. */
(function () {
  'use strict';
  const base = () => document.querySelector('meta[name="app-base"]')?.content || '';

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('cv-filters');
    if (form) {
      let t;
      form.querySelectorAll('select').forEach((s) => s.addEventListener('change', () => form.submit()));
      form.querySelector('input[type="search"]')?.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => form.submit(), 450); });
      const q = form.querySelector('input[type="search"]');
      if (q && q.value) { q.focus(); q.setSelectionRange(q.value.length, q.value.length); }
    }

    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-action]');
      if (!btn) return;
      const card = btn.closest('[data-cv-id]');
      const id = card.dataset.cvId;
      const title = card.dataset.title;
      try {
        if (btn.dataset.action === 'rename') {
          const value = await App.promptDialog({ title: 'Rename CV', iconName: 'square-pen', confirmText: 'Save', input: { label: 'CV name', value: title, maxlength: 120 } });
          if (!value || value === title) return;
          const data = await App.api(`${base()}/api/cvs/${id}/rename`, { body: { title: value } });
          card.dataset.title = data.title;
          card.querySelector('[data-cv-title]').textContent = data.title;
          App.toast('CV renamed.', 'success');
        } else if (btn.dataset.action === 'duplicate') {
          const data = await App.api(`${base()}/api/cvs/${id}/duplicate`, { body: {} });
          App.toast(`“${data.title}” created.`, 'success');
          setTimeout(() => location.reload(), 700);
        } else if (btn.dataset.action === 'delete') {
          const ok = await App.confirmDialog({
            title: `Delete “${title}”?`, message: 'This CV, its photo and its design settings will be permanently deleted. This cannot be undone.',
            confirmText: 'Delete CV', danger: true,
          });
          if (!ok) return;
          await App.api(`${base()}/api/cvs/${id}/delete`, { body: {} });
          card.style.transition = 'opacity .2s'; card.style.opacity = '0';
          setTimeout(() => { card.remove(); if (!document.querySelector('[data-cv-id]')) location.reload(); }, 200);
          App.toast('CV deleted.', 'success');
        }
      } catch (err) {
        if (err.status === 401) { location.href = `${base()}/login`; return; }
        App.toast(err.message, 'error', 7000);
      }
    });

  });
})();
