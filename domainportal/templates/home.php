<?php
use App\Domain;
$tlds = Domain::tlds();
?>
<section class="hero">
  <div class="glow" aria-hidden="true"></div>
  <div class="container narrow center">
    <p class="pill"><span class="dot"></span> Domains · einfach, fair, sofort</p>
    <h1>Deine Domain.<br><em>In Sekunden</em> gefunden.</h1>
    <p class="lead">Gib deinen Wunschnamen ein – wir zeigen dir sofort, welche Endungen noch frei sind.</p>

    <form id="check-form" class="search" action="/" method="get" role="search" autocomplete="off">
      <label class="sr" for="name">Domainname ohne Endung</label>
      <input id="name" name="name" type="text" inputmode="url" placeholder="deinname" maxlength="63"
             autocapitalize="off" autocorrect="off" spellcheck="false" autofocus
             value="<?= e($_GET['name'] ?? '') ?>">
      <button class="btn btn-primary" type="submit">Prüfen</button>
    </form>
    <p class="tld-line" aria-hidden="true">
      <?php foreach (array_slice($tlds, 0, 8) as $t): ?><span>.<?= e($t['tld']) ?> <b><?= e(money((int)$t['price_cents'])) ?></b></span><?php endforeach; ?>
    </p>
    <p id="form-error" class="field-error" role="alert" hidden></p>
  </div>

  <div class="container results-wrap">
    <div id="results" aria-live="polite"></div>
  </div>
</section>

<div id="cartbar" class="cartbar" hidden>
  <div class="container bar">
    <div class="cart-info"><strong id="cart-count">0 Domains</strong><span id="cart-list"></span></div>
    <div class="cart-action"><span id="cart-total" class="total"></span>
      <a class="btn btn-primary" href="/bestellung">Weiter zur Bestellung</a>
    </div>
  </div>
</div>
