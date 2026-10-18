<section class="container section account">
  <aside class="side">
    <p class="side-user"><?= e($user['email']) ?></p>
    <nav>
      <a href="/konto" class="<?= $nav === 'domains' ? 'active' : '' ?>">Meine Domains</a>
      <a href="/konto/bestellungen" class="<?= $nav === 'orders' ? 'active' : '' ?>">Bestellungen</a>
      <a href="/konto/profil" class="<?= $nav === 'profile' ? 'active' : '' ?>">Meine Daten</a>
    </nav>
  </aside>
  <div class="acc-main">
