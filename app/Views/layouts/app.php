<?php
/** Signed-in area with sidebar. $nav marks the current item. */
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$cur = fn (string $p) => str_starts_with($path, url($p)) ? ' aria-current="page"' : '';
?>
<!doctype html>
<html lang="en">
<head><?= App\Core\View::partial('partials/head', ['title' => $title ?? '']) ?></head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<div class="app-shell">
  <aside class="sidebar" aria-label="Main navigation">
    <?= App\Core\View::partial('partials/brand') ?>
    <nav class="side-nav">
      <a href="<?= e(url('/dashboard')) ?>"<?= $cur('/dashboard') ?>><?= icon('layout-dashboard') ?> My CVs</a>
      <a href="<?= e(url('/profile')) ?>"<?= $cur('/profile') ?>><?= icon('circle-user-round') ?> My profile</a>
      <a href="<?= e(url('/cvs/new')) ?>"<?= $cur('/cvs/new') ?>><?= icon('file-plus') ?> New CV</a>
      <a href="<?= e(url('/templates')) ?>"><?= icon('layout-template') ?> Templates</a>
      <a href="<?= e(url('/account')) ?>"<?= $cur('/account') ?>><?= icon('user') ?> Account</a>
      <?php if (is_admin()): ?>
        <div class="side-label">Administration</div>
        <a href="<?= e(url('/admin')) ?>"><?= icon('shield') ?> Admin dashboard</a>
      <?php endif; ?>
    </nav>
    <?= App\Core\View::partial('partials/user-menu') ?>
  </aside>
  <div class="backdrop"></div>
  <div class="main">
    <header class="topbar">
      <button class="btn btn-ghost btn-icon" type="button" data-sidebar-toggle aria-expanded="false" aria-label="Open navigation"><?= icon('menu') ?></button>
      <?= App\Core\View::partial('partials/brand') ?>
    </header>
    <main class="page" id="main"><?= $content ?></main>
  </div>
</div>
<?= App\Core\View::partial('partials/flash') ?>
<?php foreach (($scripts ?? []) as $s): ?><script src="<?= e(asset('js/' . $s)) ?>" defer></script><?php endforeach; ?>
</body>
</html>
