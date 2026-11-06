<div class="page-head"><div><p>Office can add accountant, supervisor and office users. Only an Owner can create or change an Owner account.</p></div>
<a class="btn" id="add" data-open="add-dialog" href="<?= e(zaz_q($query, ['form' => 'add'])) ?>">Add employee</a></div>
<template id="add-dialog">
<form class="dialog" method="post" action="<?= e($base) ?>/api/employees/create">
<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<div class="form-grid">
<div><label>Full name</label><input name="name" required maxlength="80"></div>
<div><label>Username</label><input name="username" required pattern="[A-Za-z0-9._\-]{3,30}"></div>
<div><label>Role</label><select name="role">
<option value="accountant">Farm Accountant</option>
<option value="supervisor">Farm Supervisor</option>
<option value="office">Office</option>
<option value="kamdar">Kamdar (no login)</option>
<option value="owner" <?= ($me['role'] ?? '') !== 'owner' ? 'disabled' : '' ?>>Owner</option>
</select></div>
<div><label>Assigned farm</label><select name="farm"><?php foreach ($snap['farms'] as $farm): if ($farm['archived']) continue; ?><option><?= e($farm['name']) ?></option><?php endforeach; ?></select></div>
<div><label>Monthly salary</label><input name="salary" type="number" min="0" value="0"></div>
<div><label>Pay type</label><select name="payType"><option>Monthly salary</option><option>Daily wage</option><option>Contract</option></select></div>
</div>
<button class="btn">Add employee</button>
</form></template>
<div class="card" id="t"><table><thead><tr><th>Name</th><th>Role</th><th>Farm</th><th class="num">Salary</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($snap['users'] as $person): ?>
<tr><td><b><?= e($person['name']) ?></b><br><small><?= $person['login'] ? '@' . e($person['username']) : 'No login' ?></small></td>
<td><?= e($person['role']) ?></td><td><?= e($person['farm'] ?: 'All farms') ?></td><td class="num"><?= e(rs($person['salary'])) ?></td>
<td><?= $person['active'] ? 'Active' : 'Disabled' ?></td>
<td>
<?php if ($person['login'] && !($me['role'] !== 'owner' && $person['role'] === 'owner')): ?>
<button type="button" class="btn sm" data-tog="<?= e($person['id']) ?>" data-open="tog-<?= e($person['id']) ?>"><?= $person['active'] ? 'Disable' : 'Enable' ?></button>
<template id="tog-<?= e($person['id']) ?>"><form class="dialog" method="post" action="<?= e($base) ?>/api/employees/status"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= e($person['id']) ?>"><p><?= $person['active'] ? 'Disable' : 'Enable' ?> <?= e($person['name']) ?>?</p><button class="btn"><?= $person['active'] ? 'Disable' : 'Enable' ?></button></form></template>
<?php endif; ?>
</td></tr>
<?php endforeach; ?>
</tbody></table></div>
