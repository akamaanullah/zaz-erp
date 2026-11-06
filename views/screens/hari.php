<div class="page-head"><div><p>Hari accounts for <b><?= e($ctx['farm']) ?></b>. Maximum 25 physical acres. Reusing a plot in another season does not count the acres twice.</p></div>
<?php if (in_array('farm.hari.write', $snap['abilities'] ?? [], true)): ?><a class="btn" id="add" data-open="add-dialog" href="<?= e(zaz_q($query, ['form' => 'add'])) ?>">Add Hari</a><?php endif; ?>
</div>
<template id="add-dialog">
<form class="dialog" method="post" action="<?= e($base) ?>/api/hari/create">
<input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="farm" value="<?= e($ctx['farm']) ?>">
<div class="form-grid">
<div><label>Name</label><input name="Name" required></div>
<div><label>Address</label><input name="Address" required></div>
<div><label>Physical assigned acres</label><input name="Acres" type="number" min="0.01" max="25" step="0.01" required></div>
<div><label>Crop</label><input name="Crop" required></div>
<div><label>Season</label><select name="Season"><?php foreach ($ctx['seasons'] as $season): ?><option <?= $season === $ctx['season'] ? 'selected' : '' ?>><?= e($season) ?></option><?php endforeach; ?></select></div>
</div>
<button class="btn">Add Hari</button>
</form></template>
<div class="card" id="t"><table><thead><tr><th>Name</th><th>Address</th><th class="num">Physical acres</th></tr></thead><tbody>
<?php foreach ($snap['haris'] as $hari): if ($hari['farm'] !== $ctx['farm']) continue; ?>
<tr><td><b><?= e($hari['name']) ?></b></td><td><?= e($hari['address']) ?></td><td class="num"><?= e((string)$hari['physicalAcres']) ?> / 25</td></tr>
<?php endforeach; ?>
</tbody></table></div>
