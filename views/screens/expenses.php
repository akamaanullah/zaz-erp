<?php $rows = $list['rows'] ?? []; ?>
<div class="page-head"><div><p>Quantity × rate for <b><?= e($ctx['farm']) ?></b> · <b><?= e($ctx['season']) ?></b>. Past entries keep the rate they were saved with.</p></div>
<a class="btn" id="add" data-open="add-dialog" href="<?= e(zaz_q($query, ['form' => 'add'])) ?>">Add expense</a></div>
<template id="add-dialog">
<form class="dialog" method="post" action="<?= e($base) ?>/api/expenses/create">
<input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="farm" value="<?= e($ctx['farm']) ?>"><input type="hidden" name="season" value="<?= e($ctx['season']) ?>">
<div class="form-grid">
<div><label>Category</label><select name="Category" required><?php foreach ($snap['categories'] as $cat): ?><option value="<?= e($cat['name']) ?>"><?= e($cat['name']) ?> — <?= e($cat['unit']) ?></option><?php endforeach; ?></select></div>
<div><label>Hari / Seri</label><select name="Target"><option value="seri">Seri</option><?php foreach ($snap['haris'] as $hari): if ($hari['farm'] !== $ctx['farm']) continue; ?><option value="<?= e($hari['id']) ?>"><?= e($hari['name']) ?></option><?php endforeach; ?></select></div>
<div><label>Crop</label><input name="Crop" required></div>
<div><label>Quantity</label><input name="Qty" type="number" min="0.01" step="0.01" value="1" required></div>
<div><label>Rate</label><input name="Rate" type="number" min="0" step="0.01" required></div>
</div>
<button class="btn">Add expense</button>
</form></template>
<div class="card" id="t">
<form method="get"><input type="search" name="q" value="<?= e((string)($query['q'] ?? '')) ?>" aria-label="Search table" placeholder="Search…"></form>
<table><thead><tr><th class="sortable">Category</th><th>Crop</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Total</th><th>Status</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?>
<tr><td><?= e($row['category'] ?? $row['item']) ?></td><td><?= e($row['crop']) ?></td><td class="num"><?= e((string)$row['qty']) ?></td><td class="num"><?= e(rs($row['rate'])) ?></td><td class="num"><?= e(rs($row['amount'])) ?></td><td><?= !empty($row['approved']) ? 'Approved' : 'Pending' ?></td></tr>
<?php endforeach; ?>
</tbody><tfoot><tr><td>Total</td><td></td><td></td><td></td><td class="num"><?= e(rs($list['totals']['amount'] ?? 0)) ?></td><td></td></tr></tfoot></table>
<?php require project_root() . '/views/partials/pager.php'; ?>
</div>
