<?php
require __DIR__ . '/_top.php';
$showAuth = (($_SESSION['show_auth'][0] ?? null) === (int)$d['id']) ? $_SESSION['show_auth'][1] : null;
unset($_SESSION['show_auth']);
$action = fn(string $p) => '/konto/domains/' . (int)$d['id'] . '/' . $p;
$types = ['A', 'AAAA', 'CNAME', 'MX', 'TXT'];
?>
<p class="crumb"><a href="/konto">Meine Domains</a> / <?= e($d['display']) ?></p>
<div class="page-head"><h1><?= e($d['display']) ?></h1><span class="badge ok"><?= $d['status'] === 'active' ? 'Aktiv' : e($d['status']) ?></span></div>

<?php foreach ($errors as $er): ?><div class="notice notice-error"><?= e($er) ?></div><?php endforeach; ?>

<div class="card">
  <div class="card-head"><h2>Übersicht</h2></div>
  <dl class="facts">
    <div><dt>Registriert am</dt><dd><?= e(fmt_date($d['registered_at'])) ?></dd></div>
    <div><dt>Läuft ab am</dt><dd><?= e(fmt_date($d['expires_at'])) ?></dd></div>
    <?php if ($renew): ?><div><dt>Verlängerung</dt><dd><?= e($renew) ?> / Jahr</dd></div><?php endif; ?>
  </dl>
  <form method="post" action="<?= $action('einstellung') ?>" class="toggle-row">
    <?= csrf_field() ?><input type="hidden" name="setting" value="auto_renew">
    <div><strong>Automatisch verlängern</strong><p class="muted">Die Domain wird vor Ablauf verlängert, damit sie nicht verloren geht.</p></div>
    <label class="switch"><input type="checkbox" name="value" value="1" data-autosubmit <?= $d['auto_renew'] ? 'checked' : '' ?>><span></span></label>
    <noscript><button class="btn btn-ghost">Speichern</button></noscript>
  </form>
</div>

<div class="card" id="nameserver">
  <div class="card-head"><h2>Nameserver</h2></div>
  <form method="post" action="<?= $action('nameserver') ?>" class="form">
    <?= csrf_field() ?>
    <?php for ($i = 0; $i < 5; $i++): ?>
      <label class="field"><span>Nameserver <?= $i + 1 ?><?= $i < 2 ? '' : ' <small>(optional)</small>' ?></span>
        <input name="ns[]" value="<?= e($d['ns'][$i] ?? '') ?>" placeholder="ns<?= $i + 1 ?>.beispiel.de" <?= $i < 2 ? 'required' : '' ?>></label>
    <?php endfor; ?>
    <div class="actions"><button class="btn btn-primary" type="submit">Speichern</button>
      <button class="btn btn-ghost" type="submit" name="reset" value="1" formnovalidate>Auf Standard zurücksetzen</button></div>
  </form>
</div>

<div class="card" id="dns">
  <div class="card-head"><h2>DNS-Einträge</h2></div>
  <?php if (!$defaultNs): ?>
    <p class="muted">Du nutzt eigene Nameserver. DNS-Einträge pflegst du dort. Setze die Nameserver auf Standard zurück, um sie hier zu verwalten.</p>
  <?php else: ?>
    <?php if ($dns): ?>
    <div class="table-wrap"><table class="table compact">
      <thead><tr><th>Typ</th><th>Host</th><th>Wert</th><th>TTL</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($dns as $r): ?>
        <tr><td><span class="badge"><?= e($r['type']) ?></span></td><td><?= e($r['host']) ?></td>
          <td class="wrap"><?= $r['prio'] !== null ? e($r['prio']) . ' ' : '' ?><?= e($r['value']) ?></td><td><?= (int)$r['ttl'] ?></td>
          <td class="right"><form method="post" action="<?= $action('dns/' . (int)$r['id'] . '/loeschen') ?>"><?= csrf_field() ?><button class="link-danger" type="submit">Löschen</button></form></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php else: ?><p class="muted">Noch keine Einträge.</p><?php endif; ?>
    <form method="post" action="<?= $action('dns') ?>" class="dns-form">
      <?= csrf_field() ?>
      <label class="field"><span>Typ</span><select name="type" data-dns-type><?php foreach ($types as $t): ?><option><?= $t ?></option><?php endforeach; ?></select></label>
      <label class="field"><span>Host</span><input name="host" placeholder="@ / www"></label>
      <label class="field grow"><span>Wert</span><input name="value" required placeholder="203.0.113.10"></label>
      <label class="field prio" hidden><span>Prio</span><input name="prio" type="number" value="10" min="0"></label>
      <label class="field ttl"><span>TTL</span><input name="ttl" type="number" value="3600" min="60" max="86400"></label>
      <button class="btn btn-primary" type="submit">Hinzufügen</button>
    </form>
  <?php endif; ?>
</div>

<div class="card" id="transfer">
  <div class="card-head"><h2>Transfer & Sicherheit</h2></div>
  <form method="post" action="<?= $action('einstellung') ?>" class="toggle-row">
    <?= csrf_field() ?><input type="hidden" name="setting" value="lock">
    <div><strong>Transfersperre</strong><p class="muted">Schützt vor unbefugten Umzügen. Zum Wechseln zu einem anderen Anbieter bitte ausschalten.</p></div>
    <label class="switch"><input type="checkbox" name="value" value="1" data-autosubmit <?= $d['locked'] ? 'checked' : '' ?>><span></span></label>
    <noscript><button class="btn btn-ghost">Speichern</button></noscript>
  </form>
  <div class="toggle-row">
    <div><strong>Auth-Code (EPP)</strong><p class="muted">Wird für den Umzug zu einem anderen Anbieter benötigt. Gib ihn niemandem weiter, den du nicht kennst.</p>
      <?php if ($showAuth): ?><code class="authcode"><?= e($showAuth) ?></code><?php endif; ?></div>
    <form method="post" action="<?= $action('authcode') ?>"><?= csrf_field() ?><button class="btn btn-ghost" type="submit"><?= $showAuth ? 'Neu abrufen' : 'Anzeigen' ?></button></form>
  </div>
</div>

<div class="card" id="inhaber">
  <div class="card-head"><h2>Inhaberdaten</h2></div>
  <form method="post" action="<?= $action('inhaber') ?>" class="form">
    <?= csrf_field() ?>
    <?php $form = $form; require dirname(__DIR__) . '/_profile_fields.php'; ?>
    <div class="actions"><button class="btn btn-primary" type="submit">Speichern</button></div>
  </form>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
