<?php
/** @var array{rows: list<array<string, mixed>>, total: int, page: int, pages: int} $result @var string $q @var string $status */
$me = auth_user();
$hidden = fn () => '<input type="hidden" name="q" value="' . e($q) . '"><input type="hidden" name="filter_status" value="' . e($status) . '"><input type="hidden" name="page" value="' . (int) $result['page'] . '">';
?>
<div class="page-head"><div><h1>Users</h1><p><?= (int) $result['total'] ?> account(s). Passwords and CV contents are never shown.</p></div></div>

<form class="filters" method="get" action="<?= e(url('/admin/users')) ?>" role="search" data-no-busy>
  <label class="sr-only" for="uq">Search users</label>
  <input class="input" type="search" id="uq" name="q" value="<?= e($q) ?>" placeholder="Search by name or e-mail…">
  <label class="sr-only" for="us">Status</label>
  <select class="select" id="us" name="status" onchange="this.form.submit()">
    <option value="">Any status</option>
    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Deactivated</option>
  </select>
  <button class="btn btn-secondary" type="submit"><?= icon('search', 16) ?> Search</button>
  <?php if ($q !== '' || $status !== ''): ?><a class="btn btn-ghost" href="<?= e(url('/admin/users')) ?>">Clear</a><?php endif; ?>
</form>

<div class="card">
  <?php if ($result['rows'] === []): ?>
    <div class="empty"><div class="ficon"><?= icon('users', 26) ?></div><h2>No user found</h2><p>Try another search.</p></div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th scope="col">User</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">CVs</th><th scope="col">Joined</th><th scope="col">Last sign-in</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
      <tbody>
      <?php foreach ($result['rows'] as $u): $isMe = (int) $u['id'] === (int) $me['id']; ?>
        <tr>
          <td><div style="font-weight:600"><?= e($u['name']) ?><?= $isMe ? ' <span class="badge">You</span>' : '' ?></div><div class="small muted"><?= e($u['email']) ?></div></td>
          <td><?= $u['role'] === 'admin' ? '<span class="badge badge-primary">Administrator</span>' : '<span class="badge">User</span>' ?><?= (int) $u['must_change_password'] ? ' <span class="badge badge-warning" title="Must choose a new password at next sign-in">Temp. password</span>' : '' ?></td>
          <td><?= $u['status'] === 'active' ? '<span class="badge badge-success badge-dot">Active</span>' : '<span class="badge badge-danger badge-dot">Deactivated</span>' ?></td>
          <td><?= (int) $u['cv_count'] ?></td>
          <td class="small nowrap"><?= e(ui_date($u['created_at'])) ?></td>
          <td class="small nowrap muted"><?= e($u['last_login_at'] ? ui_ago($u['last_login_at']) : 'Never') ?></td>
          <td style="text-align:right">
            <?php if (!$isMe): ?>
            <div class="menu-wrap" style="display:inline-block">
              <button class="btn btn-ghost btn-sm btn-icon" type="button" data-menu-button aria-expanded="false" aria-controls="um-<?= (int) $u['id'] ?>" aria-label="Actions for <?= e($u['email']) ?>"><?= icon('ellipsis-vertical', 16) ?></button>
              <div class="menu" id="um-<?= (int) $u['id'] ?>" hidden>
                <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/status')) ?>" data-confirm="<?= $u['status'] === 'active' ? 'Deactivate ' . e($u['email']) . '?' : 'Reactivate ' . e($u['email']) . '?' ?>"
                      data-confirm-message="<?= $u['status'] === 'active' ? 'The user is signed out immediately and cannot sign in until reactivated. Their CVs are kept.' : 'The user will be able to sign in again.' ?>"
                      data-confirm-button="<?= $u['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?>" data-confirm-danger="<?= $u['status'] === 'active' ? '1' : '0' ?>" data-no-busy>
                  <?= csrf_field() ?><?= $hidden() ?><input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'inactive' : 'active' ?>">
                  <button type="submit" class="<?= $u['status'] === 'active' ? 'danger' : '' ?>"><?= icon($u['status'] === 'active' ? 'ban' : 'circle-check') ?> <?= $u['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?></button>
                </form>
                <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/role')) ?>" data-confirm="<?= $u['role'] === 'admin' ? 'Remove administrator rights?' : 'Make this user an administrator?' ?>"
                      data-confirm-message="<?= $u['role'] === 'admin' ? 'They will keep their CVs but lose access to administration.' : 'Administrators can manage users, templates and settings.' ?>" data-confirm-button="Confirm" data-no-busy>
                  <?= csrf_field() ?><?= $hidden() ?><input type="hidden" name="role" value="<?= $u['role'] === 'admin' ? 'user' : 'admin' ?>">
                  <button type="submit"><?= icon('shield') ?> <?= $u['role'] === 'admin' ? 'Remove admin rights' : 'Make administrator' ?></button>
                </form>
              </div>
            </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($result['pages'] > 1): ?>
    <nav class="pagination" aria-label="Pages">
      <?php $link = fn (int $p) => url('/admin/users?' . http_build_query(array_filter(['q' => $q, 'status' => $status, 'page' => $p]))); ?>
      <?php if ($result['page'] > 1): ?><a class="btn btn-secondary btn-sm" href="<?= e($link($result['page'] - 1)) ?>"><?= icon('chevron-left', 16) ?> Previous</a><?php endif; ?>
      <span class="small muted">Page <?= $result['page'] ?> of <?= $result['pages'] ?></span>
      <?php if ($result['page'] < $result['pages']): ?><a class="btn btn-secondary btn-sm" href="<?= e($link($result['page'] + 1)) ?>">Next <?= icon('chevron-right', 16) ?></a><?php endif; ?>
    </nav>
  <?php endif; ?>
  <?php endif; ?>
</div>
