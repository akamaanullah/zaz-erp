<div class="page-head"><div><p>Hari share for new expenses. Seri is always 100% zamindar. Existing rows keep their snapshot.</p></div></div>
<div class="card" id="t"><table><thead><tr><th>Expense</th><th class="num">Hari pays</th><th></th></tr></thead><tbody>
<?php foreach ($snap['rules'] as $name => $share): $ruleKey = 'rule-' . substr(sha1((string)$name), 0, 12); ?>
<tr><td><b><?= e((string)$name) ?></b></td><td class="num"><?= (int)$share ?>%</td>
<td><button type="button" class="btn ghost sm" data-k="<?= e((string)$name) ?>" data-open="<?= e($ruleKey) ?>">Change</button>
<template id="<?= e($ruleKey) ?>"><form class="dialog" method="post" action="<?= e($base) ?>/api/rules/update"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="category" value="<?= e((string)$name) ?>"><label>Hari share</label><select name="Share"><option value="100">100%</option><option value="50" <?= (int)$share === 50 ? 'selected' : '' ?>>50%</option><option value="0">0%</option></select><button class="btn">Update rule</button></form></template>
</td></tr>
<?php endforeach; ?>
</tbody></table></div>
