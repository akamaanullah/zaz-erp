<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> | ZAZ AGRICULTURE ERP</title>
<link rel="icon" type="image/svg+xml" href="<?= e($base) ?>/assets/logo.svg"><link rel="alternate icon" href="<?= e($base) ?>/assets/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
<noscript><p style="padding:20px">This application needs JavaScript enabled.</p></noscript>
<script src="<?= e($base) ?>/assets/js/utils.js"></script>
<script src="<?= e($base) ?>/assets/js/store.js"></script>
<script src="<?= e($base) ?>/assets/js/app.js"></script>
<script src="<?= e($base) ?>/assets/js/pages/<?= e($page) ?>.js"></script>
</body>
</html>
