<?php
$stat = $pack['byFarm'][$ctx['farm']] ?? [];
$hariRows = $pack['hari'] ?? [];
$cats = $pack['costCats'][$ctx['farm']] ?? [];
$chart = [];
foreach ($ctx['farms'] as $name) {
    $row = $pack['byFarm'][$name] ?? [];
    if (((float)($row['sales'] ?? 0)) > 0 || ((float)($row['costs'] ?? 0)) > 0) {
        $chart[] = ['farm' => $name, 'sales' => (float)($row['sales'] ?? 0), 'costs' => (float)($row['costs'] ?? 0)];
    }
}
$farmMeta = null;
foreach ($snap['farms'] as $farmRow) {
    if ($farmRow['name'] === $ctx['farm']) {
        $farmMeta = $farmRow;
    }
}
?>
<p class="muted">Sales, costs, advances and Hari balances are for <b><?= e($ctx['season']) ?></b>. Stock remaining is the physical balance across seasons.</p>
<div class="grid g4">
<div class="card kpi"><div><div class="lbl">Total physical acres</div><div class="val"><?= e((string)($farmMeta['acres'] ?? 0)) ?></div><div class="sub">Seri <?= e((string)($farmMeta['seri'] ?? 0)) ?></div></div></div>
<div class="card kpi gold"><div><div class="lbl">Hari accounts</div><div class="val"><?= count(array_filter($snap['haris'], fn ($h) => $h['farm'] === $ctx['farm'])) ?></div></div></div>
<div class="card kpi blue"><div><div class="lbl">Recorded sales</div><div class="val"><?= e(rs($stat['sales'] ?? 0)) ?></div><div class="sub"><?= (int)($stat['saleCount'] ?? 0) ?> sale entries</div></div></div>
<div class="card kpi red"><div><div class="lbl">Recorded costs</div><div class="val"><?= e(rs($stat['costs'] ?? 0)) ?></div><div class="sub">Advances <?= e(rs($stat['advances'] ?? 0)) ?></div></div></div>
</div>
<div class="grid g3" style="margin-top:16px">
<div class="card span2"><div class="card-head"><h3>Sales vs costs by farm</h3><p><?= e($ctx['season']) ?></p></div><div id="dash-chart"></div></div>
<div class="card"><div class="card-head"><h3>Cost breakdown</h3><p><?= e($ctx['farm']) ?></p></div><div id="dash-donut"></div></div>
</div>
<script type="application/json" id="bar-data"><?= json_encode($chart, JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="donut-data"><?= json_encode($cats, JSON_UNESCAPED_UNICODE) ?></script>
<div class="card" style="margin-top:16px"><div class="card-head"><h3>Hari balances · <?= e($ctx['season']) ?></h3></div>
<div id="hb"><table><thead><tr><th>Hari</th><th class="num">Acres</th><th class="num">Hari share</th><th class="num">Deductions</th><th class="num">Advances</th><th class="num">Balance</th></tr></thead><tbody>
<?php foreach ($hariRows as $row): if ($row['farm'] !== $ctx['farm']) continue; ?>
<tr><td><?= e($row['name']) ?></td><td class="num"><?= e((string)$row['acres']) ?></td><td class="num"><?= e(rs($row['preview']['gross'])) ?></td><td class="num"><?= e(rs($row['preview']['ded'])) ?></td><td class="num"><?= e(rs($row['preview']['advance'])) ?></td><td class="num"><?= e(rs($row['preview']['balance'])) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<div class="card" style="margin-top:16px"><div class="card-head"><h3>Farm stock remaining (all seasons)</h3></div><ul class="list">
<?php foreach ($snap['items'] as $item): $left = $snap['summary']['stock'][$ctx['farm']][$item]['left'] ?? 0; ?>
<li><b><?= e($item) ?></b> <?= e((string)$left) ?></li>
<?php endforeach; ?>
</ul></div>
