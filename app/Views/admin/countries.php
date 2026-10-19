<?php /** @var array<string, array<string, mixed>> $countries */ ?>
<div class="page-head"><div><h1>Countries &amp; languages</h1><p>Choose which countries and document languages users can pick. Conventions (sections, date formats, photo guidance) are defined in config/countries.php.</p></div></div>
<form method="post" action="<?= e(url('/admin/countries')) ?>">
  <?= csrf_field() ?>
  <div class="stack">
  <?php foreach ($countries as $code => $c): ?>
    <section class="card card-pad" aria-labelledby="h-<?= e($code) ?>">
      <div class="row-between">
        <h2 id="h-<?= e($code) ?>" style="margin:0;font-size:1.05rem"><?= flag($code) ?> <?= e($c['name']) ?> <span class="muted small" style="font-weight:400"><?= e($c['native']) ?></span></h2>
        <label class="switch"><input type="checkbox" name="countries[<?= e($code) ?>][enabled]" value="1" <?= $c['enabled'] ? 'checked' : '' ?>> Available</label>
      </div>
      <div class="form-grid" style="margin-top:16px">
        <fieldset class="field" style="border:0;padding:0;margin:0"><legend class="label">Document languages</legend><div class="row">
          <?php foreach ($c['all_languages'] as $l): ?>
            <label class="check"><input type="checkbox" name="countries[<?= e($code) ?>][languages][]" value="<?= e($l) ?>" <?= in_array($l, $c['languages'], true) ? 'checked' : '' ?>> <?= e(App\Services\Countries::languageName($l)) ?></label>
          <?php endforeach; ?></div></fieldset>
        <div class="field"><label class="label" for="dl-<?= e($code) ?>">Default language</label>
          <select class="select" id="dl-<?= e($code) ?>" name="countries[<?= e($code) ?>][default_language]">
            <?php foreach ($c['all_languages'] as $l): ?><option value="<?= e($l) ?>" <?= $c['default_language'] === $l ? 'selected' : '' ?>><?= e(App\Services\Countries::languageName($l)) ?></option><?php endforeach; ?>
          </select></div>
      </div>
      <dl class="kv small" style="margin-top:16px">
        <dt>Phone</dt><dd><?= e($c['phone_code']) ?> · e.g. <?= e($c['phone_example']) ?></dd>
        <dt>Length</dt><dd><?= e($c['length_hint']) ?></dd>
        <dt>Photo</dt><dd><?= e(['common' => 'Common (optional)', 'optional' => 'Optional', 'discouraged' => 'Usually left out'][$c['photo']] ?? $c['photo']) ?></dd>
        <dt>Optional details</dt><dd><?= e(implode(', ', $c['optional_fields'])) ?></dd>
      </dl>
    </section>
  <?php endforeach; ?>
  </div>
  <div class="row" style="justify-content:flex-end;margin-top:20px"><button class="btn btn-primary" type="submit">Save countries</button></div>
</form>
