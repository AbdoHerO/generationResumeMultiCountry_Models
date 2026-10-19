<?php /** @var bool $mailEnabled */ ?>
<h1>Reset your password</h1>
<?php if (!$mailEnabled): ?>
  <p class="lead">Password reset by e-mail is not enabled on this installation.</p>
  <div class="alert alert-info"><?= icon('info') ?><span>Please contact the administrator<?php $support = (string) App\Models\Setting::get('support_email', ''); if ($support !== ''): ?> at <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a><?php endif; ?>.</span></div>
<?php else: ?>
  <p class="lead">Enter the e-mail address of your account and we will send you a link to choose a new password.</p>
  <form method="post" action="<?= e(url('/forgot-password')) ?>">
    <?= csrf_field() ?>
    <div class="field"><label class="label" for="email">E-mail address</label><input class="input" type="email" id="email" name="email" autocomplete="email" required autofocus></div>
    <button class="btn btn-primary btn-lg btn-block" type="submit" style="margin-top:24px">Send the reset link</button>
  </form>
<?php endif; ?>
<p class="auth-foot"><a href="<?= e(url('/login')) ?>"><?= icon('arrow-left', 14) ?> Back to sign in</a></p>
