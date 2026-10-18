<?php require __DIR__ . '/auth/_steps.php'; steps($step); ?>
<section class="container section checkout">
  <div class="co-main">
    <?php foreach ($errors as $er): ?><div class="notice notice-error"><?= e($er) ?></div><?php endforeach; ?>
    <?php if ($step === 2): ?>
      <h1>Deine Daten</h1>
      <p class="lead">Diese Angaben werden als Domaininhaber hinterlegt – bitte vollständig und korrekt, das ist rechtlich vorgeschrieben.</p>
      <form class="card form" method="post" action="/bestellung/daten">
        <?= csrf_field() ?>
        <?php require __DIR__ . '/_profile_fields.php'; ?>
        <button class="btn btn-primary btn-block" type="submit">Weiter zur Zahlung</button>
      </form>
    <?php else: ?>
      <h1>Bestellung abschließen</h1>
      <div class="card">
        <div class="card-head"><h2>Inhaber</h2><a href="/bestellung?daten=1">Ändern</a></div>
        <p class="addr"><?= e(trim(($user['company'] ? $user['company'] . ', ' : '') . $user['first_name'] . ' ' . $user['last_name'])) ?><br>
          <?= e($user['street']) ?>, <?= e($user['zip']) ?> <?= e($user['city']) ?>, <?= e(App\Users::COUNTRIES[$user['country']] ?? $user['country']) ?><br>
          <?= e($user['email']) ?> · <?= e($user['phone']) ?></p>
      </div>
      <form class="card form" method="post" action="/bestellung/zahlen">
        <?= csrf_field() ?>
        <h2>Zahlung</h2>
        <p class="muted">Du wirst zu PayPal weitergeleitet und kommst nach der Zahlung automatisch hierher zurück. Deine Domains werden danach sofort registriert.</p>
        <label class="check"><input type="checkbox" name="terms" required> <span>Ich akzeptiere die <a href="/agb" target="_blank">AGB</a>, habe die <a href="/datenschutz" target="_blank">Datenschutzhinweise</a> gelesen und stimme zu, dass die Registrierung sofort beginnt (Erlöschen des Widerrufsrechts bei vollständiger Leistung).</span></label>
        <button class="btn btn-paypal btn-block" type="submit">Jetzt mit PayPal bezahlen · <?= e(money($cart['total'])) ?></button>
      </form>
    <?php endif; ?>
  </div>
  <aside class="co-side card">
    <h2>Dein Warenkorb</h2>
    <ul class="summary">
      <?php foreach ($cart['lines'] as $l): ?>
        <li><span><?= e($l['display']) ?><small>1 Jahr · danach <?= e(money($l['renew_cents'])) ?>/Jahr</small></span><b><?= e(money($l['price_cents'])) ?></b></li>
      <?php endforeach; ?>
    </ul>
    <div class="sum-total"><span>Gesamt <small>inkl. <?= e(number_format((float)App\Config::get('vat_rate'), 0)) ?>% MwSt.</small></span><b><?= e(money($cart['total'])) ?></b></div>
    <a class="muted" href="/">← Weitere Domains hinzufügen</a>
  </aside>
</section>
