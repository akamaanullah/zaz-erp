<?php $rows = $list['rows'] ?? []; ?>
<div class="page-head"><div><p>Stock transfers are movements, not expenses, and are not filtered by season. Sent 20 and received 18 keeps the difference.</p></div>
<?php if ($ctx['office']): ?><a class="btn" id="send" data-open="add-dialog" href="<?= e(zaz_q($query, ['form' => 'add'])) ?>">Send stock</a><?php endif; ?>
</div>
<template id="add-dialog">
<form class="dialog" method="post" action="<?= e($base) ?>/api/transfers/send">
<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<div class="form-grid">
<div><label>Farm</label><select name="Farm"><?php foreach ($snap['farms'] as $farm): if ($farm['archived']) continue; ?><option <?= $farm['name'] === $ctx['farm'] ? 'selected' : '' ?>><?= e($farm['name']) ?></option><?php endforeach; ?></select></div>
<div><label>Item</label><select name="Item"><?php foreach ($snap['items'] as $item): ?><option><?= e($item) ?></option><?php endforeach; ?></select></div>
<div><label>Quantity</label><input name="Quantity" type="number" min="0.01" step="0.01" required></div>
</div>
<button class="btn">Send stock</button>
</form></template>
<div class="card" id="t"><table><thead><tr><th>Farm</th><th>Item</th><th class="num">Sent</th><th class="num">Received</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $row): ?>
<tr><td><?= e($row['farm']) ?></td><td><?= e($row['item']) ?></td><td class="num"><?= e((string)$row['qty']) ?></td><td class="num"><?= $row['received'] === null ? 'Awaiting' : e((string)$row['received']) ?></td>
<td><?php if ($row['received'] === null && in_array('farm.receive', $snap['abilities'] ?? [], true)): ?>
<button type="button" class="btn sm" data-receive="<?= e($row['id']) ?>" data-qty="<?= e((string)$row['qty']) ?>" data-item="<?= e($row['item']) ?>" data-farm="<?= e($row['farm']) ?>" data-open="recv-<?= e($row['id']) ?>">Receive</button>
<template id="recv-<?= e($row['id']) ?>"><form class="dialog" method="post" action="<?= e($base) ?>/api/transfers/receive"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><label>Quantity actually received (sent <?= e((string)$row['qty']) ?>)</label><input name="q" type="number" min="0" max="<?= e((string)$row['qty']) ?>" step="0.01" value="<?= e((string)$row['qty']) ?>" required><button class="btn">Confirm received</button></form></template>
<?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table><?php require project_root() . '/views/partials/pager.php'; ?></div>
