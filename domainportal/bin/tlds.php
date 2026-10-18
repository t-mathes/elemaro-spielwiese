#!/usr/bin/env php
<?php
// Verwaltung der angebotenen Domainendungen.
//   php bin/tlds.php list
//   php bin/tlds.php set <endung> <preis_eur> <verlaengerung_eur> [sortierung]   (Preise brutto, z. B. 8.99)
//   php bin/tlds.php off <endung>   |   php bin/tlds.php on <endung>   |   php bin/tlds.php remove <endung>
declare(strict_types=1);

$root = dirname(__DIR__);
foreach (['Core', 'helpers'] as $f) {
    require "$root/src/$f.php";
}
App\Config::load(require "$root/config.php");
use App\Db;

[$self, $cmd, $tld] = $argv + [null, 'list', null];
$tld = $tld ? strtolower(ltrim($tld, '.')) : null;
$cents = fn(string $v) => (int)round((float)str_replace(',', '.', $v) * 100);

switch ($cmd) {
    case 'set':
        if (!$tld || !isset($argv[3], $argv[4]) || !preg_match('/^[a-z0-9.-]{2,30}$/', $tld)) {
            fwrite(STDERR, "Aufruf: tlds.php set <endung> <preis> <verlaengerung> [sortierung]\n");
            exit(1);
        }
        $sort = (int)($argv[5] ?? 100);
        Db::run('DELETE FROM tlds WHERE tld = ?', [$tld]);
        Db::run('INSERT INTO tlds (tld, price_cents, renew_cents, active, sort) VALUES (?,?,?,1,?)', [$tld, $cents($argv[3]), $cents($argv[4]), $sort]);
        echo "Gespeichert: .$tld\n";
        break;
    case 'on': case 'off':
        echo Db::run('UPDATE tlds SET active = ? WHERE tld = ?', [$cmd === 'on' ? 1 : 0, $tld]) ? "OK\n" : "Endung nicht gefunden\n";
        break;
    case 'remove':
        echo Db::run('DELETE FROM tlds WHERE tld = ?', [$tld]) ? "Entfernt\n" : "Endung nicht gefunden\n";
        break;
    default:
        foreach (Db::all('SELECT * FROM tlds ORDER BY sort, tld') as $t) {
            printf("%-10s %9s %9s  %s\n", '.' . $t['tld'], money((int)$t['price_cents']), money((int)$t['renew_cents']), $t['active'] ? 'aktiv' : 'aus');
        }
}
