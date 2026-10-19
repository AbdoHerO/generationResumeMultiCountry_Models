<?php
/** @var array<string, int> $stats @var list<array<string, mixed>> $byCountry @var list<array<string, mixed>> $signups @var list<array<string, mixed>> $created @var list<array<string, mixed>> $topTemplates @var list<array<string, mixed>> $recent */
$series = function (array $rows): array {
    $map = array_column($rows, 'n', 'day');
    $out = [];
    for ($i = 13; $i >= 0; $i--) {
        $d = gmdate('Y-m-d', time() - $i * 86400);
        $out[$d] = (int) ($map[$d] ?? 0);
    }
    return $out;
};
$s1 = $series($signups);
$s2 = $series($created);
$bars = function (array $s): string {
    $max = max(1, max($s));
    $html = '<div class="bars" role="img" aria-label="Last 14 days: ' . e(implode(', ', $s)) . '">';
    foreach ($s as $day => $n) {
        $html .= '<span class="' . ($n > 0 ? 'has' : '') . '" style="height:' . max(3, (int) round(100 * $n / $max)) . '%" title="' . e($day . ': ' . $n) . '"></span>';
    }
    return $html . '</div>';
};
$totalByCountry = max(1, array_sum(array_column($byCountry, 'n')));
?>
<div class="page-head"><div><h1>Administration</h1><p>Accounts, templates and activity at a glance. CV contents are private to their owners.</p></div></div>

<div class="stats">
  <div class="card stat"><div class="stat-label"><?= icon('users', 16) ?> Registered users</div><div class="stat-value"><?= $stats['users'] ?></div><span class="xs muted"><?= $stats['admins'] ?> administrator(s)</span></div>
  <div class="card stat"><div class="stat-label"><?= icon('files', 16) ?> CVs</div><div class="stat-value"><?= $stats['cvs'] ?></div><span class="xs muted"><?= $stats['cvsUpdated24h'] ?> edited in the last 24 h</span></div>
  <div class="card stat"><div class="stat-label"><?= icon('layout-template', 16) ?> Templates</div><div class="stat-value"><?= $stats['templates'] ?></div><span class="xs muted"><?= $stats['activeTemplates'] ?> available to users</span></div>
  <div class="card stat"><div class="stat-label"><?= icon('globe', 16) ?> Countries</div><div class="stat-value"><?= count(App\Services\Countries::all(true)) ?></div><span class="xs muted">of <?= count(App\Services\Countries::all()) ?> supported</span></div>
</div>

<div class="admin-grid">
  <div class="stack">
    <section class="card">
      <div class="card-header"><h2 style="margin:0;font-size:1rem">New accounts · last 14 days</h2><span class="badge"><?= array_sum($s1) ?></span></div>
      <div class="card-body"><?= $bars($s1) ?></div>
    </section>
    <section class="card">
      <div class="card-header"><h2 style="margin:0;font-size:1rem">CVs created · last 14 days</h2><span class="badge"><?= array_sum($s2) ?></span></div>
      <div class="card-body"><?= $bars($s2) ?></div>
    </section>
    <section class="card">
      <div class="card-header"><h2 style="margin:0;font-size:1rem">Recent administrative activity</h2><a class="small" href="<?= e(url('/admin/audit')) ?>">View all</a></div>
      <?php if ($recent === []): ?><div class="card-body muted">No activity yet.</div><?php else: ?>
      <div class="table-wrap"><table class="table"><tbody>
        <?php foreach ($recent as $r): ?>
          <tr><td class="nowrap small muted"><?= e(ui_ago($r['created_at'])) ?></td><td><code class="small"><?= e($r['action']) ?></code></td><td class="small truncate" style="max-width:220px"><?= e($r['actor_email'] ?? 'system') ?></td></tr>
        <?php endforeach; ?>
      </tbody></table></div><?php endif; ?>
    </section>
  </div>
  <div class="stack">
    <section class="card">
      <div class="card-header"><h2 style="margin:0;font-size:1rem">CVs by country</h2></div>
      <div class="card-body stack">
        <?php if ($byCountry === []): ?><p class="muted mb-0">No CV yet.</p><?php endif; ?>
        <?php foreach ($byCountry as $row): $pct = round(100 * $row['n'] / $totalByCountry); ?>
          <div>
            <div class="row-between small"><span><?= flag($row['country_code']) ?> <?= e(App\Services\Countries::name($row['country_code'])) ?></span><span class="muted"><?= (int) $row['n'] ?> · <?= $pct ?>%</span></div>
            <div class="strength" style="height:6px;margin-top:6px"><span style="width:<?= $pct ?>%;background:var(--primary)"></span></div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <section class="card">
      <div class="card-header"><h2 style="margin:0;font-size:1rem">Most used templates</h2><a class="small" href="<?= e(url('/admin/templates')) ?>">Manage</a></div>
      <div class="table-wrap"><table class="table"><tbody>
        <?php foreach ($topTemplates as $t): ?>
          <tr><td><?= flag($t['country_code']) ?> <span class="small"><?= e($t['name']) ?></span><?= $t['is_active'] ? '' : ' <span class="badge">Hidden</span>' ?></td><td class="small muted" style="text-align:right"><?= (int) $t['cv_count'] ?></td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
    </section>
  </div>
</div>
