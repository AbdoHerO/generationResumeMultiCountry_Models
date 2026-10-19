<?php
/** Administration area. */
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$cur = fn (string $p) => ($path === url($p) || ($p !== '/admin' && str_starts_with($path, url($p)))) ? ' aria-current="page"' : '';
?>
<!doctype html>
<html lang="en">
<head><?= App\Core\View::partial('partials/head', ['title' => $title ?? '']) ?></head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<div class="app-shell">
  <aside class="sidebar" aria-label="Administration navigation">
    <?= App\Core\View::partial('partials/brand') ?>
    <nav class="side-nav">
      <div class="side-label" style="padding-top:4px">Administration</div>
      <a href="<?= e(url('/admin')) ?>"<?= $cur('/admin') ?>><?= icon('chart-column') ?> Overview</a>
      <a href="<?= e(url('/admin/users')) ?>"<?= $cur('/admin/users') ?>><?= icon('users') ?> Users</a>
      <a href="<?= e(url('/admin/templates')) ?>"<?= $cur('/admin/templates') ?>><?= icon('layout-template') ?> Templates</a>
      <a href="<?= e(url('/admin/countries')) ?>"<?= $cur('/admin/countries') ?>><?= icon('globe') ?> Countries &amp; languages</a>
      <a href="<?= e(url('/admin/settings')) ?>"<?= $cur('/admin/settings') ?>><?= icon('settings') ?> Settings</a>
      <a href="<?= e(url('/admin/audit')) ?>"<?= $cur('/admin/audit') ?>><?= icon('activity') ?> Activity log</a>
      <div class="side-label">My space</div>
      <a href="<?= e(url('/dashboard')) ?>"><?= icon('layout-dashboard') ?> My CVs</a>
    </nav>
    <?= App\Core\View::partial('partials/user-menu') ?>
  </aside>
  <div class="backdrop"></div>
  <div class="main">
    <header class="topbar">
      <button class="btn btn-ghost btn-icon" type="button" data-sidebar-toggle aria-expanded="false" aria-label="Open navigation"><?= icon('menu') ?></button>
      <?= App\Core\View::partial('partials/brand') ?><span class="badge badge-primary">Admin</span>
    </header>
    <main class="page page-wide" id="main"><?= $content ?></main>
  </div>
</div>
<?= App\Core\View::partial('partials/flash') ?>
</body>
</html>
