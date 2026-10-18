<?php
declare(strict_types=1);

namespace App;

/** Namensprüfung, Preise und Verfügbarkeit. */
final class Domain
{
    /** Normalisiert Eingabe ("Müller.de", "www.example.com") auf den Namen ohne Endung (ASCII/Punycode) oder null. */
    public static function normalizeName(string $input): ?string
    {
        $s = mb_strtolower(trim($input), 'UTF-8');
        $s = preg_replace('#^https?://#', '', $s);
        $s = explode('/', $s)[0];
        $s = preg_replace('#^www\.#', '', $s);
        if (str_contains($s, '.')) {            // Endung abschneiden, falls mit eingegeben
            $s = substr($s, 0, (int)strrpos($s, '.'));
        }
        if ($s === '' || str_contains($s, '.')) {
            return null;
        }
        $ascii = idn_to_ascii($s, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($ascii === false) {
            return null;
        }
        if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $ascii)) {
            return null;
        }
        if (substr($ascii, 2, 2) === '--' && !str_starts_with($ascii, 'xn--')) {
            return null;
        }
        return $ascii;
    }

    public static function display(string $asciiName): string
    {
        $u = idn_to_utf8($asciiName, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        return $u === false ? $asciiName : $u;
    }

    /** @return array<int,array{tld:string,price_cents:int,renew_cents:int}> */
    public static function tlds(): array
    {
        return Db::all('SELECT tld, price_cents, renew_cents FROM tlds WHERE active = 1 ORDER BY sort, tld');
    }

    public static function tld(string $tld): ?array
    {
        return Db::one('SELECT tld, price_cents, renew_cents FROM tlds WHERE active = 1 AND tld = ?', [$tld]);
    }

    public static function isTakenLocally(string $name, string $tld): bool
    {
        return (bool)Db::one('SELECT 1 FROM domains WHERE name = ? AND tld = ?', [$name, $tld]);
    }

    /**
     * Prüft alle aktiven Endungen. Ergebnis je Endung: status = free | taken | unknown.
     * @return array<int,array<string,mixed>>
     */
    public static function check(string $name): array
    {
        $tlds = self::tlds();
        $checker = Availability::make();
        $remote = $checker->checkMany($name, array_column($tlds, 'tld'));
        $out = [];
        foreach ($tlds as $t) {
            $status = self::isTakenLocally($name, $t['tld']) ? 'taken' : ($remote[$t['tld']] ?? 'unknown');
            $out[] = [
                'name' => $name, 'tld' => $t['tld'], 'fqdn' => $name . '.' . $t['tld'],
                'display' => self::display($name) . '.' . $t['tld'],
                'status' => $status,
                'price_cents' => (int)$t['price_cents'], 'price' => money((int)$t['price_cents']),
                'renew' => money((int)$t['renew_cents']),
            ];
        }
        return $out;
    }

    public static function checkOne(string $name, string $tld): string
    {
        if (self::isTakenLocally($name, $tld)) {
            return 'taken';
        }
        return Availability::make()->checkMany($name, [$tld])[$tld] ?? 'unknown';
    }
}

interface AvailabilityChecker
{
    /** @param string[] $tlds @return array<string,string> tld => free|taken|unknown */
    public function checkMany(string $name, array $tlds): array;
}

final class Availability
{
    public static function make(): AvailabilityChecker
    {
        return Config::get('availability') === 'mock' ? new MockAvailability() : new RdapAvailability();
    }
}

/** Deterministische Demo-Daten für Entwicklung ohne Netzwerk. */
final class MockAvailability implements AvailabilityChecker
{
    private const TAKEN = ['google', 'amazon', 'apple', 'test', 'example', 'microsoft', 'facebook', 'github', 'wikipedia'];

    public function checkMany(string $name, array $tlds): array
    {
        $r = [];
        foreach ($tlds as $tld) {
            $taken = in_array($name, self::TAKEN, true) && in_array($tld, ['de', 'com'], true)
                || (crc32($name . '.' . $tld) % 5 === 0);
            $r[$tld] = $taken ? 'taken' : 'free';
        }
        return $r;
    }
}

/** Echte Abfrage über RDAP (rdap.org leitet an die zuständige Registry weiter): 404 = frei, 200 = vergeben. */
final class RdapAvailability implements AvailabilityChecker
{
    public function checkMany(string $name, array $tlds): array
    {
        $mh = curl_multi_init();
        $handles = [];
        foreach ($tlds as $tld) {
            $ch = curl_init('https://rdap.org/domain/' . rawurlencode($name . '.' . $tld));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_HTTPHEADER => ['Accept: application/rdap+json'],
                CURLOPT_USERAGENT => 'domainportal/1.0',
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$tld] = $ch;
        }
        do {
            $st = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 0.5);
            }
        } while ($running && $st === CURLM_OK);

        $result = [];
        foreach ($handles as $tld => $ch) {
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $result[$tld] = match (true) {
                $code === 404 => 'free',
                $code === 200 => 'taken',
                default => 'unknown',
            };
            curl_multi_remove_handle($mh, $ch);
        }
        curl_multi_close($mh);
        return $result;
    }
}
