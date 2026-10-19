<?php
/** @var list<array<string, mixed>> $featured @var array<string, array<string, mixed>> $countries @var array<string, int> $counts @var int $templateCount */
$cta = auth_user() ? url('/cvs/new') : url('/register');
?>
<section class="hero">
  <div class="container hero-grid">
    <div>
      <span class="eyebrow"><?= icon('globe', 16) ?> 5 countries · <?= (int) $templateCount ?> templates · A4 PDF</span>
      <h1>The professional CV, <em>made for the country</em> you’re applying to.</h1>
      <p class="hero-lead">Pick a country, choose a template built for its conventions, fill in your experience with live preview, and download a clean, correctly paginated A4 PDF — in French, English, Arabic or Chinese.</p>
      <div class="hero-actions">
        <a class="btn btn-primary btn-lg" href="<?= e($cta) ?>"><?= icon('file-plus') ?> Create my CV</a>
        <a class="btn btn-secondary btn-lg" href="<?= e(url('/templates')) ?>"><?= icon('layout-template') ?> Explore templates</a>
      </div>
      <div class="hero-proof">
        <span><?= icon('circle-check', 16) ?> Free to use</span>
        <span><?= icon('circle-check', 16) ?> Saves automatically</span>
        <span><?= icon('circle-check', 16) ?> Arabic &amp; Chinese ready</span>
      </div>
    </div>
    <div class="hero-visual" aria-hidden="true">
      <div class="paper paper-1"><iframe src="<?= e(url('/templates/ma-bilingual/preview')) ?>" title="" tabindex="-1" loading="lazy"></iframe></div>
      <div class="paper paper-2"><iframe src="<?= e(url('/templates/fr-creative/preview')) ?>" title="" tabindex="-1" loading="lazy"></iframe></div>
      <div class="paper paper-3"><iframe src="<?= e(url('/templates/us-ats-classic/preview')) ?>" title="" tabindex="-1" loading="lazy"></iframe></div>
    </div>
  </div>
</section>

<section class="section" id="how">
  <div class="container">
    <div class="section-head">
      <h2>Three steps to a CV you can send today</h2>
      <p>No design skills needed. Everything you type appears instantly on a real A4 page.</p>
    </div>
    <div class="steps">
      <div class="card step"><div class="step-num">1</div><h3>Choose a country and template</h3><p class="muted mb-0">Each country has its own conventions — length, photo, personal details, section order. Start from a template designed for them.</p></div>
      <div class="card step"><div class="step-num">2</div><h3>Personalise your CV</h3><p class="muted mb-0">Fill in guided sections, reorder them, hide what you don’t need, adjust colours and typography. Changes save as you type.</p></div>
      <div class="card step"><div class="step-num">3</div><h3>Download your PDF</h3><p class="muted mb-0">Export a true A4 PDF generated on our server — selectable text, embedded fonts and clean page breaks.</p></div>
    </div>
  </div>
</section>

