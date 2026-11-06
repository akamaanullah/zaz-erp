<div class="page-head"><div><p>Changing a rate affects new entries only. The “used in” count is every season, because this screen has no season selector.</p></div>
<a class="btn" id="add" data-open="add-dialog" href="<?= e(zaz_q($query, ['form' => 'add'])) ?>">Add category</a></div>
<template id="add-dialog">
<form class="dialog" method="post" action="<?= e($base) ?>/api/rates/save">
<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<div class="form-grid">
<div><label>Category name</label><input name="name" required maxlength="60"></div>
<div><label>Unit</label><select name="unit"><option>per acre</option><option>per bag</option><option>per hour</option><option>lump sum</option></select></div>
<div><label>Default rate</label><input name="rate" type="number" min="0" step="0.01" required></div>
<div><label>Hari share</label><select name="share"><option value="100">100%</option><option value="50" selected>50%</option><option value="0">0%</option></select></div>
</div>
<button class="btn">Add category</button>
</form></template>
<div class="card" id="t"><table><thead><tr><th>Category</th><th>Unit</th><th class="num">Rate</th><th class="num">Hari pays</th><th></th></tr></thead><tbody>
<?php foreach ($snap['categories'] as $cat): ?>
<tr><td><b><?= e($cat['name']) ?></b></td><td><?= e($cat['unit']) ?></td><td class="num"><?= e(rs($cat['rate'])) ?></td><td class="num"><?= (int)$cat['share'] ?>%</td>
<td><button type="button" class="btn secondary sm" data-edit="<?= e($cat['id']) ?>" data-open="rate-<?= e($cat['id']) ?>">Edit</button>
<template id="rate-<?= e($cat['id']) ?>"><form class="dialog" method="post" action="<?= e($base) ?>/api/rates/save"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= e($cat['id']) ?>"><label>Name</label><input name="name" value="<?= e($cat['name']) ?>" required><input type="hidden" name="unit" value="<?= e($cat['unit']) ?>"><input type="hidden" name="share" value="<?= (int)$cat['share'] ?>"><label>Rate</label><input name="rate" type="number" min="0" step="0.01" value="<?= e((string)$cat['rate']) ?>" required><button class="btn">Save changes</button></form></template></td></tr>
<?php endforeach; ?>
</tbody></table></div>
