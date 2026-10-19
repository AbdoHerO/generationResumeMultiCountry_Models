<?php
/** @var array<string, mixed> $u @var int $cvCount @var bool $isMe @var array{password: string, reason: string}|null $reveal */
$base = '/admin/users/' . $u['id'];
$active = $u['status'] === 'active';
$admin = $u['role'] === 'admin';
?>
<div class="page-head">
  <div>
    <a class="small" href="<?= e(url('/admin/users')) ?>"><?= icon('arrow-left', 14) ?> Users</a>
    <h1><?= e($u['name']) ?><?= $isMe ? ' <span class="badge">You</span>' : '' ?></h1>
    <p><?= e($u['email']) ?></p>
  </div>
</div>

<?php if ($reveal): ?>
  <div class="alert alert-warning" role="alert" style="margin-bottom:20px;align-items:flex-start">
    <?= icon('key-round') ?>
    <div class="grow">
      <strong><?= $reveal['reason'] === 'created' ? 'Account created.' : 'Password reset.' ?> Temporary password:</strong>
      <div class="row" style="margin:8px 0;gap:8px">
        <code id="temp-pw" style="font-size:1.05rem;padding:6px 10px;background:#fff;border-radius:6px;border:1px solid var(--border)"><?= e($reveal['password']) ?></code>
        <button class="btn btn-secondary btn-sm" type="button" data-copy="temp-pw"><?= icon('copy', 14) ?> Copy</button>
      </div>
      Give it to <?= e($u['email']) ?> through a safe channel. It is shown only once and is not stored anywhere readable; the user must replace it at the first sign-in.
    </div>
  </div>
<?php endif; ?>

