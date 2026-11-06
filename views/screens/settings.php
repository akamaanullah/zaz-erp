<div class="grid g2">
<div class="card"><h3>Appearance & language</h3>
<form method="post" action="<?= e($base) ?>/api/settings/prefs">
<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<div class="form-grid">
<div><label>Theme</label><select name="theme"><option value="light" <?= ($snap['settings']['theme'] ?? '') !== 'dark' ? 'selected' : '' ?>>Light</option><option value="dark" <?= ($snap['settings']['theme'] ?? '') === 'dark' ? 'selected' : '' ?>>Dark</option></select></div>
<div><label>Menu language</label><select name="lang"><option value="en">EN</option><option value="ur" <?= ($lang ?? '') === 'ur' ? 'selected' : '' ?>>اردو</option><option value="sd" <?= ($lang ?? '') === 'sd' ? 'selected' : '' ?>>سنڌي</option></select></div>
</div>
<button class="btn">Save preferences</button>
</form></div>
<div class="card"><h3>My account</h3><ul class="list"><li>Name <?= e($me['name']) ?></li><li>Username @<?= e($me['username']) ?></li><li>Role <?= e($me['role']) ?></li><li>Farm <?= e($me['farm'] ?: 'All farms') ?></li></ul></div>
<?php if ($snap['totpAvailable'] || in_array($me['role'], ['owner', 'office'], true)): ?>
<div class="card"><h3>Authenticator</h3><p id="totpBox"><?php if (!$snap['totpAvailable']): ?>Authenticator stays off until the server has ZAZ_APP_KEY set. No codes are generated without it.<?php else: ?>Authenticator can be enrolled by an Owner or Office user.<?php endif; ?></p></div>
<?php endif; ?>
<?php if (in_array('admin.backup', $snap['abilities'] ?? [], true)): ?>
<div class="card"><h3>Data backup</h3><p class="muted">Export does not include passwords. Import cannot change accounts.</p>
<div class="row"><a class="btn" id="exp" href="<?= e($base) ?>/api/backup">Export JSON backup</a>
<form method="post" action="<?= e($base) ?>/api/settings/import" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="file" id="imp" name="backup" accept="application/json"></form>
<?php if (($me['role'] ?? '') === 'owner'): ?><button class="btn danger" id="rst" type="button" disabled>Reset demo data</button><?php endif; ?>
</div></div>
<?php endif; ?>
</div>
