<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Authenticator | ZAZ AGRICULTURE ERP</title>
<link rel="icon" type="image/svg+xml" href="<?= e($base) ?>/assets/logo.svg">
<link rel="stylesheet" href="<?= e($base) ?>/assets/css/variables.css">
<link rel="stylesheet" href="<?= e($base) ?>/assets/css/layout.css">
<link rel="stylesheet" href="<?= e($base) ?>/assets/css/components.css">
<link rel="stylesheet" href="<?= e($base) ?>/assets/css/theme.css">
</head>
<body>
<div class="login-page">
 <section class="login-hero">
  <div>
   <img src="<?= e($base) ?>/assets/logo.svg" alt="ZAZ Agriculture logo">
   <h1>ZAZ AGRICULTURE<br>Farm Control ERP</h1>
   <p>Enter the code from your authenticator app. This step uses the account you just signed in, not a role you choose.</p>
  </div>
  <small style="color:#c3c9e0">Internal-use ERP • Server session</small>
 </section>
 <section class="login-form">
  <form class="card" method="post" action="<?= e($base) ?>/login/totp" aria-labelledby="lt">
   <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
   <h2 id="lt" style="font-size:22px">Authenticator</h2>
   <p class="muted" style="margin:4px 0 18px">Six-digit code, or one recovery code.</p>
   <label for="code">Authenticator code</label>
   <div class="pin-wrap"><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000"></div>
   <div style="margin-top:12px"><label for="recovery">Recovery code</label><input id="recovery" name="recovery" type="text" autocomplete="off" maxlength="16" spellcheck="false"></div>
   <p class="neg small" role="alert" style="min-height:20px;margin:8px 0"><?= e($error) ?></p>
   <button class="btn" style="width:100%;padding:12px">Continue</button>
  </form>
 </section>
</div>
</body>
</html>
