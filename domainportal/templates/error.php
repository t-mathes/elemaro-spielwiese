<section class="container narrow center section">
  <p class="eyebrow">Fehler <?= (int)$status ?></p>
  <h1>Das hat nicht geklappt.</h1>
  <p class="lead"><?= e($message ?: 'Diese Seite ist nicht verfügbar.') ?></p>
  <p><a class="btn btn-primary" href="/">Zur Startseite</a></p>
</section>
