# Domainportal

Eigenständiger Domainservice: Landingpage mit Domaincheck → Bestellstrecke (Konto, E-Mail-Bestätigung, Daten, PayPal) → Kundenbereich.
PHP 8.1+ (getestet mit 8.3), keine Composer-Abhängigkeiten, SQLite (MySQL möglich), kein Build-Schritt.

## Starten (Entwicklung)

```bash
cd domainportal
php -S 127.0.0.1:8080 -t public public/index.php
```

`config.local.php` (nicht im Git) überschreibt `config.php`, z. B. `['debug' => true, 'availability' => 'mock']` für Demo ohne Netzwerk.
Mit `debug => true` und ohne PayPal-Zugangsdaten gibt es eine **Demo-Zahlung**; in Produktion (debug = false) ist ohne PayPal keine Bestellung möglich.
Mails landen im Standard in `var/mail.log`. Test der kompletten Strecke: `tests/e2e.sh` (Dev-Server muss laufen, Mock-Modus).

## Ablauf

| Schritt | Route |
|---|---|
| Domaincheck (nur Eingabefeld, alle aktiven Endungen werden live geprüft) | `/`, `/api/check` |
| Warenkorb | `/api/cart` (Session) |
| Konto anlegen → Mail bestätigen | `/registrieren`, `/bestaetigen` |
| Persönliche Daten → PayPal | `/bestellung` |
| Kundenbereich: Übersicht, Einzelansicht (Auto-Verlängerung, Nameserver, DNS, Transfersperre, Auth-Code, Inhaber) | `/konto`, `/konto/domains/{id}` |

## Endungen & Preise

`php bin/tlds.php list | set de 8.99 11.99 10 | off io | remove io` – Preise sind Brutto (inkl. MwSt.). Die mitgelieferten Preise sind **Beispielwerte**.

## Vor dem Livegang (wichtig)

1. **Registrar anbinden.** `registrar => 'mock'` speichert Domains nur lokal – es wird nichts wirklich registriert! Implementiere `App\Registrar\Registrar` für deinen Anbieter (z. B. INWX, Hexonet, Domainrobot, Netcup) und trage ihn in `RegistrarFactory` ein. Setze zudem `default_nameservers`.
2. **PayPal**: `paypal.client_id/client_secret`, `mode => 'live'`. Gezahlt wird per Redirect-Flow (Orders v2), Betrag/Währung werden serverseitig geprüft, die Ausführung ist idempotent.
   Schlägt eine Registrierung nach erfolgreicher Zahlung fehl, steht die Position auf `failed`, der Support bekommt eine Mail – **Rückerstattung erfolgt manuell** (noch kein Admin-Bereich).
3. **Verfügbarkeit**: `availability => 'rdap'` fragt rdap.org/Registries ab (404 = frei). Das ist ein guter Vorfilter; endgültig entscheidet die Registrierung beim Registrar. Nicht alle Endungen unterstützen RDAP (dann „Nicht prüfbar“).
4. **Mail**: `mail.driver` = `smtp` oder `mail`, Absender setzen (SPF/DKIM beachten).
5. **Rechtliches**: `/impressum`, `/datenschutz`, `/agb` sind Platzhalter. Widerrufsbelehrung, MwSt.-/Rechnungsstellung (es wird noch keine Rechnung erzeugt) und Verlängerungslogik (Cronjob für Auto-Renew/Erinnerungen) fehlen noch.
6. HTTPS erzwingen, `var/` nicht öffentlich ausliefern (Webroot = `public/`), `debug => false`.

## Sicherheit

Passwort-Hashing (`password_hash`), CSRF-Token auf allen POSTs, Prepared Statements, Rate-Limits (Check, Login, Registrierung), Token gehasht in der DB, CSP ohne Inline-Skripte, Besitzprüfung bei jeder Domain-Aktion.

## Noch nicht enthalten

Admin-Backend, Rechnungen/PDF, automatische Verlängerung (Cron), Domain-Transfer-In, Zwei-Faktor-Login, englische Texte.
