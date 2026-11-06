<?php $rows = $list['rows'] ?? []; $totals = $list['totals'] ?? []; ?>
<div class="page-head"><div><p>Weight × rate in the chosen unit. Hari sales are 50/50. Seri sales are 100% zamindar. This list is <b><?= e($ctx['season']) ?></b>.</p></div>
<a class="btn" id="add" data-open="add-dialog" href="<?= e(zaz_q($query, ['form' => 'add'])) ?>">Record sale</a></div>
<template id="add-dialog">
<form class="dialog" method="post" action="<?= e($base) ?>/api/sales/create" id="sale-form">
<input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="farm" value="<?= e($ctx['farm']) ?>"><input type="hidden" name="season" value="<?= e($ctx['season']) ?>">
<div class="form-grid">
<div><label>Hari / Seri</label><select name="Target"><option value="seri">Seri — 100% Zamindar</option><?php foreach ($snap['haris'] as $hari): if ($hari['farm'] !== $ctx['farm']) continue; ?><option value="<?= e($hari['id']) ?>"><?= e($hari['name']) ?></option><?php endforeach; ?></select></div>
<div><label>Crop</label><input name="Crop" required></div>
<div><label>Weight unit</label><select name="Unit"><option value="maund">Maund (40 kg)</option><option value="kg">Kilogram</option></select></div>
<div><label>Weight</label><input name="Weight" type="number" min="0.01" step="0.01" required></div>
<div><label>Rate</label><input name="Rate" type="number" min="0.01" step="0.01" required></div>
</div>
<p>Total <b id="liveTot">Rs 0</b></p>
<button class="btn">Add sale</button>
</form></template>
<div class="card" id="t">
<form method="get"><input type="search" name="q" value="<?= e((string)($query['q'] ?? '')) ?>" aria-label="Search table" placeholder="Search…"></form>
<table><thead><tr><th>Target</th><th>Crop</th><th class="num">Weight</th><th class="num"><a href="<?= e(zaz_q($query, ['sort' => 'amount', 'dir' => 'desc'])) ?>">Amount</a></th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr><td><?= ($row['target'] ?? '') === 'seri' ? 'Seri' : e($row['target']) ?></td><td><?= e($row['crop']) ?></td><td class="num"><?= e((string)$row['weight']) ?> <?= e($row['unit']) ?></td><td class="num"><?= e(rs($row['amount'])) ?></td><td><?= !empty($row['approved']) ? 'Approved' : 'Pending' ?></td></tr>
<?php endforeach; ?>
</tbody>
<tfoot><tr><td>Total</td><td></td><td class="num"><?= e((string)($totals['kg'] ?? 0)) ?> kg</td><td class="num"><?= e(rs($totals['amount'] ?? 0)) ?></td><td></td></tr></tfoot>
</table>
<?php require project_root() . '/views/partials/pager.php'; ?>
</div>
