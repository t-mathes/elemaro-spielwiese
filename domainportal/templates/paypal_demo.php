<section class="container narrow center section">
  <div class="notice notice-warn">Demo-Modus: PayPal ist nicht konfiguriert. Hier wird nichts bezahlt.</div>
  <h1>Demo-Zahlung</h1>
  <p class="lead"><?= e(money((int)$order['total_cents'])) ?> für Bestellung #<?= (int)$order['id'] ?></p>
  <p><a class="btn btn-paypal" href="/paypal/return?token=<?= e(urlencode($order['paypal_order_id'])) ?>">Zahlung simulieren</a></p>
  <p><a href="/paypal/cancel">Abbrechen</a></p>
</section>
