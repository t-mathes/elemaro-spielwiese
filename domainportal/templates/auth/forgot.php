<section class="container narrow section">
  <h1>Passwort vergessen</h1>
  <?php if ($sent): ?>
    <div class="notice notice-ok">Falls es ein Konto mit dieser Adresse gibt, haben wir dir einen Link zum Zurücksetzen geschickt.</div>
  <?php else: ?>
  <form class="card form" method="post" action="/passwort/vergessen">
    <?= csrf_field() ?>
    <label class="field"><span>E-Mail-Adresse</span><input type="email" name="email" required autocomplete="email"></label>
    <button class="btn btn-primary btn-block" type="submit">Link senden</button>
  </form>
  <?php endif; ?>
</section>
