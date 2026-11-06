<?php
$hariRows = array_values(array_filter($pack['hari'] ?? [], fn ($row) => $row['farm'] === $ctx['farm']));
$selected = (string)($query['hari'] ?? ($hariRows[0]['id'] ?? ''));
$detail = null;
if ($selected !== '') {
    try {
        $detail = (new App\Services\ReadModel())->ledger($account, (int)$selected, true, $ctx['season']);
    } catch (Throwable $e) {
        $detail = null;
    }
}
?>
<div class="page-head"><div><p>Hari settlement for <b><?= e($ctx['farm']) ?></b> · <b><?= e($ctx['season']) ?></b>. The printed statement uses approved rows only.</p></div></div>
<div class="card" id="t"><table><thead><tr><th>Hari</th><th class="num">Gross</th><th class="num">Deductions</th><th class="num">Advances</th><th class="num">Net</th><th></th></tr></thead><tbody>
<?php foreach ($hariRows as $row): $final = $row['final']; ?>
<tr><td><b><?= e($row['name']) ?></b></td><td class="num"><?= e(rs($final['gross'])) ?></td><td class="num"><?= e(rs($final['ded'])) ?></td><td class="num"><?= e(rs($final['advance'])) ?></td><td class="num"><?= e(rs($final['balance'])) ?></td>
<td><a class="btn sm" data-print="<?= e($row['id']) ?>" href="<?= e(zaz_q($query, ['hari' => $row['id']])) ?>">Sindhi statement</a></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if ($detail): ?>
<div id="printview">
<h2>حتمي کاتو: صرف آفيس جي منظور ٿيل داخلائون شامل آهن</h2>
<p><?= e($ctx['farm']) ?> · <?= e($ctx['season']) ?></p>
<p>آمدني <?= e(rs($detail['gross'])) ?> — خرچ <?= e(rs($detail['ded'])) ?> — اڳواٽ <?= e(rs($detail['advance'])) ?> — باقي <?= e(rs($detail['balance'])) ?></p>
</div>
<?php endif; ?>
