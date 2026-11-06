<?php $rows = $list['rows'] ?? []; ?>
<div class="page-head"><div><p>Pending farm entries for <b><?= e($ctx['farm']) ?></b> · <b><?= e($ctx['season']) ?></b>. Approval does not move stock again.</p></div>
<button type="button" class="btn" id="all" data-open="approve-all" <?= ($list['total'] ?? 0) ? '' : 'disabled' ?>>Approve all pending for <?= e($ctx['farm']) ?> · <?= e($ctx['season']) ?></button>
<template id="approve-all"><form class="dialog" method="post" action="<?= e($base) ?>/api/approvals/bulk"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="farm" value="<?= e($ctx['farm']) ?>"><input type="hidden" name="season" value="<?= e($ctx['season']) ?>"><p>Approve every pending entry for <?= e($ctx['farm']) ?> · <?= e($ctx['season']) ?>?</p><button class="btn">Approve all</button></form></template>
</div>
<div class="card" id="t">
<form method="get"><input type="search" name="q" value="<?= e((string)($query['q'] ?? '')) ?>" aria-label="Search table" placeholder="Search…"></form>
<table><thead><tr><th>Type</th><th>Description</th><th class="sortable"><a href="<?= e(zaz_q($query, ['sort' => 'amount'])) ?>">Amount / qty</a></th><th>By</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $row): ?>
<tr><td><?= e($row['_t']) ?></td><td><?= e($row['item'] ?? $row['crop'] ?? $row['reason'] ?? '') ?></td><td class="num"><?= e(rs($row['amount'] ?? $row['qty'] ?? 0)) ?></td><td><?= e($row['by'] ?? '') ?></td>
<td><form method="post" action="<?= e($base) ?>/api/approvals/one"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="type" value="<?= e($row['_t']) ?>"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><button class="btn secondary sm" data-ok="<?= e($row['_t'] . ':' . $row['id']) ?>">Approve</button></form></td></tr>
<?php endforeach; ?>
</tbody></table>
<p class="table-meta"><?= (int)($list['total'] ?? 0) ?> matching records</p>
<?php require project_root() . '/views/partials/pager.php'; ?>
</div>
