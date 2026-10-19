<?php $flash = App\Core\Session::pullFlash(); if ($flash): ?>
<script type="application/json" id="flash-data"><?= json_script($flash) ?></script>
<noscript><div class="container"><?php foreach ($flash as $f): ?><div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : ($f['type'] === 'success' ? 'success' : 'info') ?>" style="margin-top:12px"><?= e($f['message']) ?></div><?php endforeach; ?></div></noscript>
<?php endif; ?>
