<?php
// Standard-Konfiguration. Eigene Werte NICHT hier ändern, sondern in config.local.php
// (gleiche Struktur, wird darübergelegt) oder per Umgebungsvariable DP_*.
$config = [
    'brand'        => 'Domainhaus',
    'base_url'     => '',                 // z. B. https://domains.example.de (leer = automatisch)
    'debug'        => false,
    'currency'     => 'EUR',
    'vat_rate'     => 19.0,               // Preise im Shop sind Brutto-Preise (inkl. MwSt.)
    'support_mail' => 'support@example.de',
    'legal'        => ['imprint_url' => '/impressum', 'privacy_url' => '/datenschutz', 'terms_url' => '/agb'],

    'db' => [
        'dsn'  => 'sqlite:' . __DIR__ . '/var/data.sqlite',   // oder mysql:host=localhost;dbname=...;charset=utf8mb4
        'user' => null,
        'pass' => null,
    ],

    // Wie wird geprüft, ob eine Domain frei ist? 'rdap' = echte Abfrage bei den Registries, 'mock' = Demo-Daten.
    'availability' => 'rdap',
    // Anbindung an den Registrar (Registrierung, Nameserver, Auth-Code ...). 'mock' = nur lokal gespeichert.
    'registrar'    => 'mock',

    'paypal' => [
        'mode'          => 'sandbox',     // sandbox | live
        'client_id'     => '',
        'client_secret' => '',            // leer = Demo-Zahlung (ohne PayPal), nur für Tests!
    ],

    // 'log' schreibt Mails nach var/mail.log, 'mail' nutzt PHP mail(), 'smtp' einen SMTP-Server.
    'mail' => [
        'driver' => 'log',
        'from'   => 'no-reply@example.de',
        'smtp'   => ['host' => '', 'port' => 587, 'secure' => 'tls', 'user' => '', 'pass' => ''],
    ],

    'default_nameservers' => ['ns1.example-dns.de', 'ns2.example-dns.de'],
];

if (is_file(__DIR__ . '/config.local.php')) {
    $config = array_replace_recursive($config, require __DIR__ . '/config.local.php');
}
return $config;
