<?php /** @var int $cvCount */ $u = auth_user(); ?>
<div class="page-head"><div><h1>Account</h1><p>Your profile, password and data.</p></div></div>

<div class="stack" style="max-width:760px">
  <section class="card" aria-labelledby="profile-h">
    <div class="card-header"><h2 id="profile-h" style="margin:0;font-size:1.05rem">Profile</h2></div>
    <form class="card-body" method="post" action="<?= e(url('/account/profile')) ?>">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="field"><label class="label" for="name">Name</label>
          <input class="input" id="name" name="name" value="<?= e(old('name', (string) $u['name'])) ?>" maxlength="120" required autocomplete="name" <?= field_error('name') ? 'aria-invalid="true"' : '' ?>>
          <?php if ($e = field_error('name')): ?><span class="error-text"><?= e($e) ?></span><?php endif; ?></div>
        <div class="field"><label class="label" for="email">E-mail address</label>
          <input class="input" id="email" value="<?= e($u['email']) ?>" disabled aria-describedby="email-hint"><span class="hint" id="email-hint">Your sign-in address.</span></div>
      </div>
      <dl class="kv small" style="margin:20px 0 0">
        <dt>Member since</dt><dd><?= e(ui_date($u['created_at'])) ?></dd>
        <dt>Last sign-in</dt><dd><?= e(ui_date($u['last_login_at'], true)) ?> UTC</dd>
        <dt>CVs</dt><dd><?= $cvCount ?></dd>
      </dl>
      <div class="row" style="justify-content:flex-end;margin-top:20px"><button class="btn btn-primary" type="submit">Save profile</button></div>
    </form>
  </section>

  <section class="card" aria-labelledby="pw-h">
    <div class="card-header"><h2 id="pw-h" style="margin:0;font-size:1.05rem">Change password</h2></div>
    <form class="card-body" method="post" action="<?= e(url('/account/password')) ?>">
      <?= csrf_field() ?>
      <div class="field"><label class="label" for="current_password">Current password</label>
        <input class="input" type="password" id="current_password" name="current_password" autocomplete="current-password" required <?= field_error('current_password') ? 'aria-invalid="true"' : '' ?>>
        <?php if ($e = field_error('current_password')): ?><span class="error-text"><?= e($e) ?></span><?php endif; ?></div>
      <div class="form-grid" style="margin-top:16px">
        <div class="field"><label class="label" for="password">New password</label>
          <input class="input" type="password" id="password" name="password" autocomplete="new-password" required data-strength="pw-strength" <?= field_error('password') ? 'aria-invalid="true"' : '' ?>>
          <div class="strength" id="pw-strength" aria-hidden="true"><span></span></div><span class="hint" id="pw-strength-label"></span>
          <?php if ($e = field_error('password')): ?><span class="error-text"><?= e($e) ?></span><?php endif; ?></div>
        <div class="field"><label class="label" for="password_confirmation">Confirm new password</label>
          <input class="input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
          <?php if ($e = field_error('password_confirmation')): ?><span class="error-text"><?= e($e) ?></span><?php endif; ?></div>
      </div>
      <p class="hint" style="margin-top:12px">Changing your password signs you out everywhere else.</p>
      <div class="row" style="justify-content:flex-end"><button class="btn btn-primary" type="submit">Change password</button></div>
    </form>
  </section>

  <section class="card" aria-labelledby="del-h" style="border-color:#F8C9C7">
    <div class="card-header"><h2 id="del-h" style="margin:0;font-size:1.05rem;color:var(--danger)">Delete account</h2></div>
    <form class="card-body" method="post" action="<?= e(url('/account/delete')) ?>" data-confirm="Delete your account?" data-confirm-message="Your account, your <?= $cvCount ?> CV(s) and every photo will be permanently deleted." data-confirm-button="Delete everything" data-confirm-danger="1">
      <?= csrf_field() ?>
      <p class="muted">This permanently deletes your account, all your CVs and photos. This cannot be undone.</p>
      <div class="field" style="max-width:360px"><label class="label" for="delete-password">Confirm with your password</label>
        <input class="input" type="password" id="delete-password" name="password" autocomplete="current-password" required <?= field_error('delete_password') ? 'aria-invalid="true"' : '' ?>>
        <?php if ($e = field_error('delete_password')): ?><span class="error-text"><?= e($e) ?></span><?php endif; ?></div>
      <div class="row" style="justify-content:flex-end;margin-top:16px"><button class="btn btn-danger" type="submit"><?= icon('trash-2', 16) ?> Delete my account</button></div>
    </form>
  </section>
</div>
