<?php use App\{Config, Session, Users}; $u = Users::current(); $brand = Config::get('brand'); ?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ? "$title · $brand" : "$brand – Domains einfach registrieren") ?></title>
<meta name="description" content="Finde und registriere deine Wunschdomain in wenigen Klicks.">
<meta name="csrf-token" content="<?= e(Session::csrf()) ?>">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='9' fill='%23141414'/%3E%3Ccircle cx='16' cy='16' r='6' fill='none' stroke='%23c6f35b' stroke-width='3'/%3E%3C/svg%3E">
<link rel="stylesheet" href="/assets/css/app.css">
<script src="/assets/js/app.js" defer></script>
<?php if (!empty($scripts)) foreach ($scripts as $s): ?><script src="<?= e($s) ?>" defer></script><?php endforeach; ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<header class="site-header">
  <div class="container bar">
    <a class="brand" href="/"><span class="brand-mark"></span><?= e($brand) ?></a>
    <nav class="nav">
      <?php if ($u): ?>
        <a class="btn btn-ghost" href="/konto">Mein Konto</a>
        <form method="post" action="/abmelden"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Abmelden</button></form>
      <?php else: ?>
        <a class="btn btn-ghost" href="/anmelden">Anmelden</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main>
<?php foreach (Session::takeFlash() as $f): ?>
  <div class="container"><div class="notice notice-<?= e($f['type']) ?>" role="status"><?= e($f['msg']) ?></div></div>
<?php endforeach; ?>
<?= $content ?>
</main>
<footer class="site-footer">
  <div class="container">
    <span>© <?= date('Y') ?> <?= e($brand) ?> · Preise inkl. MwSt.</span>
    <span class="links"><a href="/impressum">Impressum</a><a href="/datenschutz">Datenschutz</a><a href="/agb">AGB</a></span>
  </div>
</footer>
</body>
</html>
