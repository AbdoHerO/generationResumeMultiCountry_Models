<?php $u = auth_user(); $initials = mb_strtoupper(mb_substr(trim($u['name'] ?? 'U'), 0, 1)); ?>
<div class="side-bottom menu-wrap">
  <button class="user-chip" type="button" data-menu-button aria-expanded="false" aria-controls="user-menu">
    <span class="avatar" aria-hidden="true"><?= e($initials) ?></span>
    <span class="grow" style="min-width:0">
      <span class="truncate" style="display:block;font-weight:600;font-size:.9rem;color:var(--text)"><?= e($u['name'] ?? '') ?></span>
      <span class="truncate xs muted" style="display:block"><?= e($u['email'] ?? '') ?></span>
    </span>
    <?= icon('chevron-up', 16) ?>
  </button>
  <div class="menu" id="user-menu" hidden>
    <a href="<?= e(url('/account')) ?>"><?= icon('user') ?> Account</a>
    <?php if (($u['role'] ?? '') === 'admin'): ?><a href="<?= e(url('/admin')) ?>"><?= icon('shield') ?> Administration</a><?php endif; ?>
    <a href="<?= e(url('/')) ?>"><?= icon('house') ?> Home page</a>
    <hr>
    <form method="post" action="<?= e(url('/logout')) ?>" data-no-busy><?= csrf_field() ?><button type="submit"><?= icon('log-out') ?> Sign out</button></form>
  </div>
</div>
