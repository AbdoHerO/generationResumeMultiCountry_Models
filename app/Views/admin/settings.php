<?php /** @var array<string, mixed> $settings */ ?>
<div class="page-head"><div><h1>Application settings</h1><p>Basic settings. Secrets (database, mail) live in the server’s .env file, never here.</p></div></div>
<form class="card card-pad" method="post" action="<?= e(url('/admin/settings')) ?>" style="max-width:720px">
  <?= csrf_field() ?>
  <div class="field"><label class="label" for="app_name">Application name</label>
    <input class="input" id="app_name" name="app_name" value="<?= e(old('app_name', (string) $settings['app_name'])) ?>" maxlength="60" required <?= field_error('app_name') ? 'aria-invalid="true"' : '' ?>>
    <?php if ($e = field_error('app_name')): ?><span class="error-text"><?= e($e) ?></span><?php endif; ?></div>
  <div class="field"><label class="label" for="support_email">Support e-mail <span class="opt">(optional, shown on the password-reset page)</span></label>
    <input class="input" type="email" id="support_email" name="support_email" value="<?= e(old('support_email', (string) $settings['support_email'])) ?>" <?= field_error('support_email') ? 'aria-invalid="true"' : '' ?>>
    <?php if ($e = field_error('support_email')): ?><span class="error-text"><?= e($e) ?></span><?php endif; ?></div>
  <div class="field"><label class="label" for="max_cvs_per_user">Maximum CVs per user</label>
    <input class="input" type="number" id="max_cvs_per_user" name="max_cvs_per_user" min="1" max="500" value="<?= (int) old('max_cvs_per_user', (string) $settings['max_cvs_per_user']) ?>" style="max-width:160px" <?= field_error('max_cvs_per_user') ? 'aria-invalid="true"' : '' ?>>
    <?php if ($e = field_error('max_cvs_per_user')): ?><span class="error-text"><?= e($e) ?></span><?php endif; ?></div>
  <div class="field"><label class="switch"><input type="checkbox" name="registration_open" value="1" <?= $settings['registration_open'] ? 'checked' : '' ?>> Allow new registrations</label></div>
  <hr class="divider">
  <dl class="kv small">
    <dt>Environment</dt><dd><?= e((string) config('app.env')) ?></dd>
    <dt>Password reset e-mails</dt><dd><?= App\Services\Mailer::enabled() ? 'Enabled (' . e((string) config('app.mail.mailer')) . ')' : 'Disabled — set MAIL_MAILER in .env' ?></dd>
    <dt>PHP</dt><dd><?= e(PHP_VERSION) ?></dd>
  </dl>
  <div class="row" style="justify-content:flex-end;margin-top:20px"><button class="btn btn-primary" type="submit">Save settings</button></div>
</form>
