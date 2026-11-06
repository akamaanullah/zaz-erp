<?php
$stat = $pack['byFarm'][$ctx['farm']] ?? [];
$rows = $list['rows'] ?? [];
?>
<div class="page-head"><div><p>Sales, expenses and advances for <b><?= e($ctx['farm']) ?></b> · <b><?= e($ctx['season']) ?></b>. Stock figures below are the physical balance, not this season alone.</p></div>
<div class="row">
<?php if ($ctx['office']): ?>
<a class="btn secondary" data-scope="farm" href="<?= e(zaz_q($query, ['scope' => 'farm'])) ?>">This farm</a>
<a class="btn secondary" data-scope="all" href="<?= e(zaz_q($query, ['scope' => 'all'])) ?>">All Farms</a>
<?php endif; ?>
</div></div>
<div class="card"><table><thead><tr><th>Type</th><th class="num">Count</th><th class="num">Total</th></tr></thead><tbody>
<tr><td>Sales</td><td class="num"><?= (int)($stat['saleCount'] ?? 0) ?></td><td class="num"><?= e(rs($stat['sales'] ?? 0)) ?></td></tr>
<tr><td>Expenses (usage + other)</td><td class="num"><?= (int)(($stat['usageCount'] ?? 0) + ($stat['expenseCount'] ?? 0)) ?></td><td class="num"><?= e(rs($stat['costs'] ?? 0)) ?></td></tr>
<tr><td>Hari advances</td><td class="num"><?= (int)($stat['advanceCount'] ?? 0) ?></td><td class="num"><?= e(rs($stat['advances'] ?? 0)) ?></td></tr>
</tbody></table></div>
<?php if ($ctx['scope'] === 'all'): ?><div class="card" id="sAll" style="margin-top:16px"><h3>Stock — all farms (physical)</h3>
<ul><?php foreach ($snap['items'] as $item): $used = 0; foreach (($snap['summary']['stock'] ?? []) as $cells) { $used += (float)($cells[$item]['used'] ?? 0); } ?><li><?= e($item) ?> used <?= e((string)$used) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card" id="sw" style="margin-top:16px"><h3>Crop sales · <?= e($ctx['season']) ?></h3>
<form method="get"><input type="hidden" name="season" value="<?= e($ctx['season']) ?>"><input type="search" name="q" value="<?= e((string)($query['q'] ?? '')) ?>" aria-label="Search table" placeholder="Search…"></form>
<table><thead><tr><th>Crop</th><th class="num">Amount</th><th>Status</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= e($row['crop']) ?></td><td class="num"><?= e(rs($row['amount'])) ?></td><td><?= !empty($row['approved']) ? 'Approved' : 'Pending' ?></td></tr><?php endforeach; ?>
</tbody><tfoot><tr><td>Total</td><td class="num"><?= e(rs($list['totals']['amount'] ?? 0)) ?></td><td></td></tr></tfoot></table>
<?php require project_root() . '/views/partials/pager.php'; ?>
</div>
