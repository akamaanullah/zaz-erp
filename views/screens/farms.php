<div class="page-head"><div><p>Add farms, record acreage, and archive farms no longer in use. Hari land is total acres minus Seri.</p></div>
<a class="btn" id="add" data-open="add-dialog" href="<?= e(zaz_q($query, ['form' => 'add'])) ?>">Add farm</a></div>
<template id="add-dialog">
<form class="dialog" method="post" action="<?= e($base) ?>/api/farms/save">
<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<div class="form-grid">
<div><label>Farm name</label><input name="name" required maxlength="80"></div>
<div><label>Location</label><input name="location" maxlength="120"></div>
<div><label>Total acres</label><input name="acres" type="number" min="0.01" step="0.01" required></div>
<div><label>Seri acres</label><input name="seri" type="number" min="0" step="0.01" value="0" required></div>
</div>
<button class="btn">Add farm</button>
</form></template>
<div class="card" id="t"><table><thead><tr><th>Farm</th><th class="num">Total acres</th><th class="num">Seri</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($snap['farms'] as $farm): ?>
<tr><td><b><?= e($farm['name']) ?></b><br><small><?= e($farm['location'] ?: '—') ?></small></td>
<td class="num"><?= e((string)$farm['acres']) ?></td><td class="num"><?= e((string)$farm['seri']) ?></td>
<td><?= $farm['archived'] ? 'Archived' : 'Active' ?></td>
<td>
<button type="button" class="btn secondary sm" data-edit="<?= e($farm['id']) ?>" data-open="farm-<?= e($farm['id']) ?>">Acreage</button>
<button type="button" class="btn ghost sm" data-arch="<?= e($farm['id']) ?>" data-open="arch-<?= e($farm['id']) ?>"><?= $farm['archived'] ? 'Restore' : 'Archive' ?></button>
<template id="farm-<?= e($farm['id']) ?>"><form class="dialog" method="post" action="<?= e($base) ?>/api/farms/save"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= e($farm['id']) ?>"><input type="hidden" name="location" value="<?= e((string)$farm['location']) ?>"><label>Total acres</label><input name="acres" type="number" min="0.01" step="0.01" value="<?= e((string)$farm['acres']) ?>" required><label>Seri</label><input name="seri" type="number" min="0" step="0.01" value="<?= e((string)$farm['seri']) ?>" required><button class="btn">Save</button></form></template>
<template id="arch-<?= e($farm['id']) ?>"><form class="dialog" method="post" action="<?= e($base) ?>/api/farms/archive"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= e($farm['id']) ?>"><p><?= $farm['archived'] ? 'Restore' : 'Archive' ?> <?= e($farm['name']) ?>?</p><button class="btn"><?= $farm['archived'] ? 'Restore' : 'Archive' ?></button></form></template>
</td></tr>
<?php endforeach; ?>
</tbody></table></div>
