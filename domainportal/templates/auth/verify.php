<?php require __DIR__ . '/_steps.php'; ?>
<?php if ($state === 'pending'): steps(1); ?>
<section class="container narrow center section">
  <div class="icon-circle">✉</div>
  <h1>Bitte bestätige deine E-Mail</h1>
  <p class="lead">Wir haben dir einen Link an <strong><?= e($user['email']) ?></strong> geschickt. Klicke darauf, um fortzufahren. Diese Seite aktualisiert sich nicht von selbst – nach der Bestätigung geht es mit dem Link weiter.</p>
  <form method="post" action="/bestaetigen/senden"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Mail erneut senden</button></form>
</section>
<?php elseif ($state === 'done'): ?>
<section class="container narrow center section">
  <div class="icon-circle ok">✓</div>
  <h1>E-Mail bestätigt</h1>
  <p class="lead">Danke! Dein Konto ist freigeschaltet.</p>
  <p><a class="btn btn-primary" href="<?= $user ? (App\Cart::items() ? '/bestellung' : '/konto') : '/anmelden' ?>"><?= $user && App\Cart::items() ? 'Weiter zur Bestellung' : ($user ? 'Zu meinem Konto' : 'Anmelden') ?></a></p>
</section>
<?php else: ?>
<section class="container narrow center section">
  <h1>Link ungültig oder abgelaufen</h1>
  <p class="lead">Melde dich an – dort kannst du eine neue Bestätigungsmail anfordern.</p>
  <p><a class="btn btn-primary" href="/anmelden">Anmelden</a></p>
</section>
<?php endif; ?>
