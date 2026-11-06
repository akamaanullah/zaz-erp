<div class="page-head"><div><p>Office only. Receive stock into the main godown. Balances are physical, not seasonal.</p></div>
<a class="btn" id="add" data-open="add-dialog" href="<?= e(zaz_q($query, ['form' => 'add'])) ?>">Receive into godown</a></div>
<template id="add-dialog">
<form class="dialog" method="post" action="<?= e($base) ?>/api/godown/receive">
<input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="date" value="<?= e(date('Y-m-d')) ?>">
<div class="form-grid">
<div><label>Item</label><select name="Item"><?php foreach ($snap['items'] as $item): ?><option><?= e($item) ?></option><?php endforeach; ?></select></div>
<div><label>Quantity</label><input name="Quantity" type="number" min="0.01" step="0.01" required></div>
<div><label>Office rate</label><input name="Rate" type="number" min="0" step="0.01" required></div>
</div>
<button class="btn">Receive into godown</button>
</form></template>
<div class="grid g4">
<?php foreach ($snap['items'] as $item): $cell = $snap['godown'][$item] ?? ['available' => 0, 'received' => 0]; ?>
<div class="card kpi"><div><div class="lbl"><?= e($item) ?> available</div><div class="val"><?= e((string)$cell['available']) ?></div></div></div>
<?php endforeach; ?>
</div>
<div class="card" id="r" style="margin-top:16px"><table><thead><tr><th>Date</th><th>Item</th><th class="num">Qty</th><th class="num">Rate</th></tr></thead><tbody>
<?php foreach ($list['rows'] ?? [] as $row): ?><tr><td><?= e($row['date']) ?></td><td><?= e($row['item']) ?></td><td class="num"><?= e((string)$row['qty']) ?></td><td class="num"><?= e(rs($row['rate'])) ?></td></tr><?php endforeach; ?>
</tbody></table><?php require project_root() . '/views/partials/pager.php'; ?></div>
