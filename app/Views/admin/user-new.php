<?php /** New account created by an administrator. */ ?>
<div class="page-head">
  <div>
    <a class="small" href="<?= e(url('/admin/users')) ?>"><?= icon('arrow-left', 14) ?> Users</a>
    <h1>New user</h1>
    <p>The account gets a temporary password, shown once on the next page. The user must choose their own password at the first sign-in.</p>
  </div>
</div>
<form class="card card-pad" method="post" action="<?= e(url('/admin/users')) ?>" style="max-width:640px" novalidate>
  <?= csrf_field() ?>
  <div class="field"><label class="label" for="name">Full name</label>
    <input class="input" id="name" name="name" value="<?= e(old('name')) ?>" maxlength="120" required autocomplete="off" <?= field_error('name') ? 'aria-invalid="true" aria-describedby="name-err"' : '' ?>>
    <?php if ($err = field_error('name')): ?><span class="error-text" id="name-err"><?= e($err) ?></span><?php endif; ?></div>
  <div class="field"><label class="label" for="email">E-mail address</label>
    <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" maxlength="190" required autocomplete="off" <?= field_error('email') ? 'aria-invalid="true" aria-describedby="email-err"' : '' ?>>
    <?php if ($err = field_error('email')): ?><span class="error-text" id="email-err"><?= e($err) ?></span><?php endif; ?></div>
  <fieldset class="field" style="border:0;padding:0;margin-top:16px"><legend class="label">Role</legend>
    <div class="row">
      <label class="check"><input type="radio" name="role" value="user" <?= old('role', 'user') !== 'admin' ? 'checked' : '' ?>> User</label>
      <label class="check"><input type="radio" name="role" value="admin" <?= old('role') === 'admin' ? 'checked' : '' ?>> Administrator</label>
    </div>
  </fieldset>
  <div class="row" style="justify-content:flex-end;margin-top:20px">
    <a class="btn btn-ghost" href="<?= e(url('/admin/users')) ?>">Cancel</a>
    <button class="btn btn-primary" type="submit"><?= icon('plus', 16) ?> Create account</button>
  </div>
</form>
