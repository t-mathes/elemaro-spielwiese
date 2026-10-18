<?php $attention = $order['status'] === 'attention'; $paid = in_array($order['status'], ['completed', 'attention', 'paid'], true); ?>
<section class="container narrow center section">
  <div class="icon-circle <?= $paid && !$attention ? 'ok' : '' ?>"><?= $paid ? '✓' : '…' ?></div>
  <h1><?= $paid ? 'Danke für deine Bestellung!' : 'Bestellung nicht abgeschlossen' ?></h1>
  <p class="lead">Bestellung #<?= (int)$order['id'] ?> · <?= e(money((int)$order['total_cents'])) ?></p>
  <ul class="card summary left">
    <?php foreach ($items as $it): ?>
      <li><span><?= e(App\Domain::display($it['name']) . '.' . $it['tld']) ?><?php if ($it['status'] === 'failed'): ?><small class="bad">Registrierung nicht möglich – wir melden uns bei dir.</small><?php endif; ?></span>
        <b class="badge <?= $it['status'] === 'done' ? 'ok' : ($it['status'] === 'failed' ? 'bad' : '') ?>"><?= $it['status'] === 'done' ? 'Registriert' : ($it['status'] === 'failed' ? 'Problem' : 'Offen') ?></b></li>
    <?php endforeach; ?>
  </ul>
  <?php if ($paid): ?><p class="muted">Eine Bestätigung ist per E-Mail unterwegs.</p><?php endif; ?>
  <p><a class="btn btn-primary" href="/konto">Zu meinen Domains</a></p>
</section>
