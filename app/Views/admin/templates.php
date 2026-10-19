<?php
/** @var list<array<string, mixed>> $templates @var array<int, int> $usage @var array<string, array<string, mixed>> $countries @var list<string> $categories */
$byCountry = [];
foreach ($templates as $t) {
    $byCountry[$t['country_code']][] = $t;
}
?>
<div class="page-head"><div><h1>Templates</h1><p>Availability and metadata. Layouts themselves are defined in code (config/templates.php); hiding a template never changes existing CVs.</p></div></div>

<?php foreach ($byCountry as $code => $list): ?>
  <section class="card" style="margin-bottom:20px" aria-labelledby="c-<?= e($code) ?>">
    <div class="card-header"><h2 id="c-<?= e($code) ?>" style="margin:0;font-size:1.05rem"><?= flag($code) ?> <?= e($countries[$code]['name'] ?? $code) ?></h2><span class="badge"><?= count(array_filter($list, fn ($t) => $t['is_active'])) ?> / <?= count($list) ?> available</span></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th scope="col" style="width:140px">Preview</th><th scope="col">Template</th><th scope="col">Languages</th><th scope="col">CVs</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
      <tbody>
      <?php foreach ($list as $t): ?>
        <tr id="t-<?= (int) $t['id'] ?>">
          <td>
            <div class="thumb-zoom">
              <div class="tpl-thumb"><iframe src="<?= e(url('/templates/' . $t['slug'] . '/preview')) ?>" title="" loading="lazy" tabindex="-1" aria-hidden="true"></iframe></div>
              <button type="button" class="thumb-zoom-btn" data-preview="<?= e($t['slug']) ?>" data-name="<?= e($t['name']) ?>" data-langs="<?= e(implode(',', $t['languages'])) ?>" aria-label="Enlarge the preview of <?= e($t['name']) ?>" title="Enlarge"><span class="thumb-zoom-icon"><?= icon('zoom-in', 18) ?></span></button>
            </div>
          </td>
          <td style="min-width:260px">
            <details>
              <summary style="cursor:pointer"><strong><?= e($t['name']) ?></strong> <?= $t['is_recommended'] ? '<span class="badge badge-primary">Recommended</span>' : '' ?><div class="small muted"><?= e($t['slug']) ?> · <?= e($t['category']) ?> · <?= e($t['config']['layout']) ?> layout · order <?= (int) $t['sort_order'] ?></div></summary>
              <form method="post" action="<?= e(url('/admin/templates/' . $t['id'])) ?>" class="stack" style="margin-top:12px;max-width:520px">
                <?= csrf_field() ?>
                <div class="field"><label class="label" for="n<?= $t['id'] ?>">Name</label><input class="input" id="n<?= $t['id'] ?>" name="name" value="<?= e($t['name']) ?>" maxlength="120" required></div>
                <div class="field"><label class="label" for="d<?= $t['id'] ?>">Description</label><textarea class="textarea" id="d<?= $t['id'] ?>" name="description" maxlength="600" rows="3"><?= e($t['description']) ?></textarea></div>
                <div class="form-grid">
                  <div class="field"><label class="label" for="c<?= $t['id'] ?>">Category</label><select class="select" id="c<?= $t['id'] ?>" name="category">
                    <?php foreach ($categories as $cat): ?><option value="<?= e($cat) ?>" <?= $t['category'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option><?php endforeach; ?></select></div>
                  <div class="field"><label class="label" for="o<?= $t['id'] ?>">Sort order</label><input class="input" type="number" id="o<?= $t['id'] ?>" name="sort_order" min="0" max="9999" value="<?= (int) $t['sort_order'] ?>"></div>
                </div>
                <fieldset class="field" style="border:0;padding:0;margin:0"><legend class="label">Supported languages</legend><div class="row">
                  <?php foreach (config('countries.' . $code . '.languages', []) as $l): ?>
                    <label class="check"><input type="checkbox" name="languages[]" value="<?= e($l) ?>" <?= in_array($l, $t['languages'], true) ? 'checked' : '' ?>> <?= e(App\Services\Countries::languageName($l)) ?></label>
                  <?php endforeach; ?></div></fieldset>
                <label class="switch"><input type="checkbox" name="is_recommended" value="1" <?= $t['is_recommended'] ? 'checked' : '' ?>> Recommended</label>
                <div><button class="btn btn-primary btn-sm" type="submit">Save</button></div>
              </form>
            </details>
          </td>
          <td class="small"><?= e(implode(', ', array_map(fn ($l) => App\Services\Countries::languageName($l), $t['languages']))) ?></td>
          <td><?= (int) ($usage[$t['id']] ?? 0) ?></td>
          <td><?= $t['is_active'] ? '<span class="badge badge-success badge-dot">Available</span>' : '<span class="badge badge-dot">Hidden</span>' ?></td>
          <td style="text-align:right">
            <div class="row" style="justify-content:flex-end;gap:8px;flex-wrap:nowrap">
              <button class="btn btn-secondary btn-sm" type="button" data-preview="<?= e($t['slug']) ?>" data-name="<?= e($t['name']) ?>" data-langs="<?= e(implode(',', $t['languages'])) ?>"><?= icon('zoom-in', 15) ?> Preview</button>
              <form method="post" action="<?= e(url('/admin/templates/' . $t['id'] . '/toggle')) ?>" data-no-busy>
                <?= csrf_field() ?>
                <button class="btn btn-secondary btn-sm" type="submit"><?= icon($t['is_active'] ? 'eye-off' : 'eye', 15) ?> <?= $t['is_active'] ? 'Hide' : 'Make available' ?></button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
<?php endforeach; ?>

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
  <div class="dialog-actions"><button class="btn btn-secondary" type="button" data-close>Close</button></div>
</dialog>
<script src="<?= e(asset('js/gallery.js')) ?>" defer></script>
