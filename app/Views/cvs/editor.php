<?php
/** @var array<string, mixed> $cv @var array<string, mixed> $state */
$apiBase = url('/api/cvs/' . $cv['id']);
?>
<div class="editor" id="editor" data-api="<?= e($apiBase) ?>" data-download="<?= e(url('/cvs/' . $cv['id'] . '/download')) ?>" data-dashboard="<?= e(url('/dashboard')) ?>">
  <header class="ed-top">
    <a class="btn btn-ghost btn-icon" href="<?= e(url('/dashboard')) ?>" aria-label="Back to my CVs" title="Back to my CVs"><?= icon('arrow-left') ?></a>
    <div class="ed-title grow">
      <button type="button" class="ed-title-btn truncate" id="ed-title" title="Rename this CV"><?= e($cv['title']) ?></button>
      <div class="ed-status" id="ed-status" role="status" aria-live="polite"><span class="dot"></span><span class="txt">All changes saved</span></div>
    </div>
    <div class="ed-actions">
      <div class="ed-group" role="group" aria-label="History">
        <button type="button" class="btn btn-ghost btn-icon" id="ed-undo" aria-label="Undo (Ctrl+Z)" title="Undo (Ctrl+Z)" disabled><?= icon('undo-2') ?></button>
        <button type="button" class="btn btn-ghost btn-icon" id="ed-redo" aria-label="Redo (Ctrl+Y)" title="Redo (Ctrl+Y)" disabled><?= icon('redo-2') ?></button>
      </div>
      <button type="button" class="btn btn-secondary hide-sm" id="ed-save" title="Save now (Ctrl+S)"><?= icon('save', 16) ?> Save</button>
      <button type="button" class="btn btn-primary" id="ed-download"><?= icon('download', 16) ?> <span class="hide-xs">Download PDF</span></button>
      <div class="menu-wrap">
        <button type="button" class="btn btn-ghost btn-icon" data-menu-button aria-expanded="false" aria-controls="ed-menu" aria-label="More actions"><?= icon('ellipsis-vertical') ?></button>
        <div class="menu" id="ed-menu" hidden>
          <button type="button" data-action="rename"><?= icon('pencil') ?> Rename</button>
          <button type="button" data-action="duplicate"><?= icon('copy') ?> Duplicate</button>
          <button type="button" data-action="save" class="show-sm"><?= icon('save') ?> Save now</button>
          <a href="<?= e(url('/cvs/' . $cv['id'] . '/download?inline=1')) ?>" target="_blank" rel="noopener"><?= icon('external-link') ?> Open PDF in a new tab</a>
          <hr>
          <button type="button" data-action="delete" class="danger"><?= icon('trash-2') ?> Delete CV</button>
        </div>
      </div>
    </div>
  </header>

  <div class="ed-mobile-switch" role="tablist" aria-label="View">
    <button type="button" role="tab" aria-selected="true" data-view="edit"><?= icon('pencil', 16) ?> Edit</button>
    <button type="button" role="tab" aria-selected="false" data-view="preview"><?= icon('eye', 16) ?> Preview</button>
  </div>

  <div class="ed-body" data-view="edit">
    <aside class="ed-panel" aria-label="CV editor">
      <nav class="ed-tabs" role="tablist" aria-label="Editor sections">
        <button type="button" role="tab" id="tab-content" aria-controls="pane-content" aria-selected="true"><?= icon('file-text', 16) ?> Content</button>
        <button type="button" role="tab" id="tab-design" aria-controls="pane-design" aria-selected="false" tabindex="-1"><?= icon('palette', 16) ?> Design</button>
        <button type="button" role="tab" id="tab-template" aria-controls="pane-template" aria-selected="false" tabindex="-1"><?= icon('layout-template', 16) ?> Template</button>
      </nav>
      <div class="ed-progress" id="ed-progress" aria-live="polite"></div>
      <form class="ed-pane" id="editor-form" role="tabpanel" aria-labelledby="tab-content" novalidate autocomplete="off" onsubmit="return false"></form>
      <div class="ed-pane" id="pane-design" role="tabpanel" aria-labelledby="tab-design" hidden></div>
      <div class="ed-pane" id="pane-template" role="tabpanel" aria-labelledby="tab-template" hidden></div>
    </aside>

    <section class="ed-preview" aria-label="Live preview">
      <div class="ed-preview-bar">
        <div class="row" style="gap:6px">
          <button type="button" class="btn btn-ghost btn-icon btn-sm" id="zoom-out" aria-label="Zoom out"><?= icon('zoom-out', 16) ?></button>
          <button type="button" class="btn btn-ghost btn-sm" id="zoom-fit" title="Fit to width"><span id="zoom-label">100%</span></button>
          <button type="button" class="btn btn-ghost btn-icon btn-sm" id="zoom-in" aria-label="Zoom in"><?= icon('zoom-in', 16) ?></button>
        </div>
        <div class="row small muted" style="gap:10px">
          <label class="switch small"><input type="checkbox" id="page-guides" checked> Page breaks</label>
          <span id="page-count" class="badge" title="Estimated from the preview; the PDF is checked when you download">1 page</span>
        </div>
      </div>
      <div class="ed-stage" id="ed-stage">
        <div class="ed-paper" id="ed-paper">
          <iframe id="preview-frame" title="Preview of your CV" sandbox="allow-same-origin"></iframe>
          <div class="ed-guides" id="ed-guides" aria-hidden="true"></div>
        </div>
        <div class="ed-preview-loading" id="ed-preview-loading" hidden><span class="spinner"></span></div>
      </div>
    </section>
  </div>
</div>
<script type="application/json" id="editor-state"><?= json_script($state) ?></script>
