<?php
declare(strict_types=1);

namespace App\Registrar;

use App\Config;

/** Platzhalter ohne echte Registrierung: es passiert nur die lokale Speicherung. Nur für Entwicklung/Demo! */
final class MockRegistrar implements Registrar
{
    public function register(string $fqdn, array $contact, array $nameservers, int $years): string
    {
        return $this->authCode($fqdn);
    }

    public function renew(string $fqdn, int $years): void {}
    public function setNameservers(string $fqdn, array $nameservers): void {}
    public function setLock(string $fqdn, bool $locked): void {}
    public function updateContact(string $fqdn, array $contact): void {}

    public function authCode(string $fqdn): string
    {
        return strtr(rtrim(base64_encode(random_bytes(12)), '='), '+/', 'Ax') . '#' . random_int(10, 99);
    }
}

final class RegistrarFactory
{
    public static function make(): Registrar
    {
        return match (Config::get('registrar')) {
            'mock' => new MockRegistrar(),
            default => throw new \RuntimeException(
                'Registrar "' . Config::get('registrar') . '" ist nicht implementiert. '
                . 'Implementiere App\Registrar\Registrar und trage die Klasse in RegistrarFactory ein.'
            ),
        };
    }
}
