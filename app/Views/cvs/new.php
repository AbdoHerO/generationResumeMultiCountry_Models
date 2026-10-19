<?php
/** @var array<string, array<string, mixed>> $countries @var list<array<string, mixed>> $templates @var string $country @var array<string, mixed>|null $preselected */
$langName = fn (string $l) => App\Services\Countries::languageName($l);
?>
<div class="page-head">
  <div><h1>Create a new CV</h1><p>Two quick choices, then you can start writing. You can change both later.</p></div>
  <a class="btn btn-ghost" href="<?= e(url('/dashboard')) ?>"><?= icon('arrow-left', 16) ?> Back to my CVs</a>
</div>

<ol class="wizard-steps" aria-label="Steps">
  <li class="wizard-step active" data-step-label="1"><b>1</b> Country &amp; language</li>
  <li class="wizard-step" data-step-label="2"><b>2</b> Template</li>
  <li class="wizard-step"><b>3</b> Your details</li>
  <li class="wizard-step"><b>4</b> Customise &amp; review</li>
  <li class="wizard-step"><b>5</b> Download</li>
</ol>

<form method="post" action="<?= e(url('/cvs')) ?>" id="wizard" data-preselected="<?= e($preselected['slug'] ?? '') ?>">
  <?= csrf_field() ?>

  <section data-step="1" aria-labelledby="step1-title">
    <h2 id="step1-title">Where are you applying?</h2>
    <p class="muted">The country sets the conventions: language, date format, photo guidance and suggested sections.</p>
    <div class="choice-grid" role="radiogroup" aria-label="Country">
      <?php foreach ($countries as $code => $c): ?>
        <label class="card choice">
          <input type="radio" name="country" value="<?= e($code) ?>" <?= $country === $code ? 'checked' : '' ?> required
                 data-languages="<?= e(implode(',', $c['languages'])) ?>" data-default-language="<?= e($c['default_language']) ?>">
          <span class="check-mark"><?= icon('circle-check', 20) ?></span>
          <?= flag($code) ?>
          <strong><?= e($c['name']) ?></strong>
          <span class="small muted"><?= e($c['native']) ?></span>
          <span class="xs" style="color:var(--text-2)"><?= e($c['length_hint']) ?></span>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="card card-pad" style="margin-top:20px" id="lang-panel" <?= $country === '' ? 'hidden' : '' ?>>
      <div class="form-grid">
        <div class="field">
          <span class="label" id="lang-label">Document language</span>
          <div class="lang-options" role="radiogroup" aria-labelledby="lang-label" id="lang-options">
            <?php foreach (['fr', 'en', 'ar', 'zh'] as $l): ?>
              <label data-lang="<?= $l ?>"><input type="radio" name="language" value="<?= $l ?>"> <?= e($langName($l)) ?></label>
            <?php endforeach; ?>
          </div>
          <span class="hint">Section titles and dates follow this language. Your own text is never translated.</span>
        </div>
        <div class="field">
          <label class="label" for="title">CV name <span class="opt">(optional, only you see it)</span></label>
          <input class="input" id="title" name="title" maxlength="120" placeholder="e.g. Data analyst — Casablanca">
        </div>
      </div>
      <div class="alert alert-info" style="margin-top:16px" id="country-tip" hidden><?= icon('lightbulb') ?><span></span></div>
      <div class="profile-fill" id="profile-fill" data-langs="<?= e(implode(',', $profileLanguages)) ?>" style="margin-top:16px">
        <?php if ($profileLanguages !== []): ?>
          <label class="switch"><input type="checkbox" name="from_profile" value="1" id="from-profile" checked> <span>Fill this CV with my profile <span class="muted small" id="profile-fill-note"></span></span></label>
        <?php else: ?>
          <p class="small muted mb-0"><?= icon('user', 14) ?> Tip: <a href="<?= e(url('/profile')) ?>">fill in your profile</a> or <a href="<?= e(url('/profile/import')) ?>">import a PDF CV</a> once, and every new CV can start from it.</p>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section data-step="2" aria-labelledby="step2-title" hidden>
    <h2 id="step2-title">Choose a template</h2>
    <p class="muted">The first template is the simple, clean option. Previews use sample content; switching later keeps everything you write.</p>
    <div class="tpl-grid" id="wizard-templates">
      <?php foreach ($templates as $t): ?>
        <label class="card tpl-card" data-country="<?= e($t['country_code']) ?>" data-langs="<?= e(implode(',', $t['languages'])) ?>" hidden>
          <input class="sr-only" type="radio" name="template" value="<?= e($t['slug']) ?>" <?= ($preselected['slug'] ?? '') === $t['slug'] ? 'checked' : '' ?>>
          <div class="tpl-thumb">
            <div class="skeleton"></div>
            <iframe data-src="<?= e(url('/templates/' . $t['slug'] . '/preview')) ?>" data-base-src="<?= e(url('/templates/' . $t['slug'] . '/preview')) ?>" title="" tabindex="-1"></iframe>
            <div class="tpl-badges"><?php if ($t['is_recommended']): ?><span class="badge badge-primary"><?= icon('star', 12) ?> Recommended</span><?php endif; ?></div>
          </div>
          <div class="tpl-info">
            <h3><?= e($t['name']) ?></h3>
            <p><?= e($t['description']) ?></p>
          </div>
        </label>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="wizard-bar">
    <button class="btn btn-secondary" type="button" id="wizard-back" hidden><?= icon('arrow-left', 16) ?> Back</button>
    <span class="grow"></span>
    <button class="btn btn-primary btn-lg" type="button" id="wizard-next" disabled>Continue <?= icon('arrow-right', 16) ?></button>
    <button class="btn btn-primary btn-lg" type="submit" id="wizard-create" hidden><?= icon('sparkles', 16) ?> Create my CV</button>
  </div>
</form>
<script type="application/json" id="country-tips"><?= json_script(array_map(fn ($c) => $c['tips'][0] ?? '', $countries)) ?></script>
<script src="<?= e(asset('js/gallery.js')) ?>" defer></script>
<script src="<?= e(asset('js/wizard.js')) ?>" defer></script>
