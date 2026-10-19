<section class="container" style="padding:80px 0">
  <div class="card empty" style="max-width:560px;margin:0 auto">
    <div class="ficon"><?= icon('search', 28) ?></div>
    <p class="xs muted" style="margin-bottom:4px">Error 404</p>
    <h1 style="font-size:1.5rem">Page not found</h1>
    <p><?= e($message ?? '') ?></p>
    <div class="row" style="justify-content:center">
      <a class="btn btn-primary" href="<?= e(url(auth_user() ? '/dashboard' : '/')) ?>"><?= icon('house', 16) ?> <?= auth_user() ? 'Back to my CVs' : 'Back to home' ?></a>
    </div>
  </div>
</section>
