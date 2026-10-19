<?php
/**
 * @var list<array<string, mixed>> $cvs @var int $total @var int $completed @var list<array<string, mixed>> $recent
 * @var array<string, string> $filters @var bool $filtered @var array<string, array<string, mixed>> $countries
 * @var list<array<string, mixed>> $templates
 */
$user = auth_user();
$first = explode(' ', trim((string) $user['name']))[0] ?? '';
?>
<div class="page-head">
  <div>
    <h1>Welcome<?= $first !== '' ? ', ' . e($first) : '' ?></h1>
    <p><?= $total === 0 ? 'Create your first CV — it only takes a few minutes.' : 'Pick up where you left off, or start a new CV.' ?></p>
  </div>
  <a class="btn btn-primary btn-lg" href="<?= e(url('/cvs/new')) ?>"><?= icon('plus') ?> Create a new CV</a>
</div>

<?php if ($total === 0): ?>
  <div class="card empty">
    <div class="ficon"><?= icon('file-text', 30) ?></div>
    <h2>Your CV library is empty</h2>
    <p>Choose the country you are applying to, pick a template designed for it, and fill in your details with a live preview. Your work saves automatically.</p>
    <div class="row" style="justify-content:center">
      <a class="btn btn-primary" href="<?= e(url('/cvs/new')) ?>"><?= icon('file-plus') ?> Create my first CV</a>
      <a class="btn btn-secondary" href="<?= e(url('/templates')) ?>"><?= icon('layout-template') ?> Browse templates</a>
    </div>
  </div>
