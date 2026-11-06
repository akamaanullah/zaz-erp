<div class="page-head"><div><p>Append-only record of who changed what. This list is not filtered by season.</p></div>
<a class="btn secondary" id="csv" href="<?= e(zaz_q($query, ['export' => 'csv'])) ?>">Export CSV</a></div>
<div class="card" id="t"><table><thead><tr><th>When</th><th>User</th><th>Event</th></tr></thead><tbody>
<?php foreach ($list['rows'] ?? [] as $row): ?><tr><td><?= e($row['at']) ?></td><td><?= e($row['by']) ?></td><td><?= e($row['msg']) ?></td></tr><?php endforeach; ?>
</tbody></table><?php require project_root() . '/views/partials/pager.php'; ?></div>
