<?php $rows = $list['rows'] ?? []; ?>
<div class="page-head"><div><p>Daily work is filtered by date, not by crop season.<?php if (in_array('farm.daily.write', $snap['abilities'] ?? [], true)): ?> Supervisors and office can submit.<?php else: ?> You can view submissions.<?php endif; ?></p></div>
<?php if (in_array('farm.daily.write', $snap['abilities'] ?? [], true)): ?><a class="btn" id="add" data-open="add-dialog" href="<?= e(zaz_q($query, ['form' => 'add'])) ?>">Submit today’s work</a><?php endif; ?>
</div>
<div class="row" style="margin-bottom:12px">
<a class="btn secondary" data-range="24h" href="<?= e(zaz_q($query, ['range' => '24h'])) ?>">Last 24 hours</a>
<a class="btn secondary" data-range="date" href="<?= e(zaz_q($query, ['range' => 'date'])) ?>">By date</a>
<a class="btn secondary" data-range="all" href="<?= e(zaz_q($query, ['range' => 'all'])) ?>">All</a>
<?php if (($query['range'] ?? '') === 'date'): ?><form method="get"><input type="hidden" name="range" value="date"><input type="date" id="fDate" name="date" value="<?= e((string)($query['date'] ?? date('Y-m-d'))) ?>" aria-label="Work date"></form><?php endif; ?>
</div>
<template id="add-dialog">
<form class="dialog" method="post" action="<?= e($base) ?>/api/daily/create">
<input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="farm" value="<?= e($ctx['farm']) ?>">
<div class="form-grid">
<div><label>Date</label><input name="date" type="date" value="<?= e(date('Y-m-d')) ?>" required></div>
<div><label>Work type</label><select name="type"><option>Irrigation</option><option>Land preparation</option><option>Other</option></select></div>
<div><label>Work description</label><textarea name="desc" required></textarea></div>
</div>
<button class="btn">Submit update</button>
</form></template>
<div id="timeline"><ul class="timeline">
<?php
$range = (string)($query['range'] ?? 'all');
$day = (string)($query['date'] ?? '');
foreach ($rows as $row):
    $stamp = strtotime((string)$row['at']);
    if ($range === '24h' && $stamp !== false && $stamp < time() - 86400) continue;
    if ($range === 'date' && $day !== '' && $row['date'] !== $day) continue;
?>
<li><b><?= e($row['type']) ?></b> <?= e($row['farm']) ?> — <?= e($row['desc']) ?></li>
<?php endforeach; ?>
</ul></div>
