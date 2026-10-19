<?php
/**
 * The global profile (mode "profile") or a PDF import under review (mode "import"), edited with the
 * CV editor without preview, in three language versions.
 * @var string $mode @var array<string, mixed> $state @var array<string, bool> $filled @var bool $aiAvailable
 */
$isImport = $mode === 'import';
$api = $isImport ? url('/api/profile/imports/' . $import['id']) : url('/api/profile');
?>
<div class="editor no-preview" id="editor" data-api="<?= e($api) ?>" data-dashboard="<?= e(url('/dashboard')) ?>">
  <header class="ed-top">
    <a class="btn btn-ghost btn-icon" href="<?= e(url($isImport ? '/profile/import' : '/dashboard')) ?>" aria-label="Back" title="Back"><?= icon('arrow-left') ?></a>
    <div class="ed-title grow">
      <span class="ed-title-btn truncate" id="ed-title" style="cursor:default"><?= $isImport ? 'Review your imported CV' : 'My profile' ?></span>
      <div class="ed-status" id="ed-status" role="status" aria-live="polite"><span class="dot"></span><span class="txt">All changes saved</span></div>
    </div>
    <div class="ed-actions">
      <div class="ed-group" role="group" aria-label="History">
        <button type="button" class="btn btn-ghost btn-icon" id="ed-undo" aria-label="Undo (Ctrl+Z)" title="Undo (Ctrl+Z)" disabled><?= icon('undo-2') ?></button>
        <button type="button" class="btn btn-ghost btn-icon" id="ed-redo" aria-label="Redo (Ctrl+Y)" title="Redo (Ctrl+Y)" disabled><?= icon('redo-2') ?></button>
      </div>
      <button type="button" class="btn btn-secondary hide-sm" id="ed-save" title="Save now (Ctrl+S)"><?= icon('save', 16) ?> Save</button>
      <?php if ($isImport): ?>
        <button type="button" class="btn btn-primary" id="ed-apply"><?= icon('check', 16) ?> <span class="hide-xs">Save to my profile</span></button>
      <?php else: ?>
        <?php if ($aiAvailable): ?><a class="btn btn-secondary hide-sm" href="<?= e(url('/profile/import')) ?>"><?= icon('upload', 16) ?> Import a PDF</a><?php endif; ?>
        <a class="btn btn-primary" href="<?= e(url('/cvs/new')) ?>"><?= icon('file-plus', 16) ?> <span class="hide-xs">Create a CV</span></a>
      <?php endif; ?>
    </div>
  </header>

  <div class="ed-body">
    <aside class="ed-panel" aria-label="<?= $isImport ? 'Imported CV' : 'Profile' ?> editor">
      <div class="profile-bar">
        <div class="seg" role="group" aria-label="Language version">
          <?php foreach (App\Services\Profile\ProfileService::LANGUAGE_NAMES as $code => $name): ?>
            <button type="button" data-profile-lang="<?= e($code) ?>" aria-pressed="false"><?= e($name) ?><?= empty($filled[$code]) ? ' <span class="badge" style="height:18px">empty</span>' : '' ?></button>
          <?php endforeach; ?>
        </div>
        <p class="small muted mb-0">
          <?= $isImport
            ? 'Check every language version and correct anything before saving it to your profile. Nothing is saved to your profile until you click “Save to my profile”.'
            : 'One master copy in three languages. New CVs in French, English or Chinese can start from it; you can still edit each CV.' ?>
        </p>
      </div>
      <div class="ed-progress" id="ed-progress" aria-live="polite"></div>
      <form class="ed-pane" id="editor-form" novalidate autocomplete="off" onsubmit="return false"></form>
    </aside>
  </div>
</div>
<script type="application/json" id="editor-state"><?= json_script($state) ?></script>
