<?php
/** @var list<array<string, mixed>> $templates @var array<string, array<string, mixed>> $countries @var string $selectedCountry @var list<string> $categories */
$catLabels = ['classic' => 'Classic', 'modern' => 'Modern', 'executive' => 'Executive', 'creative' => 'Creative', 'compact' => 'Compact',
    'academic' => 'Academic', 'bilingual' => 'Bilingual', 'technical' => 'Technical', 'entry-level' => 'Entry-level', 'career' => 'Career'];
?>
<section class="container" style="padding-top:40px">
  <div class="page-head" style="padding:0">
    <div>
      <h1>CV templates</h1>
      <p><?= count($templates) ?> professional templates, five per country. The first of each country is the simple, clean choice.</p>
    </div>
  </div>

  <div class="filters" role="search">
    <div class="chips" role="group" aria-label="Country">
      <button class="chip" type="button" data-country="" aria-pressed="<?= $selectedCountry === '' ? 'true' : 'false' ?>">All countries</button>
      <?php foreach ($countries as $code => $c): ?>
        <button class="chip" type="button" data-country="<?= e($code) ?>" aria-pressed="<?= $selectedCountry === $code ? 'true' : 'false' ?>"><?= flag($code) ?> <?= e($c['name']) ?></button>
      <?php endforeach; ?>
    </div>
    <label class="sr-only" for="tpl-style">Style</label>
    <select class="select" id="tpl-style" style="width:auto">
      <option value="">All styles</option>
      <?php foreach ($categories as $cat): ?><option value="<?= e($cat) ?>"><?= e($catLabels[$cat] ?? $cat) ?></option><?php endforeach; ?>
    </select>
    <label class="sr-only" for="tpl-search">Search templates</label>
    <input class="input" type="search" id="tpl-search" placeholder="Search templates…" autocomplete="off">
  </div>

  <p class="muted small" id="tpl-count" aria-live="polite"></p>
  <div class="tpl-grid" id="tpl-grid">
    <?php foreach ($templates as $t): ?>
      <article class="card tpl-card" data-country="<?= e($t['country_code']) ?>" data-category="<?= e($t['category']) ?>"
               data-search="<?= e(mb_strtolower($t['name'] . ' ' . $t['description'] . ' ' . ($countries[$t['country_code']]['name'] ?? ''))) ?>">
        <div class="tpl-thumb">
          <div class="skeleton"></div>
          <iframe data-src="<?= e(url('/templates/' . $t['slug'] . '/preview')) ?>" title="<?= e($t['name']) ?> preview" tabindex="-1"></iframe>
          <div class="tpl-badges"><?= flag($t['country_code']) ?><?php if ($t['is_recommended']): ?><span class="badge badge-primary"><?= icon('star', 12) ?> Recommended</span><?php endif; ?></div>
        </div>
        <div class="tpl-info">
          <div class="row-between"><h3><?= e($t['name']) ?></h3><span class="badge"><?= e($catLabels[$t['category']] ?? $t['category']) ?></span></div>
          <p><?= e($t['description']) ?></p>
          <span class="xs muted"><?= e(implode(' · ', array_map(fn ($l) => App\Services\Countries::languageName($l), $t['languages']))) ?></span>
        </div>
        <div class="tpl-actions">
          <button class="btn btn-secondary btn-sm" type="button" data-preview="<?= e($t['slug']) ?>" data-name="<?= e($t['name']) ?>" data-langs="<?= e(implode(',', $t['languages'])) ?>"><?= icon('eye', 16) ?> Preview</button>
          <a class="btn btn-primary btn-sm" href="<?= e(url('/cvs/new?template=' . $t['slug'])) ?>">Use this template</a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <div class="empty card" id="tpl-empty" hidden>
    <div class="ficon"><?= icon('search', 28) ?></div>
    <h2>No template matches</h2>
    <p>Try another country or style, or clear the search.</p>
    <button class="btn btn-secondary" type="button" id="tpl-reset">Clear filters</button>
  </div>
</section>

<dialog class="dialog dialog-wide" id="tpl-dialog" aria-labelledby="tpl-dialog-title">
  <div class="card-header">
    <h2 id="tpl-dialog-title" style="margin:0;font-size:1.1rem"></h2>
    <div class="row">
      <label class="sr-only" for="tpl-lang">Document language</label>
      <select class="select" id="tpl-lang" style="width:auto;min-height:34px;padding-top:4px;padding-bottom:4px"></select>
      <div class="device-toggle" role="group" aria-label="Preview size">
        <button type="button" data-device="desktop" aria-pressed="true"><?= icon('monitor', 16) ?> Desktop</button>
        <button type="button" data-device="mobile" aria-pressed="false"><?= icon('smartphone', 16) ?> Phone</button>
      </div>
      <button class="btn btn-ghost btn-icon" type="button" data-close aria-label="Close preview"><?= icon('x') ?></button>
    </div>
  </div>
  <div class="preview-frame-wrap" id="tpl-frame-wrap"><iframe id="tpl-frame" title="Template preview" width="794" height="1123"></iframe></div>
  <div class="dialog-actions"><button class="btn btn-secondary" type="button" data-close>Close</button><a class="btn btn-primary" id="tpl-use" href="#">Use this template</a></div>
</dialog>
<script src="<?= e(asset('js/gallery.js')) ?>" defer></script>
