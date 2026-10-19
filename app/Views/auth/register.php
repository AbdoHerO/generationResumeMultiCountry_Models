<?php /** @var bool $open */ ?>
<h1>Create your account</h1>
<p class="lead">Free, private, and ready in under a minute.</p>
<?php if (!$open): ?>
  <div class="alert alert-warning" role="alert"><?= icon('info') ?><span>New registrations are closed at the moment. If you already have an account, <a href="<?= e(url('/login')) ?>">sign in</a>.</span></div>
<?php else: ?>
<form method="post" action="<?= e(url('/register')) ?>" novalidate>
  <?= csrf_field() ?>
  <?php foreach ([['name', 'Full name', 'text', 'name', 'e.g. Camille Laurent', 120], ['email', 'E-mail address', 'email', 'email', 'you@example.com', 190]] as [$n, $l, $t, $ac, $ph, $max]): $err = field_error($n); ?>
    <div class="field">
      <label class="label" for="<?= $n ?>"><?= $l ?></label>
      <input class="input" type="<?= $t ?>" id="<?= $n ?>" name="<?= $n ?>" value="<?= e(old($n)) ?>" autocomplete="<?= $ac ?>" placeholder="<?= e($ph) ?>" required maxlength="<?= $max ?>"
        <?= $err ? 'aria-invalid="true" aria-describedby="' . $n . '-err"' : '' ?>>
      <?php if ($err): ?><span class="error-text" id="<?= $n ?>-err"><?= e($err) ?></span><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php $err = field_error('password'); ?>
  <div class="field">
    <label class="label" for="password">Password</label>
    <div class="password-wrap">
      <input class="input" type="password" id="password" name="password" autocomplete="new-password" required minlength="8" data-strength="pw-strength" aria-describedby="pw-hint<?= $err ? ' password-err' : '' ?>" <?= $err ? 'aria-invalid="true"' : '' ?>>
      <button class="btn btn-ghost btn-icon btn-sm" type="button" data-toggle-password="password" aria-label="Show password" aria-pressed="false"><?= icon('eye') ?></button>
    </div>
    <div class="strength" id="pw-strength" aria-hidden="true"><span></span></div>
    <span class="hint" id="pw-hint">At least 8 characters, with a letter and a number. <span id="pw-strength-label"></span></span>
    <?php if ($err): ?><span class="error-text" id="password-err"><?= e($err) ?></span><?php endif; ?>
  </div>
  <?php $err = field_error('password_confirmation'); ?>
  <div class="field">
    <label class="label" for="password_confirmation">Confirm password</label>
    <input class="input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required <?= $err ? 'aria-invalid="true"' : '' ?>>
    <?php if ($err): ?><span class="error-text"><?= e($err) ?></span><?php endif; ?>
  </div>
  <div class="field">
    <label class="check"><input type="checkbox" name="terms" value="1" required> <span>I understand how my data is handled (<a href="<?= e(url('/privacy')) ?>" target="_blank" rel="noopener">privacy &amp; security</a>).</span></label>
    <?php if ($e = field_error('terms')): ?><span class="error-text"><?= e($e) ?></span><?php endif; ?>
  </div>
  <button class="btn btn-primary btn-lg btn-block" type="submit" style="margin-top:24px">Create my account</button>
</form>
<?php endif; ?>
<p class="auth-foot">Already have an account? <a href="<?= e(url('/login')) ?>">Sign in</a></p>
