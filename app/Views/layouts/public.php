<?php /** Public pages: landing, gallery, privacy, errors. */ $user = auth_user(); ?>
<!doctype html>
<html lang="en">
<head><?= App\Core\View::partial('partials/head', ['title' => $title ?? '']) ?></head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
  <nav class="nav container" aria-label="Main">
    <?= App\Core\View::partial('partials/brand') ?>
    <div class="nav-links">
      <a href="<?= e(url('/templates')) ?>">Templates</a>
      <a href="<?= e(url('/#how')) ?>">How it works</a>
      <a href="<?= e(url('/#features')) ?>">Features</a>
      <a href="<?= e(url('/#faq')) ?>">FAQ</a>
    </div>
    <div class="nav-actions">
      <?php if ($user): ?>
        <a class="btn btn-primary" href="<?= e(url($user['role'] === 'admin' ? '/admin' : '/dashboard')) ?>"><?= icon('layout-dashboard') ?> <span>My space</span></a>
      <?php else: ?>
        <a class="btn btn-ghost hide-sm" href="<?= e(url('/login')) ?>">Sign in</a>
        <a class="btn btn-primary hide-sm" href="<?= e(url('/register')) ?>">Create my CV</a>
      <?php endif; ?>
    </div>
    <button class="btn btn-ghost btn-icon nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-label="Open menu"><?= icon('menu') ?></button>
  </nav>
</header>
<main id="main"><?= $content ?></main>
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <?= App\Core\View::partial('partials/brand') ?>
        <p class="muted small" style="margin-top:12px;max-width:28em">Professional CVs and resumes for France, Morocco, the United States, Canada and China — written by you, typeset for each country, exported as true A4 PDFs.</p>
      </div>
      <div><h4>Product</h4><ul>
        <li><a href="<?= e(url('/templates')) ?>">Templates</a></li>
        <li><a href="<?= e(url('/#how')) ?>">How it works</a></li>
        <li><a href="<?= e(url('/#faq')) ?>">FAQ</a></li></ul></div>
      <div><h4>Countries</h4><ul>
        <?php foreach (App\Services\Countries::all(true) as $code => $c): ?>
          <li><a href="<?= e(url('/templates?country=' . $code)) ?>"><?= e($c['name']) ?></a></li>
        <?php endforeach; ?></ul></div>
      <div><h4>Account</h4><ul>
        <?php if ($user): ?><li><a href="<?= e(url('/dashboard')) ?>">My CVs</a></li><li><a href="<?= e(url('/account')) ?>">Account</a></li>
        <?php else: ?><li><a href="<?= e(url('/login')) ?>">Sign in</a></li><li><a href="<?= e(url('/register')) ?>">Create an account</a></li><?php endif; ?>
        <li><a href="<?= e(url('/privacy')) ?>">Privacy &amp; security</a></li></ul></div>
    </div>
    <div class="footer-bottom row-between"><span>© <?= date('Y') ?> <?= e(app_name()) ?></span><span>Your CVs stay private to your account.</span></div>
  </div>
</footer>
<?= App\Core\View::partial('partials/flash') ?>
</body>
</html>
