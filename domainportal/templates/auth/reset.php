<section class="container narrow section">
  <h1>Neues Passwort</h1>
  <?php if (!$valid): ?>
    <div class="notice notice-error">Dieser Link ist ungültig oder abgelaufen. <a href="/passwort/vergessen">Neuen Link anfordern</a></div>
  <?php else: ?>
    <?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?>
    <form class="card form" method="post" action="/passwort/neu/<?= e($tok) ?>">
      <?= csrf_field() ?>
      <label class="field"><span>Neues Passwort <small>(mind. 10 Zeichen)</small></span><input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
      <button class="btn btn-primary btn-block" type="submit">Passwort speichern</button>
    </form>
  <?php endif; ?>
</section>
