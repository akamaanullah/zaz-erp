<?php $rows = $list['rows'] ?? []; ?>
<div class="page-head"><div><p>Issue farm stock. The history is <b><?= e($ctx['season']) ?></b>. Remaining quantities are the physical balance.</p></div>
<a class="btn" id="add" data-open="add-dialog" href="<?= e(zaz_q($query, ['form' => 'add'])) ?>">Record usage</a></div>
<div class="grid g4">
<?php foreach ($snap['items'] as $item): $left = $snap['summary']['stock'][$ctx['farm']][$item]['left'] ?? 0; ?>
<div class="card kpi"><div><div class="lbl"><?= e($item) ?> remaining</div><div class="val"><?= e((string)$left) ?></div><div class="sub">Rate <?= e(rs($snap['itemRates'][$item] ?? 0)) ?></div></div></div>
<?php endforeach; ?>
</div>
<template id="add-dialog">
<form class="dialog" method="post" action="<?= e($base) ?>/api/usage/create">
<input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="farm" value="<?= e($ctx['farm']) ?>"><input type="hidden" name="season" value="<?= e($ctx['season']) ?>">
<div class="form-grid">
<div><label>Item</label><select name="Item"><?php foreach ($snap['items'] as $item): ?><option><?= e($item) ?></option><?php endforeach; ?></select></div>
<div><label>Target</label><select name="Target"><option value="seri">Seri</option><?php foreach ($snap['haris'] as $hari): if ($hari['farm'] !== $ctx['farm']) continue; ?><option value="<?= e($hari['id']) ?>"><?= e($hari['name']) ?></option><?php endforeach; ?></select></div>
<div><label>Crop</label><input name="Crop" required></div>
<div><label>Quantity</label><input name="Quantity" type="number" min="0.01" step="0.01" required></div>
</div>
<button class="btn">Record usage</button>
</form></template>
<div class="card" id="b" style="margin-top:16px"><div class="card-head"><h3>Farm stock balances (all seasons)</h3></div>
<table><thead><tr><th>Item</th><th class="num">Left</th></tr></thead><tbody>
<?php foreach ($snap['items'] as $item): ?><tr><td><?= e($item) ?></td><td class="num"><?= e((string)($snap['summary']['stock'][$ctx['farm']][$item]['left'] ?? 0)) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<div class="card" id="h" style="margin-top:16px"><div class="card-head"><h3>Usage history · <?= e($ctx['season']) ?></h3></div>
<form method="get"><input type="search" name="q" value="<?= e((string)($query['q'] ?? '')) ?>" aria-label="Search table" placeholder="Search…"></form>
<table><thead><tr><th>Item</th><th>Crop</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Cost</th><th>Status</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?>
<tr><td><?= e($row['item']) ?></td><td><?= e($row['crop']) ?></td><td class="num"><?= e((string)$row['qty']) ?></td><td class="num"><?= e(rs($row['rate'])) ?></td><td class="num"><?= e(rs($row['amount'])) ?></td><td><?= !empty($row['approved']) ? 'Approved' : 'Pending' ?></td></tr>
<?php endforeach; ?>
</tbody><tfoot><tr><td>Total</td><td></td><td></td><td></td><td class="num"><?= e(rs($list['totals']['amount'] ?? 0)) ?></td><td></td></tr></tfoot></table>
<?php require project_root() . '/views/partials/pager.php'; ?>
</div>
