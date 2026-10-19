// Drives the local app in Chrome: signs in, exercises the editor, records console errors and screenshots.
const puppeteer = require('puppeteer-core');
const path = require('path');
const B = process.env.BASE || 'http://127.0.0.1:8000';
const OUT = process.env.SHOTS_DIR || path.join(__dirname, 'shots');
require('fs').mkdirSync(OUT, { recursive: true });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const browser = await puppeteer.launch({ executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: 'new', args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900 });
  const errors = [];
  page.on('console', (m) => { if (m.type() === 'error' || m.type() === 'warning') errors.push(`[${m.type()}] ${m.text()} @ ${page.url()}`); });
  page.on('pageerror', (e) => { errors.push(e.message); console.log('PAGEERROR', e.stack || e.message); });
  page.on('requestfailed', (r) => { if (!r.url().includes('fonts.g')) errors.push(`[requestfailed] ${r.url()} ${r.failure()?.errorText}`); });

  const shot = async (name) => page.screenshot({ path: path.join(OUT, name + '.png') });

  for (const p of ['/', '/templates', '/login', '/register']) {
    await page.goto(B + p, { waitUntil: 'networkidle0' });
    await shot('pub' + p.replace(/\//g, '_'));
  }
  await page.goto(B + '/login', { waitUntil: 'networkidle0' });
  await page.type('#email', 'demo@example.test');
  await page.type('#password', 'demo-password-1');
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('form button[type="submit"]')]);
  console.log('after login:', page.url());
  await shot('dashboard');
  await page.goto(B + '/cvs/new', { waitUntil: 'networkidle0' });
  await shot('wizard');

  await page.goto(B + '/cvs/1/edit', { waitUntil: 'networkidle0' });
  await sleep(1500);
  await shot('editor-1');
  const status = () => page.$eval('#ed-status .txt', (e) => e.textContent);
  console.log('status at load:', await status());

  // Edit the headline in personal details
  await page.focus('#editor-form .sec-card[data-id="personal"] input.input:nth-of-type(1)').catch(() => {});
  const inputs = await page.$$('#editor-form .sec-card[data-id="personal"] .sec-body input.input');
  console.log('personal inputs:', inputs.length);
  await inputs[1].click({ clickCount: 3 });
  await inputs[1].type('Responsable Data & BI');
  console.log('status after typing:', await status());
  await sleep(2500);
  console.log('status after autosave:', await status());
  // Undo / redo
  await page.keyboard.down('Control'); await page.keyboard.press('z'); await page.keyboard.up('Control');
  await sleep(300);
  console.log('headline after undo:', await page.evaluate(() => document.querySelectorAll('#editor-form .sec-card[data-id="personal"] .sec-body input.input')[1].value));
  await page.keyboard.down('Control'); await page.keyboard.press('y'); await page.keyboard.up('Control');
  await sleep(300);
  console.log('headline after redo:', await page.evaluate(() => document.querySelectorAll('#editor-form .sec-card[data-id="personal"] .sec-body input.input')[1].value));

  // Open the experience section, expand first item
  const secIds = await page.$$eval('#sections .sec-card', (els) => els.map((e) => e.dataset.id + ':' + e.getAttribute('aria-label')));
  console.log('sections:', secIds.join(' | '));
  await page.click('#sections .sec-card:nth-child(2) .sec-toggle');
  await sleep(200);
  await page.click('#sections .sec-card:nth-child(2) .item:first-child .item-toggle').catch(() => console.log('no item toggle'));
  await sleep(300);
  await shot('editor-2-section-open');
  // Move section 2 up with the button
  await page.click('#sections .sec-card:nth-child(2) [data-focus^="up-"]');
  await sleep(300);
  console.log('after move up:', (await page.$$eval('#sections .sec-card', (els) => els.map((e) => e.getAttribute('aria-label')))).join(' | '));
  // Add a custom section
  const before = await page.$$eval('#sections .sec-card', (els) => els.length);
  const addButtons = await page.$$('.add-section-grid button');
  await addButtons[addButtons.length - 1].click();
  await sleep(300);
  console.log('sections', before, '->', await page.$$eval('#sections .sec-card', (els) => els.length));
  // Hide it
  await page.click('#sections .sec-card:last-child [data-focus^="vis-"]');
  await sleep(200);

  // Design tab
  await page.click('#tab-design');
  await sleep(200);
  const sw = await page.$$('.swatch');
  await sw[3].click();
  await sleep(1500);
  await shot('editor-3-design');
  // Template tab
  await page.click('#tab-template');
  await sleep(2500);
  await shot('editor-4-template');
  const opts = await page.$$('.tpl-opt');
  console.log('template options:', opts.length);
  await opts[1].click();
  await sleep(2500);
  await shot('editor-5-template-switched');
  console.log('page badge:', await page.$eval('#page-count', (e) => e.textContent));
  await sleep(5000);
  console.log('page badge (exact):', await page.$eval('#page-count', (e) => e.textContent + ' / ' + e.title));
  await page.click('#ed-save');
  await sleep(1500);
  console.log('status after manual save:', await status());

  // Arabic CV and Chinese CV previews
  for (const id of [2, 3, 4, 5]) {
    await page.goto(B + `/cvs/${id}/edit`, { waitUntil: 'networkidle0' });
    await sleep(1800);
    await shot(`editor-cv${id}`);
  }

  // Mobile
  await page.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true });
  await page.goto(B + '/cvs/1/edit', { waitUntil: 'networkidle0' });
  await sleep(1200);
  await shot('mobile-editor-edit');
  await page.click('.ed-mobile-switch [data-view="preview"]');
  await sleep(800);
  await shot('mobile-editor-preview');
  await page.goto(B + '/dashboard', { waitUntil: 'networkidle0' });
  await shot('mobile-dashboard');
  await page.goto(B + '/', { waitUntil: 'networkidle0' });
  await shot('mobile-landing');

  console.log('\nCONSOLE/NETWORK ISSUES:\n' + (errors.join('\n') || 'none'));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
