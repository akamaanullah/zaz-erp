<?php
$pageNo = (int)($list['page'] ?? 1);
$per = (int)($list['per'] ?? 25);
$total = (int)($list['total'] ?? 0);
$pages = max(1, (int)ceil($total / max(1, $per)));
?>
<div class="table-meta">Showing <?= count($list['rows'] ?? []) ?> on this page • <?= $total ?> matching records</div>
<?php if ($pages > 1): ?>
<div class="pager">
<?php if ($pageNo > 1): ?><a class="btn secondary sm" data-pg="prev" href="<?= e(zaz_q($query, ['page' => $pageNo - 1])) ?>">Previous</a><?php endif; ?>
<span class="muted small">Page <?= $pageNo ?> of <?= $pages ?></span>
<?php if ($pageNo < $pages): ?><a class="btn secondary sm" data-pg="next" href="<?= e(zaz_q($query, ['page' => $pageNo + 1])) ?>">Next</a><?php endif; ?>
</div>
<?php endif; ?>
