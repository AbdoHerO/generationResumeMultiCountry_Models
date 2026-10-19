<h1>Choose a new password</h1>
<p class="lead">Your account uses a temporary password. Choose your own before continuing.</p>
<div class="alert alert-warning" style="margin-bottom:20px"><?= icon('shield') ?><span>This protects the account: the initial password must not stay in use.</span></div>
<form method="post" action="<?= e(url('/account/change-password')) ?>">
  <?= csrf_field() ?>
  <div class="field"><label class="label" for="current_password">Current (temporary) password</label>
    <input class="input" type="password" id="current_password" name="current_password" autocomplete="current-password" required <?= field_error('current_password') ? 'aria-invalid="true"' : '' ?>>
    <?php if ($e = field_error('current_password')): ?><span class="error-text"><?= e($e) ?></span><?php endif; ?></div>
  <div class="field"><label class="label" for="password">New password</label>
    <input class="input" type="password" id="password" name="password" autocomplete="new-password" required data-strength="pw-strength" <?= field_error('password') ? 'aria-invalid="true"' : '' ?>>
    <div class="strength" id="pw-strength" aria-hidden="true"><span></span></div><span class="hint">At least 8 characters, with a letter and a number. <span id="pw-strength-label"></span></span>
    <?php if ($e = field_error('password')): ?><span class="error-text"><?= e($e) ?></span><?php endif; ?></div>
  <div class="field"><label class="label" for="password_confirmation">Confirm the new password</label>
    <input class="input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
    <?php if ($e = field_error('password_confirmation')): ?><span class="error-text"><?= e($e) ?></span><?php endif; ?></div>
  <button class="btn btn-primary btn-lg btn-block" type="submit" style="margin-top:24px">Save and continue</button>
</form>
<form method="post" action="<?= e(url('/logout')) ?>" class="auth-foot"><?= csrf_field() ?><button class="link-btn" type="submit">Sign out</button></form>
