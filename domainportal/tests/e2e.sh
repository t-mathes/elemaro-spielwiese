#!/usr/bin/env bash
# Durchlauf der kompletten Bestellstrecke gegen einen laufenden Dev-Server (Mock-Modus, debug=true).
# Start:  php -S 127.0.0.1:8099 -t public public/index.php   |   Aufruf: tests/e2e.sh
set -eu  # kein pipefail: grep -q beendet die Pipe früh und würde curl mit SIGPIPE melden
B=${1:-http://127.0.0.1:8099}; J=$(mktemp); trap 'rm -f $J' EXIT
cd "$(dirname "$0")/.."
tok() { curl -s -b $J -c $J $B/ | grep -o 'csrf-token" content="[a-f0-9]*' | cut -d'"' -f3; }
post() { local u=$1; shift; curl -s -b $J -c $J -o /dev/null -w '%{http_code} %{redirect_url}\n' -X POST "$B$u" -d "_csrf=$(tok)" "$@"; }
ok() { echo "✓ $1"; }
fail() { echo "✗ FEHLGESCHLAGEN: $1"; exit 1; }

curl -s $B/ | grep -q 'Prüfen' && ok "Startseite" || fail "Startseite"
curl -s "$B/api/check?name=meinefirma" | grep -q '"status":"free"' && ok "Domaincheck liefert freie Domains" || fail "Domaincheck liefert freie Domains"
curl -s "$B/api/check?name=" | grep -q 'gültigen' && ok "Leere Eingabe wird abgelehnt" || fail "Leere Eingabe wird abgelehnt"
curl -s "$B/api/check?name=-bad-" | grep -q 'gültigen' && ok "Ungültiger Name wird abgelehnt" || fail "Ungültiger Name wird abgelehnt"
curl -s "$B/api/check?name=M%C3%BCller.de" | grep -q 'xn--mller-kva' && ok "Umlaute (IDN) werden umgewandelt" || fail "Umlaute (IDN) werden umgewandelt"

curl -s -b $J -c $J -X POST $B/api/cart -H "X-CSRF-Token: $(tok)" -d 'action=add&name=meinefirma&tld=de' | grep -q '"count":1' && ok "Warenkorb: hinzufügen" || fail "Warenkorb: hinzufügen"
curl -s -X POST $B/api/cart -d 'action=add&name=x&tld=de' -o /dev/null -w '%{http_code}' | grep -q 419 && ok "POST ohne CSRF wird abgelehnt (419)" || fail "POST ohne CSRF wird abgelehnt (419)"
curl -s -b $J -c $J $B/bestellung -o /dev/null -w '%{redirect_url}' | grep -q registrieren && ok "Bestellung ohne Konto -> Registrierung" || fail "Bestellung ohne Konto -> Registrierung"

post /registrieren --data-urlencode "email=kunde@example.de" --data-urlencode "password=geheimespasswort1" -d terms=1 | grep -q '/bestaetigen' && ok "Registrierung" || fail "Registrierung"
curl -s -b $J -c $J $B/konto -o /dev/null -w '%{redirect_url}' | grep -q bestaetigen && ok "Konto gesperrt bis Mail bestätigt" || fail "Konto gesperrt bis Mail bestätigt"
T=$(grep -o 'bestaetigen/[a-f0-9]*' var/mail.log | tail -1 | cut -d/ -f2)
curl -s -b $J -c $J $B/bestaetigen/$T | grep -q 'E-Mail bestätigt' && ok "Mail-Bestätigung" || fail "Mail-Bestätigung"
curl -s -b $J -c $J $B/bestellung | grep -q 'Deine Daten' && ok "Bestellung: Schritt Daten" || fail "Bestellung: Schritt Daten"
post /bestellung/daten -d type=private -d first_name=Erika -d last_name=Muster --data-urlencode "street=Hauptstr. 1" -d zip=10115 -d city=Berlin -d country=DE --data-urlencode "phone=+49 30 123456" | grep -q '/bestellung' && ok "Daten speichern" || fail "Daten speichern"
curl -s -b $J -c $J $B/bestellung | grep -q 'PayPal bezahlen' && ok "Bestellung: Schritt Zahlung" || fail "Bestellung: Schritt Zahlung"
R=$(post /bestellung/zahlen -d terms=1); echo "$R" | grep -q 'paypal/demo' && ok "Weiterleitung zu PayPal (Demo)" || fail "Weiterleitung zu PayPal (Demo)"
PT=$(echo "$R" | grep -o 'token=[A-Za-z0-9-]*' | cut -d= -f2)
curl -s -b $J -c $J "$B/paypal/return?token=$PT" -o /dev/null -w '%{redirect_url}' | grep -q 'fertig' && ok "Zahlung erfasst, Domain registriert" || fail "Zahlung erfasst, Domain registriert"
curl -s -b $J -c $J "$B/paypal/return?token=$PT" -o /dev/null -w '%{redirect_url}' | grep -q 'fertig' && ok "Reload der Rückkehrseite ist idempotent" || fail "Reload der Rückkehrseite ist idempotent"
[ "$(php -r 'require "src/Core.php"; App\Config::load(["db"=>["dsn"=>"sqlite:var/data.sqlite"]]); echo App\Db::one("select count(*) c from domains")["c"];')" = 1 ] && ok "genau 1 Domain angelegt" || fail "genau 1 Domain angelegt"

curl -s -b $J -c $J $B/konto | grep -q 'meinefirma.de' && ok "Backend: Domainübersicht" || fail "Backend: Domainübersicht"
curl -s -b $J -c $J $B/konto/domains/1 | grep -q 'Nameserver' && ok "Backend: Einzelansicht" || fail "Backend: Einzelansicht"
post /konto/domains/1/nameserver -d 'ns[]=ns1.foo.de' -d 'ns[]=ns2.foo.de' >/dev/null
curl -s -b $J -c $J $B/konto/domains/1 | grep -q 'ns1.foo.de' && ok "Nameserver ändern" || fail "Nameserver ändern"
post /konto/domains/1/einstellung -d setting=auto_renew >/dev/null
curl -s -b $J -c $J $B/konto | grep -q 'Manuell' && ok "Auto-Verlängerung ausschalten" || fail "Auto-Verlängerung ausschalten"
curl -s -b $J -c $J $B/konto/domains/1 | grep -q 'keine Einträge\|eigene Nameserver' && ok "DNS gesperrt bei fremden Nameservern" || fail "DNS gesperrt bei fremden Nameservern"
post /konto/domains/1/nameserver -d reset=1 >/dev/null
post /konto/domains/1/dns -d type=A -d host=www -d value=203.0.113.9 >/dev/null
curl -s -b $J -c $J $B/konto/domains/1 | grep -q '203.0.113.9' && ok "DNS-Eintrag anlegen" || fail "DNS-Eintrag anlegen"
post /konto/domains/1/authcode >/dev/null
curl -s -b $J -c $J $B/konto/domains/1 | grep -q 'class="authcode"' && ok "Auth-Code anzeigen" || fail "Auth-Code anzeigen"
curl -s -b $J -c $J $B/konto/domains/2 -o /dev/null -w '%{http_code}' | grep -q 404 && ok "Fremde/unbekannte Domain -> 404" || fail "Fremde/unbekannte Domain -> 404"
echo "Alle Prüfungen bestanden."