<div class="admin-grid">
  <div class="stack">
    <section class="card card-pad" aria-labelledby="h-profile">
      <h2 id="h-profile" style="font-size:1.05rem">Profile</h2>
      <form method="post" action="<?= e(url($base)) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field"><label class="label" for="name">Full name</label>
          <input class="input" id="name" name="name" value="<?= e(old('name', (string) $u['name'])) ?>" maxlength="120" required <?= field_error('name') ? 'aria-invalid="true" aria-describedby="name-err"' : '' ?>>
          <?php if ($err = field_error('name')): ?><span class="error-text" id="name-err"><?= e($err) ?></span><?php endif; ?></div>
        <div class="field"><label class="label" for="email">E-mail address</label>
          <input class="input" type="email" id="email" name="email" value="<?= e(old('email', (string) $u['email'])) ?>" maxlength="190" required <?= field_error('email') ? 'aria-invalid="true" aria-describedby="email-err"' : '' ?>>
          <span class="hint">The user signs in with this address.</span>
          <?php if ($err = field_error('email')): ?><span class="error-text" id="email-err"><?= e($err) ?></span><?php endif; ?></div>
        <div class="row" style="justify-content:flex-end;margin-top:16px"><button class="btn btn-primary" type="submit">Save changes</button></div>
      </form>
    </section>

    <?php if (!$isMe): ?>
    <section class="card card-pad" aria-labelledby="h-access">
      <h2 id="h-access" style="font-size:1.05rem">Access</h2>
      <div class="stack">
        <div class="row-between">
          <div><strong>Password</strong><div class="small muted">Temporary password; signs the user out.</div></div>
          <form method="post" action="<?= e(url($base . '/password')) ?>" data-confirm="Reset the password of <?= e($u['email']) ?>?" data-confirm-message="Their current password stops working and they are signed out. You will see a temporary password to give them." data-confirm-button="Reset password">
            <?= csrf_field() ?><button class="btn btn-secondary btn-sm" type="submit"><?= icon('key-round', 15) ?> Reset password</button>
          </form>
        </div>
        <div class="row-between">
          <div><strong>Status</strong><div class="small muted"><?= $active ? 'Active: the user can sign in.' : 'Deactivated: the user cannot sign in. Their CVs are kept.' ?></div></div>
          <form method="post" action="<?= e(url($base . '/status')) ?>" data-confirm="<?= $active ? 'Deactivate' : 'Reactivate' ?> <?= e($u['email']) ?>?" data-confirm-button="<?= $active ? 'Deactivate' : 'Reactivate' ?>" data-confirm-danger="<?= $active ? '1' : '0' ?>">
            <?= csrf_field() ?><input type="hidden" name="return" value="user"><input type="hidden" name="status" value="<?= $active ? 'inactive' : 'active' ?>">
            <button class="btn btn-secondary btn-sm" type="submit"><?= icon($active ? 'ban' : 'circle-check', 15) ?> <?= $active ? 'Deactivate' : 'Reactivate' ?></button>
          </form>
        </div>
        <div class="row-between">
          <div><strong>Role</strong><div class="small muted"><?= $admin ? 'Administrator: manages users, templates and settings.' : 'User: manages their own CVs only.' ?></div></div>
          <form method="post" action="<?= e(url($base . '/role')) ?>" data-confirm="<?= $admin ? 'Remove administrator rights?' : 'Make this user an administrator?' ?>" data-confirm-button="Confirm">
            <?= csrf_field() ?><input type="hidden" name="return" value="user"><input type="hidden" name="role" value="<?= $admin ? 'user' : 'admin' ?>">
            <button class="btn btn-secondary btn-sm" type="submit"><?= icon('shield', 15) ?> <?= $admin ? 'Remove admin rights' : 'Make administrator' ?></button>
          </form>
        </div>
      </div>
    </section>

    <section class="card card-pad" aria-labelledby="h-delete" style="border-color:#F8C9C7">
      <h2 id="h-delete" style="font-size:1.05rem;color:var(--danger)">Delete account</h2>
      <p class="small muted">Deletes the account, its <?= $cvCount ?> CV(s) and their photos permanently. This cannot be undone.</p>
      <form method="post" action="<?= e(url($base . '/delete')) ?>" data-confirm="Delete <?= e($u['email']) ?> permanently?" data-confirm-message="The account and all its CVs are deleted." data-confirm-button="Delete account" data-confirm-danger="1" novalidate>
        <?= csrf_field() ?>
        <div class="field"><label class="label" for="confirm_email">Type <strong><?= e($u['email']) ?></strong> to confirm</label>
          <input class="input" id="confirm_email" name="confirm_email" autocomplete="off" required <?= field_error('confirm_email') ? 'aria-invalid="true" aria-describedby="ce-err"' : '' ?>>
          <?php if ($err = field_error('confirm_email')): ?><span class="error-text" id="ce-err"><?= e($err) ?></span><?php endif; ?></div>
        <div class="row" style="justify-content:flex-end;margin-top:12px"><button class="btn btn-danger" type="submit"><?= icon('trash-2', 15) ?> Delete account</button></div>
      </form>
    </section>
    <?php else: ?>
      <div class="alert alert-info"><?= icon('info') ?><span>This is your own account. Change your password or delete your account from <a href="<?= e(url('/account')) ?>">your account page</a>.</span></div>
    <?php endif; ?>
  </div>

  <aside class="stack">
    <section class="card card-pad">
      <h2 style="font-size:1.05rem">Summary</h2>
      <dl class="kv small">
        <dt>Role</dt><dd><?= $admin ? '<span class="badge badge-primary">Administrator</span>' : '<span class="badge">User</span>' ?></dd>
        <dt>Status</dt><dd><?= $active ? '<span class="badge badge-success badge-dot">Active</span>' : '<span class="badge badge-danger badge-dot">Deactivated</span>' ?></dd>
        <dt>Password</dt><dd><?= (int) $u['must_change_password'] ? '<span class="badge badge-warning">Temporary: must be changed</span>' : 'Chosen by the user' ?></dd>
        <dt>CVs</dt><dd><?= $cvCount ?></dd>
        <dt>Joined</dt><dd><?= e(ui_date($u['created_at'])) ?></dd>
        <dt>Last sign-in</dt><dd><?= e($u['last_login_at'] ? ui_ago($u['last_login_at']) : 'Never') ?></dd>
      </dl>
      <p class="xs muted mb-0" style="margin-top:12px">CV contents are private to their owner and are not shown to administrators.</p>
    </section>
  </aside>
</div>
