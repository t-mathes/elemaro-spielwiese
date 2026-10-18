<?php
declare(strict_types=1);

namespace App\Registrar;

/**
 * Schnittstelle zum Domain-Registrar (z. B. INWX, Hexonet, Domainrobot ...).
 * Jede Methode wirft bei Fehlern eine RuntimeException mit verständlicher Meldung.
 * Die Datenbank des Portals wird vom Aufrufer gepflegt, der Registrar ist nur die "Gegenstelle".
 */
interface Registrar
{
    /** Registriert die Domain; $contact enthält Inhaberdaten. Rückgabe: Auth-Code (EPP). */
    public function register(string $fqdn, array $contact, array $nameservers, int $years): string;

    public function renew(string $fqdn, int $years): void;

    /** @param string[] $nameservers */
    public function setNameservers(string $fqdn, array $nameservers): void;

    public function setLock(string $fqdn, bool $locked): void;

    public function updateContact(string $fqdn, array $contact): void;

    /** Erzeugt/liefert einen aktuellen Auth-Code für den Transfer. */
    public function authCode(string $fqdn): string;
}
