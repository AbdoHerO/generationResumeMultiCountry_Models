<?php /** @var bool $mailEnabled */ $err = field_error('email'); ?>
<h1>Welcome back</h1>
<p class="lead">Sign in to continue working on your CVs.</p>
<?php if ($err): ?><div class="alert alert-danger" role="alert" style="margin-bottom:16px"><?= icon('triangle-alert') ?><span><?= e($err) ?></span></div><?php endif; ?>
<form method="post" action="<?= e(url('/login')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="field">
    <label class="label" for="email">E-mail address</label>
    <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email" required autofocus <?= $err ? 'aria-invalid="true"' : '' ?>>
  </div>
  <div class="field">
    <div class="row-between"><label class="label" for="password">Password</label>
      <a class="small" href="<?= e(url('/forgot-password')) ?>">Forgot your password?</a></div>
    <div class="password-wrap">
      <input class="input" type="password" id="password" name="password" autocomplete="current-password" required>
      <button class="btn btn-ghost btn-icon btn-sm" type="button" data-toggle-password="password" aria-label="Show password" aria-pressed="false"><?= icon('eye') ?></button>
    </div>
  </div>
  <button class="btn btn-primary btn-lg btn-block" type="submit" style="margin-top:24px">Sign in</button>
</form>
<p class="auth-foot">New here? <a href="<?= e(url('/register')) ?>">Create a free account</a></p>
