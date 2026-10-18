<?php require __DIR__ . '/_steps.php'; steps(1); ?>
<section class="container narrow section">
  <h1>Konto erstellen</h1>
  <p class="lead">Mit einem Konto verwaltest du später alle deine Domains an einem Ort.</p>
  <?php foreach ($errors as $er): ?><div class="notice notice-error"><?= e($er) ?></div><?php endforeach; ?>
  <form class="card form" method="post" action="/registrieren">
    <?= csrf_field() ?>
    <label class="field"><span>E-Mail-Adresse</span><input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email"></label>
    <label class="field"><span>Passwort <small>(mind. 10 Zeichen)</small></span><input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
    <label class="check"><input type="checkbox" name="terms" required> <span>Ich akzeptiere die <a href="/agb" target="_blank">AGB</a> und habe die <a href="/datenschutz" target="_blank">Datenschutzhinweise</a> gelesen.</span></label>
    <button class="btn btn-primary btn-block" type="submit">Konto erstellen</button>
    <p class="muted center">Schon registriert? <a href="/anmelden">Anmelden</a></p>
  </form>
</section>
