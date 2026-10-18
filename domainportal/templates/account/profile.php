<?php require __DIR__ . '/_top.php'; ?>
<div class="page-head"><h1>Meine Daten</h1></div>
<?php foreach ($errors as $er): ?><div class="notice notice-error"><?= e($er) ?></div><?php endforeach; ?>
<form class="card form" method="post" action="/konto/profil">
  <?= csrf_field() ?>
  <label class="field"><span>E-Mail-Adresse</span><input value="<?= e($user['email']) ?>" disabled></label>
  <?php require dirname(__DIR__) . '/_profile_fields.php'; ?>
  <div class="actions"><button class="btn btn-primary" type="submit">Speichern</button></div>
</form>
<form class="card form" method="post" action="/konto/passwort">
  <?= csrf_field() ?>
  <h2>Passwort ändern</h2>
  <label class="field"><span>Aktuelles Passwort</span><input type="password" name="current" required autocomplete="current-password"></label>
  <label class="field"><span>Neues Passwort <small>(mind. 10 Zeichen)</small></span><input type="password" name="new" minlength="10" required autocomplete="new-password"></label>
  <div class="actions"><button class="btn btn-primary" type="submit">Passwort ändern</button></div>
</form>
<?php require __DIR__ . '/_bottom.php'; ?>