<section class="section" id="countries">
  <div class="container">
    <div class="section-head">
      <h2>Built for five job markets</h2>
      <p>Language, date formats, photo guidance and section order follow each country’s practice.</p>
    </div>
    <div class="country-grid">
      <?php foreach ($countries as $code => $c): ?>
        <a class="card country-card" href="<?= e(url('/templates?country=' . $code)) ?>">
          <?= flag($code) ?>
          <strong><?= e($c['name']) ?></strong>
          <span class="muted small"><?= e($c['native']) ?></span>
          <span class="small" style="color:var(--text-2)"><?= (int) ($counts[$code] ?? 0) ?> templates · <?= e(implode(', ', array_map(fn ($l) => App\Services\Countries::languageName($l), $c['languages']))) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="row-between" style="margin-bottom:24px">
      <div><h2 style="margin:0">Templates people actually get hired with</h2><p class="muted mb-0">Sober, readable and ATS-conscious — previews are real renders.</p></div>
      <a class="btn btn-secondary" href="<?= e(url('/templates')) ?>">See all <?= (int) $templateCount ?> templates <?= icon('arrow-right', 16) ?></a>
    </div>
    <div class="tpl-grid">
      <?php foreach ($featured as $t): ?>
        <article class="card tpl-card">
          <div class="tpl-thumb">
            <div class="skeleton"></div>
            <iframe src="<?= e(url('/templates/' . $t['slug'] . '/preview')) ?>" title="<?= e($t['name']) ?> preview" tabindex="-1" loading="lazy"></iframe>
            <div class="tpl-badges"><?= flag($t['country_code']) ?></div>
          </div>
          <div class="tpl-info"><h3><?= e($t['name']) ?></h3><p><?= e($t['description']) ?></p></div>
          <div class="tpl-actions"><a class="btn btn-soft btn-sm" href="<?= e(url('/cvs/new?template=' . $t['slug'])) ?>">Use this template</a></div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" id="features">
  <div class="container">
    <div class="section-head"><h2>Everything you need, nothing you don’t</h2></div>
    <div class="features">
      <div class="card feature"><div class="ficon"><?= icon('eye') ?></div><h3>Live preview</h3><p>See every change on an A4 page as you type, with page guides so you know where the second page starts.</p></div>
      <div class="card feature"><div class="ficon"><?= icon('save') ?></div><h3>Automatic saving</h3><p>Your work is saved to your account while you write, with a clear status — and nothing is lost if your connection drops.</p></div>
      <div class="card feature"><div class="ficon"><?= icon('languages') ?></div><h3>Arabic &amp; Chinese</h3><p>Right-to-left Arabic with correct letter shaping, and Simplified Chinese with proper fonts — in the editor and in the PDF.</p></div>
      <div class="card feature"><div class="ficon"><?= icon('layers') ?></div><h3>Switch templates freely</h3><p>All templates share one content model: change the design at any time without retyping anything.</p></div>
      <div class="card feature"><div class="ficon"><?= icon('palette') ?></div><h3>Tasteful customisation</h3><p>Curated colours and fonts, size, spacing and margins — with limits that keep your CV readable.</p></div>
      <div class="card feature"><div class="ficon"><?= icon('file-down') ?></div><h3>Real A4 PDFs</h3><p>Generated on the server at 210 × 297 mm, with multi-page support. Your content is never cut to fit.</p></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="card privacy-band">
      <div>
        <span class="eyebrow"><?= icon('shield-check', 16) ?> Privacy &amp; security</span>
        <h2 style="margin-top:16px">Your CV is personal. We treat it that way.</h2>
        <p class="muted">Only you can open, edit or download your CVs. We never ask for more personal information than your CV needs.</p>
        <a href="<?= e(url('/privacy')) ?>">Read how we protect your data <?= icon('arrow-right', 14) ?></a>
      </div>
      <ul class="check-list">
        <li><?= icon('check') ?> Passwords are stored as one-way hashes, never in plain text.</li>
        <li><?= icon('check') ?> Every CV, photo and PDF is checked against its owner on the server.</li>
        <li><?= icon('check') ?> Photos and sensitive details such as date of birth are always optional.</li>
        <li><?= icon('check') ?> Delete a CV — or your whole account — at any time.</li>
      </ul>
    </div>
  </div>
</section>

<section class="section" id="faq">
  <div class="container">
    <div class="section-head"><h2>Frequently asked questions</h2></div>
    <div class="faq">
      <details><summary>Is it free? <?= icon('chevron-down') ?></summary><p>Yes. Create an account, build as many CVs as you need (up to the limit set by the administrator) and download them as PDFs.</p></details>
      <details><summary>Should I add a photo? <?= icon('chevron-down') ?></summary><p>It depends on the country. Photos are common in Morocco and China, optional in France, and usually left out in the United States and Canada. Each template tells you, and the photo is always optional.</p></details>
      <details><summary>Can I write my CV in Arabic or Chinese? <?= icon('chevron-down') ?></summary><p>Yes. Moroccan CVs support French, Arabic (right-to-left) and English; Chinese CVs support Simplified Chinese and English. The PDF uses fonts that render these scripts correctly.</p></details>
      <details><summary>Will my CV be translated automatically? <?= icon('chevron-down') ?></summary><p>No. Section titles follow the document language, but your own text — your name, experience and achievements — is never translated or changed without you.</p></details>
      <details><summary>Is the PDF compatible with applicant tracking systems? <?= icon('chevron-down') ?></summary><p>The PDFs contain real, selectable text with conventional headings, and the “Classic” templates use a simple single column. No tool can guarantee compatibility with every system, so prefer the classic templates when you apply through online portals.</p></details>
      <details><summary>What happens if my CV is longer than one page? <?= icon('chevron-down') ?></summary><p>The PDF continues on a second A4 page automatically. We never remove content to make it fit; the editor shows page guides so you can decide what to shorten.</p></details>
    </div>
    <div class="cta-band">
      <h2>Ready to write a CV that opens doors?</h2>
      <p>It takes about 15 minutes. Your progress is saved as you go.</p>
      <a class="btn btn-secondary btn-lg" href="<?= e($cta) ?>"><?= icon('file-plus') ?> Create my CV</a>
    </div>
  </div>
</section>
