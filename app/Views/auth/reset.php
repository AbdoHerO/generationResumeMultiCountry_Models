<?php /** @var string $token */ ?>
<h1>Choose a new password</h1>
<p class="lead">Use at least 8 characters, with a letter and a number.</p>
<form method="post" action="<?= e(url('/reset-password')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <div class="field"><label class="label" for="password">New password</label>
    <input class="input" type="password" id="password" name="password" autocomplete="new-password" required data-strength="pw-strength" <?= field_error('password') ? 'aria-invalid="true"' : '' ?>>
    <div class="strength" id="pw-strength" aria-hidden="true"><span></span></div><span class="hint" id="pw-strength-label"></span>
    <?php if ($e = field_error('password')): ?><span class="error-text"><?= e($e) ?></span><?php endif; ?></div>
  <div class="field"><label class="label" for="password_confirmation">Confirm the new password</label>
    <input class="input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
    <?php if ($e = field_error('password_confirmation')): ?><span class="error-text"><?= e($e) ?></span><?php endif; ?></div>
  <button class="btn btn-primary btn-lg btn-block" type="submit" style="margin-top:24px">Save the new password</button>
</form>
