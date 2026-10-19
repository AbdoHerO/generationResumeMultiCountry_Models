<?php /** Sign-in, registration and password pages. */ ?>
<!doctype html>
<html lang="en">
<head><?= App\Core\View::partial('partials/head', ['title' => $title ?? '']) ?></head>
<body>
<div class="auth-shell">
  <aside class="auth-side" aria-hidden="true">
    <a class="brand" href="<?= e(url('/')) ?>" tabindex="-1"><span class="brand-mark"><?= icon('file-text', 18) ?></span><span><?= e(app_name()) ?></span></a>
    <div>
      <h2>A CV that fits the country you are applying to.</h2>
      <p class="quote">25 professional templates for France, Morocco, the United States, Canada and China — with live preview, automatic saving and true A4 PDFs.</p>
    </div>
    <div class="auth-papers">
      <div class="paper paper-1"><iframe src="<?= e(url('/templates/fr-classic/preview')) ?>" title="" tabindex="-1" loading="lazy"></iframe></div>
      <div class="paper paper-2" style="right:10%"><iframe src="<?= e(url('/templates/us-technical/preview')) ?>" title="" tabindex="-1" loading="lazy"></iframe></div>
    </div>
  </aside>
  <main class="auth-main" id="main">
    <div class="auth-card">
      <div style="margin-bottom:28px"><?= App\Core\View::partial('partials/brand') ?></div>
      <?= $content ?>
    </div>
  </main>
</div>
<?= App\Core\View::partial('partials/flash') ?>
</body>
</html>
