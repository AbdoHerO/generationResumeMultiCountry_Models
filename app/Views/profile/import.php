<?php /** @var bool $aiAvailable @var bool $hasProfile @var list<array<string, mixed>> $recent @var int $maxMb */ ?>
<div class="page-head">
  <div>
    <a class="small" href="<?= e(url('/profile')) ?>"><?= icon('arrow-left', 14) ?> My profile</a>
    <h1>Import a CV from PDF</h1>
    <p>We read your PDF, structure it with AI, and prepare French, English and Chinese versions. You review and correct everything before it is saved to your profile.</p>
  </div>
</div>

<?php if (!$aiAvailable): ?>
  <div class="alert alert-warning" style="max-width:760px"><?= icon('triangle-alert') ?><span>The CV import is not available yet: an administrator must configure the AI service. You can still <a href="<?= e(url('/profile')) ?>">fill in your profile by hand</a>.</span></div>
<?php else: ?>
<div class="import-grid">
  <section class="card card-pad" aria-labelledby="h-upload">
    <h2 id="h-upload" style="font-size:1.05rem">1. Choose your CV</h2>
    <form id="import-form" data-api="<?= e(url('/api/profile/import')) ?>" data-max="<?= $maxMb * 1048576 ?>">
      <label class="dropzone" id="dropzone" for="pdf">
        <span class="ficon"><?= icon('file-text', 26) ?></span>
        <strong id="drop-title">Drop a PDF here or click to choose</strong>
        <span class="small muted">PDF exported from Word, Google Docs, LinkedIn… up to <?= $maxMb ?> MB. Scanned images are not supported.</span>
        <input type="file" id="pdf" name="pdf" accept="application/pdf,.pdf" class="sr-only">
      </label>
      <?php if ($hasProfile): ?>
        <p class="small muted" style="margin:12px 0 0"><?= icon('info', 14) ?> You already have a profile. It is replaced only if you choose “Save to my profile” after reviewing.</p>
      <?php endif; ?>
      <div class="row" style="justify-content:flex-end;margin-top:16px">
        <button class="btn btn-primary" type="submit" id="import-start" disabled><?= icon('sparkles', 16) ?> Read my CV</button>
      </div>
    </form>
  </section>

  <section class="card card-pad" aria-labelledby="h-progress">
    <h2 id="h-progress" style="font-size:1.05rem">2. Progress</h2>
    <ol class="steps-list" id="import-steps" aria-live="polite">
      <li data-step="extract"><span class="st-ico"></span><span><strong>Reading and structuring</strong><span class="small muted">Text extraction, then the AI organises your sections.</span></span></li>
      <li data-step="fr"><span class="st-ico"></span><span><strong>Français</strong><span class="small muted">French version</span></span></li>
      <li data-step="en"><span class="st-ico"></span><span><strong>English</strong><span class="small muted">English version</span></span></li>
      <li data-step="zh"><span class="st-ico"></span><span><strong>简体中文</strong><span class="small muted">Chinese version</span></span></li>
    </ol>
    <p class="small muted" id="import-elapsed" aria-live="off" hidden></p>
    <div class="alert alert-danger" id="import-error" hidden><?= icon('triangle-alert') ?><span></span></div>
    <div class="row" style="justify-content:flex-end;margin-top:12px">
      <button class="btn btn-secondary" type="button" id="import-retry" hidden><?= icon('refresh-cw', 16) ?> Retry</button>
    </div>
    <p class="xs muted mb-0" style="margin-top:12px">Usually 1 to 3 minutes: the AI service limits how much it writes per minute, so the page sometimes waits and then continues by itself. You can leave and come back: the import continues from <em>Recent imports</em>.</p>
    <p class="xs muted mb-0" style="margin-top:8px">Your PDF is processed and then deleted; it is never stored. Only its text is sent to the AI service (Groq), to build your profile.</p>
  </section>
</div>
<?php endif; ?>

<?php if ($recent !== []): ?>
  <section class="card" style="margin-top:24px;max-width:1100px">
    <div class="card-header"><h2 style="margin:0;font-size:1rem">Recent imports</h2><span class="xs muted">Kept 7 days</span></div>
    <div class="table-wrap"><table class="table"><tbody>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td class="small"><?= e($r['file_name'] ?: 'CV.pdf') ?></td>
          <td class="small muted nowrap"><?= e(ui_ago($r['created_at'])) ?></td>
          <td><?= ['ready' => '<span class="badge badge-primary">Ready to review</span>', 'applied' => '<span class="badge badge-success">Saved to profile</span>', 'failed' => '<span class="badge badge-danger">Failed</span>', 'processing' => '<span class="badge badge-warning">Incomplete</span>'][$r['status']] ?? '' ?></td>
          <td style="text-align:right"><?php if ($r['status'] === 'ready'): ?><a class="btn btn-secondary btn-sm" href="<?= e(url('/profile/imports/' . $r['id'])) ?>">Review</a><?php elseif ($r['status'] === 'processing'): ?><button class="btn btn-secondary btn-sm" type="button" data-resume-import="<?= (int) $r['id'] ?>">Continue</button><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </section>
<?php endif; ?>
<script src="<?= e(asset('js/import.js')) ?>" defer></script>
