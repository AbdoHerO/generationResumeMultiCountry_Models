<?php /** @var array<string, mixed> $settings @var array<string, mixed> $ai */ ?>
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

<section class="card card-pad" id="ai" style="max-width:720px;margin-top:24px" aria-labelledby="h-ai">
  <div class="row-between">
    <h2 id="h-ai" style="margin:0;font-size:1.1rem"><?= icon('sparkles', 18) ?> CV import with AI (Groq)</h2>
    <?= $ai['keyMask'] !== null && $ai['enabled'] ? '<span class="badge badge-success badge-dot">Active</span>' : '<span class="badge badge-dot">Not active</span>' ?>
  </div>
  <p class="small muted">Users import a PDF CV; its text is structured by the model and translated into French, English and Chinese. The token is stored encrypted in the database and read again on every call: a new token takes effect immediately, without a restart.</p>
  <form method="post" action="<?= e(url('/admin/settings/ai')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <div class="field"><label class="switch"><input type="checkbox" name="ai_import_enabled" value="1" <?= $ai['enabled'] ? 'checked' : '' ?>> Allow users to import a PDF CV</label></div>
    <div class="field"><label class="label" for="ai_api_key">Groq API token</label>
      <input class="input" type="password" id="ai_api_key" name="ai_api_key" placeholder="<?= $ai['keyMask'] !== null ? 'Current: ' . e($ai['keyMask']) . ' — leave empty to keep it' : 'gsk_…' ?>" autocomplete="new-password" spellcheck="false" <?= field_error('ai_api_key') ? 'aria-invalid="true"' : '' ?>>
      <span class="hint"><?= match ($ai['keySource']) {
          'admin' => 'A token set here is in use (' . e((string) $ai['keyMask']) . ').',
          'env' => 'No token set here: the GROQ_API_KEY of the server .env is used as a fallback.',
          default => 'No token yet. Create one at console.groq.com → API Keys.',
      } ?> It is never displayed again.</span>
      <?php if ($e = field_error('ai_api_key')): ?><span class="error-text"><?= e($e) ?></span><?php endif; ?>
      <?php if ($ai['keySource'] === 'admin'): ?><label class="check" style="margin-top:6px"><input type="checkbox" name="ai_remove_key" value="1"> Remove the stored token</label><?php endif; ?>
    </div>
    <div class="form-grid" style="margin-top:16px">
      <div class="field"><label class="label" for="ai_model">Model</label>
        <input class="input" id="ai_model" name="ai_model" value="<?= e(old('ai_model', (string) $ai['model'])) ?>" maxlength="100" required spellcheck="false" <?= field_error('ai_model') ? 'aria-invalid="true"' : '' ?>>
        <?php if ($e = field_error('ai_model')): ?><span class="error-text"><?= e($e) ?></span><?php endif; ?></div>
      <div class="field"><label class="label" for="ai_reasoning_effort">Reasoning</label>
        <select class="select" id="ai_reasoning_effort" name="ai_reasoning_effort">
          <option value="none" <?= $ai['reasoning'] === 'none' ? 'selected' : '' ?>>Off (faster, recommended)</option>
          <option value="default" <?= $ai['reasoning'] === 'default' ? 'selected' : '' ?>>On (slower, sometimes more accurate)</option>
        </select></div>
    </div>
    <dl class="kv small" style="margin-top:16px"><dt>API address</dt><dd><code><?= e((string) $ai['url']) ?></code> <span class="muted">(GROQ_API_URL in .env)</span></dd></dl>
    <div class="row" style="justify-content:flex-end;margin-top:20px"><button class="btn btn-primary" type="submit">Save AI settings</button></div>
  </form>
  <form method="post" action="<?= e(url('/admin/settings/ai/test')) ?>" style="margin-top:8px">
    <?= csrf_field() ?>
    <button class="btn btn-secondary btn-sm" type="submit" <?= $ai['keyMask'] === null ? 'disabled' : '' ?>><?= icon('activity', 15) ?> Test the connection</button>
  </form>
</section>
