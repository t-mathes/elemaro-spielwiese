<section class="container narrow section">
  <h1>Willkommen zurück</h1>
  <?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?>
  <form class="card form" method="post" action="/anmelden">
    <?= csrf_field() ?>
    <label class="field"><span>E-Mail-Adresse</span><input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email"></label>
    <label class="field"><span>Passwort</span><input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-primary btn-block" type="submit">Anmelden</button>
    <p class="muted center"><a href="/passwort/vergessen">Passwort vergessen?</a> · <a href="/registrieren">Konto erstellen</a></p>
  </form>
</section>
