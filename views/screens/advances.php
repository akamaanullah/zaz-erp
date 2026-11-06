<?php $rows = $list['rows'] ?? []; ?>
<div class="page-head"><div><p>Personal advances for <b><?= e($ctx['season']) ?></b> reduce the Hari ledger. They are not farm operating expenses.</p></div>
<a class="btn" id="add" data-open="add-dialog" href="<?= e(zaz_q($query, ['form' => 'add'])) ?>">Record advance</a></div>
<template id="add-dialog">
<form class="dialog" method="post" action="<?= e($base) ?>/api/advances/create">
<input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="farm" value="<?= e($ctx['farm']) ?>"><input type="hidden" name="season" value="<?= e($ctx['season']) ?>">
<div class="form-grid">
<div><label>Hari</label><select name="Hari" required><?php foreach ($snap['haris'] as $hari): if ($hari['farm'] !== $ctx['farm']) continue; ?><option value="<?= e($hari['id']) ?>"><?= e($hari['name']) ?></option><?php endforeach; ?></select></div>
<div><label>Reason</label><input name="Reason" required></div>
<div><label>Amount</label><input name="Amount" type="number" min="0.01" step="0.01" required></div>
</div>
<button class="btn">Record advance</button>
</form></template>
<div class="card" id="t">
<table><thead><tr><th>Reason</th><th class="num">Amount</th><th>Status</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= e($row['reason']) ?></td><td class="num"><?= e(rs($row['amount'])) ?></td><td><?= !empty($row['approved']) ? 'Approved' : 'Pending' ?></td></tr><?php endforeach; ?>
</tbody><tfoot><tr><td>Total</td><td class="num"><?= e(rs($list['totals']['amount'] ?? 0)) ?></td><td></td></tr></tfoot></table>
<?php require project_root() . '/views/partials/pager.php'; ?>
</div>