<?php else: ?>
  <div class="stats">
    <div class="card stat"><div class="stat-label"><?= icon('files', 16) ?> CVs</div><div class="stat-value"><?= $total ?></div></div>
    <div class="card stat"><div class="stat-label"><?= icon('circle-check', 16) ?> Completed</div><div class="stat-value"><?= $completed ?></div></div>
    <div class="card stat"><div class="stat-label"><?= icon('pencil', 16) ?> Drafts</div><div class="stat-value"><?= $total - $completed ?></div></div>
    <div class="card stat"><div class="stat-label"><?= icon('clock', 16) ?> Last edited</div><div class="stat-value" style="font-size:1.05rem;margin-top:10px"><?= e(ui_ago($recent[0]['updated_at'] ?? null)) ?></div>
      <?php if (!empty($recent[0])): ?><a class="xs truncate" style="display:block" href="<?= e(url('/cvs/' . $recent[0]['id'] . '/edit')) ?>"><?= e($recent[0]['title']) ?></a><?php endif; ?></div>
  </div>

  <form class="filters" method="get" action="<?= e(url('/dashboard')) ?>" role="search" id="cv-filters" data-no-busy>
    <label class="sr-only" for="q">Search your CVs</label>
    <input class="input" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Search by name…">
    <label class="sr-only" for="f-country">Country</label>
    <select class="select" id="f-country" name="country">
      <option value="">All countries</option>
      <?php foreach ($countries as $code => $c): ?><option value="<?= e($code) ?>" <?= $filters['country'] === $code ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <label class="sr-only" for="f-template">Template</label>
    <select class="select" id="f-template" name="template">
      <option value="">All templates</option>
      <?php foreach ($templates as $t): ?><option value="<?= e($t['slug']) ?>" <?= $filters['template'] === $t['slug'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?>
    </select>
    <label class="sr-only" for="f-status">Status</label>
    <select class="select" id="f-status" name="status">
      <option value="">Any status</option>
      <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Drafts</option>
      <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
    </select>
    <label class="sr-only" for="f-sort">Sort by</label>
    <select class="select" id="f-sort" name="sort">
      <option value="updated" <?= $filters['sort'] === 'updated' ? 'selected' : '' ?>>Last modified</option>
      <option value="created" <?= $filters['sort'] === 'created' ? 'selected' : '' ?>>Newest first</option>
      <option value="oldest" <?= $filters['sort'] === 'oldest' ? 'selected' : '' ?>>Least recently modified</option>
      <option value="title" <?= $filters['sort'] === 'title' ? 'selected' : '' ?>>Name (A–Z)</option>
    </select>
    <noscript><button class="btn btn-secondary" type="submit">Apply</button></noscript>
    <?php if ($filtered): ?><a class="btn btn-ghost" href="<?= e(url('/dashboard')) ?>"><?= icon('x', 16) ?> Clear</a><?php endif; ?>
  </form>

  <?php if ($cvs === []): ?>
    <div class="card empty">
      <div class="ficon"><?= icon('search', 28) ?></div>
      <h2>No CV matches these filters</h2>
      <p>Try another search term or clear the filters.</p>
      <a class="btn btn-secondary" href="<?= e(url('/dashboard')) ?>">Clear filters</a>
    </div>
  <?php else: ?>
    <div class="cv-grid" id="cv-grid">
      <?php foreach ($cvs as $cv): $id = (int) $cv['id']; ?>
        <article class="card cv-card" data-cv-id="<?= $id ?>" data-title="<?= e($cv['title']) ?>">
          <a class="tpl-thumb" href="<?= e(url('/cvs/' . $id . '/edit')) ?>" aria-label="Edit <?= e($cv['title']) ?>" tabindex="-1">
            <div class="skeleton"></div>
            <iframe src="<?= e(url('/cvs/' . $id . '/render')) ?>?v=<?= (int) $cv['version'] ?>" title="" loading="lazy" tabindex="-1"></iframe>
          </a>
          <div class="cv-meta">
            <a class="cv-title truncate" href="<?= e(url('/cvs/' . $id . '/edit')) ?>" data-cv-title><?= e($cv['title']) ?></a>
            <div class="cv-sub"><?= flag($cv['country_code']) ?> <?= e(App\Services\Countries::name($cv['country_code'])) ?> · <?= e($cv['template_name']) ?></div>
            <div class="cv-sub">
              <?php if ($cv['status'] === 'completed'): ?><span class="badge badge-success badge-dot">Completed</span><?php else: ?><span class="badge badge-warning badge-dot">Draft</span><?php endif; ?>
              <span title="<?= e(ui_date($cv['updated_at'], true)) ?> UTC">Edited <?= e(ui_ago($cv['updated_at'])) ?></span>
            </div>
          </div>
          <div class="cv-actions">
            <a class="btn btn-secondary btn-sm" href="<?= e(url('/cvs/' . $id . '/edit')) ?>"><?= icon('pencil', 15) ?> Edit</a>
            <a class="btn btn-secondary btn-sm btn-icon" href="<?= e(url('/cvs/' . $id . '/download')) ?>" data-download aria-label="Download <?= e($cv['title']) ?> as PDF" title="Download PDF"><?= icon('download', 16) ?></a>
            <div class="menu-wrap">
              <button class="btn btn-ghost btn-sm btn-icon" type="button" data-menu-button aria-expanded="false" aria-controls="m-<?= $id ?>" aria-label="More actions for <?= e($cv['title']) ?>"><?= icon('ellipsis-vertical', 16) ?></button>
              <div class="menu" id="m-<?= $id ?>" hidden>
                <button type="button" data-action="rename"><?= icon('square-pen') ?> Rename</button>
                <button type="button" data-action="duplicate"><?= icon('copy') ?> Duplicate</button>
                <a href="<?= e(url('/cvs/' . $id . '/download')) ?>" data-download><?= icon('download') ?> Download PDF</a>
                <a href="<?= e(url('/cvs/' . $id . '/download?inline=1')) ?>" target="_blank" rel="noopener"><?= icon('external-link') ?> Open PDF</a>
                <hr>
                <button type="button" class="danger" data-action="delete"><?= icon('trash-2') ?> Delete</button>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
      <a class="new-card" href="<?= e(url('/cvs/new')) ?>"><span class="ficon"><?= icon('plus', 22) ?></span><strong>New CV</strong><span class="small muted">Another country or role</span></a>
    </div>
  <?php endif; ?>
<?php endif; ?>
<script src="<?= e(asset('js/dashboard.js')) ?>" defer></script>
