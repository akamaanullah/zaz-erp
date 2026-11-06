<div class="page-head"><div><p>Staff pay is monthly. It is not filtered by crop season.</p></div></div>
<div class="card" id="t"><table><thead><tr><th>Staff</th><th class="num">Salary</th><th></th></tr></thead><tbody>
<?php foreach ($snap['users'] as $person): if (!$person['login']) continue; ?>
<tr><td><?= e($person['name']) ?></td><td class="num"><?= e(rs($person['salary'])) ?></td>
<td>
<button type="button" class="btn secondary sm" data-sal="<?= e($person['id']) ?>" data-open="sal-<?= e($person['id']) ?>">Salary</button>
<button type="button" class="btn sm" data-payu="<?= e($person['id']) ?>" data-open="pay-<?= e($person['id']) ?>">Payment</button>
<template id="sal-<?= e($person['id']) ?>"><form class="dialog" method="post" action="<?= e($base) ?>/api/employees/salary"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= e($person['id']) ?>"><input type="hidden" name="payType" value="<?= e($person['payType']) ?>"><label>Monthly salary</label><input name="salary" type="number" min="0" step="0.01" value="<?= e((string)$person['salary']) ?>" required><button class="btn">Save salary</button></form></template>
<template id="pay-<?= e($person['id']) ?>"><form class="dialog" method="post" action="<?= e($base) ?>/api/salaries/pay"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="userId" value="<?= e($person['id']) ?>"><label>Month</label><input name="month" type="month" value="<?= e(date('Y-m')) ?>" required><label>Type</label><select name="kind"><option>Salary</option><option>Advance</option></select><label>Amount</label><input name="amount" type="number" min="1" step="0.01" required><label>Status</label><select name="status"><option>Paid</option><option>Pending</option></select><label>Date</label><input name="date" type="date" value="<?= e(date('Y-m-d')) ?>"><button class="btn">Save payment</button></form></template>
</td></tr>
<?php endforeach; ?>
</tbody></table></div>
<div class="card" id="p" style="margin-top:16px"><table><thead><tr><th>Month</th><th>Kind</th><th class="num">Amount</th><th>Status</th></tr></thead><tbody>
<?php foreach ($snap['salaryPayments'] as $pay): ?><tr><td><?= e($pay['month']) ?></td><td><?= e($pay['kind']) ?></td><td class="num"><?= e(rs($pay['amount'])) ?></td><td><?= e($pay['status']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
