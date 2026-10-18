<?php require __DIR__ . '/_top.php'; ?>
<div class="page-head"><h1>Meine Domains</h1><a class="btn btn-primary" href="/">+ Domain hinzufügen</a></div>
<?php if (!$domains): ?>
  <div class="card empty"><h2>Noch keine Domains</h2><p class="muted">Suche deine erste Wunschdomain – das dauert nur Sekunden.</p><a class="btn btn-primary" href="/">Domain suchen</a></div>
<?php else: ?>
  <div class="card table-wrap">
    <table class="table">
      <thead><tr><th>Domain</th><th>Status</th><th>Läuft ab</th><th>Verlängerung</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($domains as $d): $days = (int)floor((strtotime($d['expires_at'] . ' UTC') - time()) / 86400); ?>
        <tr>
          <td data-l="Domain"><a class="dom" href="/konto/domains/<?= (int)$d['id'] ?>"><?= e(App\Domain::display($d['name']) . '.' . $d['tld']) ?></a></td>
          <td data-l="Status"><span class="badge <?= $d['status'] === 'active' ? 'ok' : '' ?>"><?= $d['status'] === 'active' ? 'Aktiv' : e($d['status']) ?></span></td>
          <td data-l="Läuft ab"><?= e(fmt_date($d['expires_at'])) ?><?php if ($days <= 30): ?> <small class="bad">(<?= max(0, $days) ?> Tage)</small><?php endif; ?></td>
          <td data-l="Verlängerung"><?= $d['auto_renew'] ? 'Automatisch' : 'Manuell' ?></td>
          <td class="right"><a class="btn btn-ghost" href="/konto/domains/<?= (int)$d['id'] ?>">Verwalten</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/_bottom.php'; ?>
