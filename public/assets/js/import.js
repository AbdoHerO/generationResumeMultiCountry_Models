/* PDF import: upload (text extraction), then repeated "continue" steps until the French, English
   and Chinese versions are ready, then the review page. The AI service limits tokens per minute:
   when it asks to wait, the page counts down and continues by itself. */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('import-form');
    const steps = document.getElementById('import-steps');
    const resumeButtons = document.querySelectorAll('[data-resume-import]');
    if (!form && !resumeButtons.length) return;
    const { api, toast } = window.App;
    const base = document.querySelector('meta[name="app-base"]').content;
    const input = document.getElementById('pdf');
    const drop = document.getElementById('dropzone');
    const start = document.getElementById('import-start');
    const errorBox = document.getElementById('import-error');
    const retry = document.getElementById('import-retry');
    const notes = {};
    let file = null;
    let importId = null;
    let busy = false;
    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

    const step = (name) => steps?.querySelector(`[data-step="${name}"]`);
    const note = (name) => step(name)?.querySelector('.small');
    steps?.querySelectorAll('[data-step]').forEach((li) => { notes[li.dataset.step] = li.querySelector('.small')?.textContent || ''; });
    function setNote(name, text) { const n = note(name); if (n) n.textContent = text ?? notes[name]; }
    function mark(name, state) {
      const li = step(name);
      if (!li) return;
      li.classList.remove('running', 'done', 'failed');
      if (state) li.classList.add(state);
    }
    function showError(message) {
      errorBox.hidden = false;
      errorBox.querySelector('span').textContent = message;
      retry.hidden = false;
    }
    function clearError() { errorBox.hidden = true; retry.hidden = true; }

    /** Shows where the work stands: { steps: { extract|fr|en|zh: {done, total, source?} }, ready }. */
    function render(progress) {
      let runningSet = false;
      for (const [name, s] of Object.entries(progress.steps || {})) {
        if (s.total > 0 && s.done >= s.total) {
          mark(name, 'done');
          setNote(name, s.source ? 'Original language of your CV' : null);
        } else if (!runningSet) {
          mark(name, 'running');
          runningSet = true;
          setNote(name, s.total > 1 ? `Part ${s.done + 1} of ${s.total}…` : null);
        } else {
          mark(name, null);
        }
      }
    }

    function choose(f) {
      if (!f) return;
      const max = Number(form.dataset.max);
      if (!/\.pdf$/i.test(f.name) && f.type !== 'application/pdf') { toast('Please choose a PDF file.', 'error'); return; }
      if (f.size > max) { toast(`This PDF is larger than ${Math.round(max / 1048576)} MB.`, 'error'); return; }
      file = f;
      importId = null;
      document.getElementById('drop-title').textContent = f.name;
      drop.classList.add('has-file');
      start.disabled = false;
      ['extract', 'fr', 'en', 'zh'].forEach((s) => { mark(s, null); setNote(s, null); });
      clearError();
    }
    input?.addEventListener('change', () => choose(input.files[0]));
    ['dragenter', 'dragover'].forEach((ev) => drop?.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.add('over'); }));
    ['dragleave', 'drop'].forEach((ev) => drop?.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.remove('over'); }));
    drop?.addEventListener('drop', (e) => choose(e.dataTransfer.files[0]));

    async function waitFor(seconds) {
      const name = steps?.querySelector('li.running')?.dataset.step;
      for (let s = seconds; s > 0; s--) {
        if (name) setNote(name, `Waiting for the AI quota — continuing in ${s} s…`);
        await sleep(1000);
      }
    }
    function warn(e) { e.preventDefault(); e.returnValue = ''; }

    async function run() {
      if (busy) return;
      busy = true;
      if (start) start.disabled = true;
      clearError();
      window.addEventListener('beforeunload', warn);
      // Live elapsed time, so a wait for the AI quota never looks like a frozen page.
      const elapsed = document.getElementById('import-elapsed');
      const t0 = Date.now();
      const tick = () => {
        const s = Math.round((Date.now() - t0) / 1000);
        if (elapsed) { elapsed.hidden = false; elapsed.textContent = `Working… ${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')} elapsed`; }
      };
      tick();
      const timer = setInterval(tick, 1000);
      try {
        if (!importId) {
          mark('extract', 'running');
          const fd = new FormData();
          fd.append('pdf', file);
          const r = await api(`${base}/api/profile/import`, { formData: fd });
          importId = r.id;
          render(r.progress);
        }
        let quotaWaits = 0;
        for (;;) {
          let r;
          try {
            r = await api(`${base}/api/profile/imports/${importId}/continue`, { body: {} });
          } catch (err) {
            const wait = err.status === 429 ? Number(err.data?.errors?.retryAfter || 0) : 0;
            if (wait && quotaWaits++ < 40) { await waitFor(wait); continue; }
            throw err;
          }
          render(r.progress);
          if (r.ready) {
            toast('Your CV is ready to review.', 'success', 2500);
            window.removeEventListener('beforeunload', warn);
            setTimeout(() => { location.href = r.reviewUrl; }, 500);
            return;
          }
        }
      } catch (err) {
        const running = steps?.querySelector('li.running');
        if (running) mark(running.dataset.step, 'failed');
        showError(err.message);
        if (start) start.disabled = false;
      } finally {
        clearInterval(timer);
        busy = false;
        window.removeEventListener('beforeunload', warn);
      }
    }

    form?.addEventListener('submit', (e) => { e.preventDefault(); if (file || importId) run(); });
    retry?.addEventListener('click', run);
    // An import left unfinished continues where it stopped.
    resumeButtons.forEach((b) => b.addEventListener('click', () => {
      importId = Number(b.dataset.resumeImport);
      steps?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      run();
    }));
  });
})();
