<?php /** @var array{rows: list<array<string, mixed>>, total: int, page: int, pages: int} $log @var string $action */ ?>
<div class="page-head"><div><h1>Activity log</h1><p>Administrative and security events (<?= (int) $log['total'] ?>). No passwords, tokens or CV contents are recorded.</p></div></div>
<form class="filters" method="get" action="<?= e(url('/admin/audit')) ?>" data-no-busy>
  <label class="sr-only" for="action">Filter by action</label>
  <select class="select" id="action" name="action" onchange="this.form.submit()">
    <option value="">All events</option>
    <?php foreach (['admin.' => 'Administration', 'auth.' => 'Sign-in & passwords', 'account.' => 'Accounts'] as $k => $l): ?><option value="<?= e($k) ?>" <?= $action === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
  </select>
  <noscript><button class="btn btn-secondary" type="submit">Filter</button></noscript>
</form>
<div class="card">
  <?php if ($log['rows'] === []): ?><div class="empty"><div class="ficon"><?= icon('activity', 26) ?></div><h2>No event</h2></div><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th scope="col">When (UTC)</th><th scope="col">Event</th><th scope="col">By</th><th scope="col">Target</th><th scope="col">Details</th><th scope="col">IP</th></tr></thead>
    <tbody>
    <?php foreach ($log['rows'] as $r): $d = json_decode((string) $r['details'], true) ?: []; ?>
      <tr>
        <td class="small nowrap"><?= e(ui_date($r['created_at'], true)) ?></td>
        <td><code class="small"><?= e($r['action']) ?></code></td>
        <td class="small"><?= e($r['actor_email'] ?? 'system') ?></td>
        <td class="small"><?= e(trim(($r['target_type'] ?? '') . ' ' . ($r['target_id'] ?? ''))) ?></td>
        <td class="small muted" style="max-width:320px"><?= e(implode(', ', array_map(fn ($k, $v) => $k . ': ' . (is_scalar($v) ? (is_bool($v) ? ($v ? 'yes' : 'no') : $v) : json_encode($v)), array_keys($d), $d))) ?></td>
        <td class="small muted"><?= e($r['ip'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($log['pages'] > 1): $link = fn (int $p) => url('/admin/audit?' . http_build_query(array_filter(['action' => $action, 'page' => $p]))); ?>
    <nav class="pagination" aria-label="Pages">
      <?php if ($log['page'] > 1): ?><a class="btn btn-secondary btn-sm" href="<?= e($link($log['page'] - 1)) ?>">Previous</a><?php endif; ?>
      <span class="small muted">Page <?= $log['page'] ?> of <?= $log['pages'] ?></span>
      <?php if ($log['page'] < $log['pages']): ?><a class="btn btn-secondary btn-sm" href="<?= e($link($log['page'] + 1)) ?>">Next</a><?php endif; ?>
    </nav>
  <?php endif; ?>
  <?php endif; ?>
</div>
