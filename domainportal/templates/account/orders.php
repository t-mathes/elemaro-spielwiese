<?php require __DIR__ . '/_top.php'; ?>
<div class="page-head"><h1>Bestellungen</h1></div>
<?php if (!$orders): ?><div class="card empty"><p class="muted">Noch keine Bestellungen.</p></div><?php endif; ?>
<?php foreach ($orders as $o): ?>
  <div class="card">
    <div class="card-head"><h2>Bestellung #<?= (int)$o['id'] ?></h2><span class="muted"><?= e(fmt_date($o['paid_at'] ?: $o['created_at'])) ?> · <?= e(money((int)$o['total_cents'])) ?></span></div>
    <ul class="summary">
      <?php foreach ($o['items'] as $it): ?><li><span><?= e(App\Domain::display($it['name']) . '.' . $it['tld']) ?></span><b><?= e(money((int)$it['price_cents'])) ?></b></li><?php endforeach; ?>
    </ul>
  </div>
<?php endforeach; ?>
<?php require __DIR__ . '/_bottom.php'; ?>
