/* CV creation wizard: country & language, then template. */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('wizard');
    if (!form) return;
    const steps = [...form.querySelectorAll('[data-step]')];
    const labels = [...document.querySelectorAll('[data-step-label]')];
    const next = document.getElementById('wizard-next');
    const back = document.getElementById('wizard-back');
    const create = document.getElementById('wizard-create');
    const langPanel = document.getElementById('lang-panel');
    const tips = JSON.parse(document.getElementById('country-tips').textContent || '{}');
    let step = 1;

    const country = () => form.querySelector('input[name="country"]:checked');
    const language = () => form.querySelector('input[name="language"]:checked')?.value;

    function updateLanguages() {
      const c = country();
      if (!c) return;
      langPanel.hidden = false;
      const allowed = c.dataset.languages.split(',');
      let current = language();
      form.querySelectorAll('[data-lang]').forEach((label) => {
        const ok = allowed.includes(label.dataset.lang);
        label.hidden = !ok;
        label.querySelector('input').disabled = !ok;
      });
      if (!current || !allowed.includes(current)) {
        current = c.dataset.defaultLanguage;
        form.querySelector(`input[name="language"][value="${current}"]`).checked = true;
      }
      const tip = document.getElementById('country-tip');
      tip.hidden = !tips[c.value];
      tip.querySelector('span').textContent = tips[c.value] || '';
      next.disabled = false;
      if (typeof updateFill === 'function') updateFill();
    }

    function showTemplates() {
      const c = country().value;
      const lang = language();
      const cards = [...form.querySelectorAll('#wizard-templates [data-country]')];
      let firstVisible = null;
      cards.forEach((card) => {
        const show = card.dataset.country === c;
        card.hidden = !show;
        if (!show) return;
        firstVisible ??= card;
        const frame = card.querySelector('iframe');
        const src = `${frame.dataset.baseSrc}?lang=${encodeURIComponent(card.dataset.langs.split(',').includes(lang) ? lang : '')}`;
        if (frame.dataset.src !== undefined) frame.dataset.src = src; else if (frame.src !== src) frame.src = src;
      });
      const checked = form.querySelector('input[name="template"]:checked');
      if (!checked || checked.closest('[data-country]').dataset.country !== c) {
        firstVisible?.querySelector('input').click();
      }
      window.Gallery?.lazyFrames(form);
      window.App?.initThumbs(form);
      markSelected();
    }

    function markSelected() {
      form.querySelectorAll('#wizard-templates .tpl-card').forEach((card) => card.classList.toggle('selected', card.querySelector('input').checked));
    }

    function go(n) {
      step = n;
      steps.forEach((s) => { s.hidden = Number(s.dataset.step) !== n; });
      labels.forEach((l) => {
        const i = Number(l.dataset.stepLabel);
        l.classList.toggle('active', i === n);
        l.classList.toggle('done', i < n);
      });
      back.hidden = n === 1;
      next.hidden = n === 2;
      create.hidden = n !== 2;
      if (n === 2) showTemplates();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // "Fill with my profile" is offered for the languages the profile has.
    const fill = document.getElementById('from-profile');
    const fillNote = document.getElementById('profile-fill-note');
    const profileLangs = (document.getElementById('profile-fill')?.dataset.langs || '').split(',').filter(Boolean);
    const NAMES = { fr: 'French', en: 'English', zh: 'Chinese', ar: 'Arabic' };
    function updateFill() {
      if (!fill) return;
      const lang = language();
      const ok = profileLangs.includes(lang);
      fill.disabled = !ok;
      if (!ok) fill.checked = false; else if (!fill.dataset.touched) fill.checked = true;
      fillNote.textContent = ok ? `(${NAMES[lang]} version)` : `(your profile has no ${NAMES[lang] || lang} version)`;
    }
    fill?.addEventListener('change', () => { fill.dataset.touched = '1'; });

    form.addEventListener('change', (e) => {
      if (e.target.name === 'language' || e.target.name === 'country') updateFill();
      if (e.target.name === 'country') updateLanguages();
      if (e.target.name === 'template') markSelected();
    });
    next.addEventListener('click', () => { if (country()) go(2); });
    back.addEventListener('click', () => go(1));
    form.addEventListener('submit', (e) => {
      if (!country()) { e.preventDefault(); go(1); App.toast('Choose a country first.', 'error'); }
    });

    if (country()) {
      updateLanguages();
      if (form.dataset.preselected) go(2);
    }
  });
})();
