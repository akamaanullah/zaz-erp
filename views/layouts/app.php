<?php
/** @var string $screen */
/** @var string $title */
/** @var array $ctx */
/** @var array $snap */
/** @var array|null $list */
/** @var array $query */
/** @var array $nav */
/** @var array $me */
/** @var string $flash */
/** @var string $formError */
/** @var bool $showForm */
$theme = ($snap['settings']['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
$noCtx = in_array($screen, ['farms', 'employees', 'salaries', 'godown', 'daily', 'rates', 'rules', 'audit', 'settings'], true);
$seasonOptions = $ctx['seasons'];
if (!empty($snap['summary']['seasons']['No season'])) {
    $seasonOptions[] = 'No season';
}
$pack = $snap['summary']['seasons'][$ctx['season']] ?? ['byFarm' => [], 'costCats' => [], 'expenseCats' => [], 'salesByCrop' => [], 'hari' => []];
?>
<!doctype html>
<html lang="<?= e($lang ?? 'en') ?>" data-theme="<?= e($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> | ZAZ AGRICULTURE ERP</title>
<link rel="icon" type="image/svg+xml" href="<?= e($base) ?>/assets/logo.svg">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Noto+Naskh+Arabic:wght@400;600;700&display=swap">
<link rel="stylesheet" href="<?= e($base) ?>/assets/css/variables.css">
<link rel="stylesheet" href="<?= e($base) ?>/assets/css/layout.css">
<link rel="stylesheet" href="<?= e($base) ?>/assets/css/components.css">
<link rel="stylesheet" href="<?= e($base) ?>/assets/css/theme.css">
<link rel="stylesheet" href="<?= e($base) ?>/assets/css/print.css">
<meta name="zaz-base" content="<?= e($base) ?>">
<meta name="zaz-csrf" content="<?= e($csrf) ?>">
</head>
<body>
<a href="#content" class="skip">Skip to content</a>
<div class="app">
<aside class="sidebar" aria-label="Main navigation">
<div class="brand"><img src="<?= e($base) ?>/assets/logo.svg" alt="ZAZ Agriculture logo"><div><b>ZAZ AGRICULTURE</b><small>Farm Control ERP</small></div></div>
<nav class="nav">
<?php foreach ($nav as $item): ?>
    <?php if (isset($item['group'])): ?><div class="nav-group"><?= e($item['group']) ?></div>
    <?php else: ?><a href="<?= e($base) ?>/<?= e($item['id']) ?>" class="<?= $item['active'] ? 'active' : '' ?>" <?= $item['active'] ? 'aria-current="page"' : '' ?>><span><?= e($item['label']) ?></span><?php if (!empty($item['count'])): ?><span class="count"><?= (int)$item['count'] ?></span><?php endif; ?></a>
    <?php endif; ?>
<?php endforeach; ?>
</nav>
<div class="sidebar-foot">Signed-in session • role checked on the server</div>
</aside>
<div class="backdrop" data-close-nav></div>
<div class="main">
<header class="topbar">
<button class="btn secondary icon menu-btn" id="menuBtn" type="button" aria-label="Open menu">Menu</button>
<div class="title"><div class="crumb">ZAZ ERP / <?= e($title) ?></div><h1><?= e($title) ?></h1></div>
<?php if (!$noCtx): ?>
<form class="ctx no-print" method="get" action="<?= e($base) ?>/<?= e($screen) ?>">
<select id="ctxFarm" name="farm" aria-label="Farm" <?= count($ctx['farms']) < 2 ? 'disabled' : '' ?>>
<?php foreach ($ctx['farms'] as $farmName): ?><option <?= $farmName === $ctx['farm'] ? 'selected' : '' ?>><?= e($farmName) ?></option><?php endforeach; ?>
<?php if ($ctx['farms'] === []): ?><option>No farm assigned</option><?php endif; ?>
</select>
<select id="ctxSeason" name="season" aria-label="Season">
<?php foreach ($seasonOptions as $seasonName): ?><option <?= $seasonName === $ctx['season'] ? 'selected' : '' ?>><?= e($seasonName) ?></option><?php endforeach; ?>
</select>
<noscript><button class="btn secondary sm">Apply</button></noscript>
</form>
<?php endif; ?>
<form method="post" action="<?= e($base) ?>/api/settings/prefs" class="no-print">
<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<input type="hidden" name="mode" value="<?= e($theme) ?>">
<select id="ctxLang" name="language" aria-label="Menu language" style="min-width:auto">
<option value="en" <?= ($lang ?? 'en') === 'en' ? 'selected' : '' ?>>EN</option>
<option value="ur" <?= ($lang ?? '') === 'ur' ? 'selected' : '' ?>>اردو</option>
<option value="sd" <?= ($lang ?? '') === 'sd' ? 'selected' : '' ?>>سنڌي</option>
</select>
</form>
<form method="post" action="<?= e($base) ?>/api/settings/prefs">
<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<input type="hidden" name="language" value="<?= e($lang ?? 'en') ?>">
<input type="hidden" name="mode" value="<?= $theme === 'dark' ? 'light' : 'dark' ?>">
<button class="btn secondary icon" id="themeBtn" type="submit" aria-label="Toggle dark mode"><?= $theme === 'dark' ? 'Light' : 'Dark' ?></button>
</form>
<div class="user-chip"><span class="avatar"><?= e(mb_strtoupper(mb_substr((string)$me['name'], 0, 1))) ?></span><div><b><?= e($me['name']) ?></b><br><small><?= e($me['role']) ?><?= $me['farm'] !== '' ? ' • ' . e($me['farm']) : '' ?></small></div></div>
<form method="post" action="<?= e($base) ?>/logout"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="btn secondary icon" id="logoutBtn" type="submit" aria-label="Log out">Log out</button></form>
</header>
<main id="content" class="content" tabindex="-1">
<?php if ($flash !== ''): ?><div class="toast"><?= e($flash) ?></div><?php endif; ?>
<?php if ($formError !== ''): ?><div class="toast error"><?= e($formError) ?></div><?php endif; ?>
<?php
$screenFile = project_root() . '/views/screens/' . $screen . '.php';
if (!is_file($screenFile)) {
    echo '<div class="card"><p>This screen is not available.</p></div>';
} else {
    require $screenFile;
}
?>
</main>
<footer class="footer">ZAZ AGRICULTURE • Farm Control ERP • Server session</footer>
</div>
</div>
<div class="modal-back" id="dialog-host" hidden></div>
<script src="<?= e($base) ?>/assets/js/utils.js"></script>
<script src="<?= e($base) ?>/assets/js/enhance.js"></script>
</body>
</html>
