<?php
declare(strict_types=1);

namespace App;

use App\Registrar\RegistrarFactory;

final class Users
{
    public static function current(): ?array
    {
        static $u = false;
        if ($u === false) {
            $id = Session::userId();
            $u = $id ? Db::one('SELECT * FROM users WHERE id = ?', [$id]) : null;
            if (!$u) {
                $u = null;
                unset($_SESSION['uid']);
            }
        }
        return $u;
    }

    public static function require(): array
    {
        $u = self::current();
        if (!$u) {
            $_SESSION['next'] = $_SERVER['REQUEST_URI'] ?? '/konto';
            Http::redirect('/anmelden');
        }
        return $u;
    }

    /** Konto + bestätigte Mail. */
    public static function requireVerified(): array
    {
        $u = self::require();
        if (!$u['verified_at']) {
            Http::redirect('/bestaetigen');
        }
        return $u;
    }

    private static function issueToken(int $uid, string $kind, int $hours): string
    {
        $raw = bin2hex(random_bytes(24));
        Db::run('UPDATE users SET token = ?, token_kind = ?, token_expires = ? WHERE id = ?',
            [hash('sha256', $raw), $kind, gmdate('Y-m-d H:i:s', time() + $hours * 3600), $uid]);
        return $raw;
    }

    /** @return string[] Fehlermeldungen (leer = ok) */
    public static function register(string $email, string $password, bool $acceptTerms): array
    {
        $err = [];
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $err[] = 'Bitte eine gültige E-Mail-Adresse angeben.';
        }
        if (mb_strlen($password) < 10) {
            $err[] = 'Das Passwort braucht mindestens 10 Zeichen.';
        }
        if (!$acceptTerms) {
            $err[] = 'Bitte AGB und Datenschutzhinweise bestätigen.';
        }
        if ($err) {
            return $err;
        }
        if (Db::one('SELECT 1 FROM users WHERE email = ?', [$email])) {
            // Keine Aussage, ob die Adresse existiert, wäre ideal – für die Bestellstrecke ist ein klarer Hinweis hilfreicher.
            return ['Mit dieser E-Mail-Adresse gibt es bereits ein Konto. Bitte melde dich an.'];
        }
        $uid = Db::insert('INSERT INTO users (email, password_hash, created_at) VALUES (?, ?, ?)',
            [$email, password_hash($password, PASSWORD_DEFAULT), now()]);
        self::sendVerification($uid, $email);
        Session::login($uid);
        return [];
    }

    public static function sendVerification(int $uid, string $email): void
    {
        $tok = self::issueToken($uid, 'verify', 48);
        Mailer::send($email, 'Bitte bestätige deine E-Mail-Adresse',
            "Willkommen bei " . Config::get('brand') . "!\n\nBitte bestätige deine E-Mail-Adresse, um deine Bestellung fortzusetzen:\n\n"
            . Http::baseUrl() . "/bestaetigen/$tok\n\nDer Link ist 48 Stunden gültig. Falls du dich nicht registriert hast, ignoriere diese Mail.");
    }

    public static function verify(string $raw): bool
    {
        $u = Db::one("SELECT id FROM users WHERE token = ? AND token_kind = 'verify' AND token_expires > ?", [hash('sha256', $raw), now()]);
        if (!$u) {
            return false;
        }
        Db::run('UPDATE users SET verified_at = ?, token = NULL, token_kind = NULL, token_expires = NULL WHERE id = ?', [now(), $u['id']]);
        return true;
    }

    public static function attempt(string $email, string $password): bool
    {
        $u = Db::one('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
        // Konstante Arbeit auch bei unbekannter Adresse
        $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringforsaltuYk7Wq4Z9E8o0f4oX3f6R6l1s0aKq2';
        if (password_verify($password, $hash) && $u) {
            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
            }
            Session::login((int)$u['id']);
            return true;
        }
        return false;
    }

    public static function requestReset(string $email): void
    {
        $u = Db::one('SELECT id, email FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
        if ($u) {
            $tok = self::issueToken((int)$u['id'], 'reset', 2);
            Mailer::send($u['email'], 'Passwort zurücksetzen',
                "Du hast das Zurücksetzen deines Passworts angefordert:\n\n" . Http::baseUrl() . "/passwort/neu/$tok\n\n"
                . "Der Link ist 2 Stunden gültig. Falls du das nicht warst, ignoriere diese Mail.");
        }
    }

    public static function findByResetToken(string $raw): ?array
    {
        return Db::one("SELECT * FROM users WHERE token = ? AND token_kind = 'reset' AND token_expires > ?", [hash('sha256', $raw), now()]);
    }

    public static function setPassword(int $uid, string $password): void
    {
        // Ein Reset-Link beweist auch den Besitz der Mailadresse.
        Db::run('UPDATE users SET password_hash = ?, token = NULL, token_kind = NULL, token_expires = NULL, verified_at = COALESCE(verified_at, ?) WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), now(), $uid]);
    }

    public const COUNTRIES = ['DE' => 'Deutschland', 'AT' => 'Österreich', 'CH' => 'Schweiz', 'LU' => 'Luxemburg', 'NL' => 'Niederlande',
        'BE' => 'Belgien', 'FR' => 'Frankreich', 'IT' => 'Italien', 'ES' => 'Spanien', 'PL' => 'Polen', 'DK' => 'Dänemark', 'GB' => 'Vereinigtes Königreich', 'US' => 'USA'];

    /** @return array{0:array,1:string[]} bereinigte Daten, Fehler */
    public static function validateProfile(array $in): array
    {
        $f = fn(string $k, int $max = 190) => mb_substr(trim((string)($in[$k] ?? '')), 0, $max);
        $d = [
            'type' => ($in['type'] ?? '') === 'company' ? 'company' : 'private',
            'company' => $f('company'), 'first_name' => $f('first_name', 100), 'last_name' => $f('last_name', 100),
            'street' => $f('street'), 'zip' => $f('zip', 20), 'city' => $f('city', 100),
            'country' => strtoupper($f('country', 2)), 'phone' => $f('phone', 40),
        ];
        $err = [];
        foreach (['first_name' => 'Vorname', 'last_name' => 'Nachname', 'street' => 'Straße und Hausnummer', 'zip' => 'PLZ', 'city' => 'Ort', 'phone' => 'Telefonnummer'] as $k => $label) {
            if ($d[$k] === '') {
                $err[] = "Bitte $label angeben.";
            }
        }
        if ($d['type'] === 'company' && $d['company'] === '') {
            $err[] = 'Bitte den Firmennamen angeben.';
        }
        if (!isset(self::COUNTRIES[$d['country']])) {
            $err[] = 'Bitte ein Land wählen.';
        }
        if ($d['phone'] !== '' && !preg_match('/^\+?[0-9 ()\/.-]{6,}$/', $d['phone'])) {
            $err[] = 'Die Telefonnummer scheint ungültig (z. B. +49 30 1234567).';
        }
        return [$d, $err];
    }

    public static function saveProfile(int $uid, array $d): void
    {
        Db::run('UPDATE users SET type=?, company=?, first_name=?, last_name=?, street=?, zip=?, city=?, country=?, phone=? WHERE id=?',
            [$d['type'], $d['company'] ?: null, $d['first_name'], $d['last_name'], $d['street'], $d['zip'], $d['city'], $d['country'], $d['phone'], $uid]);
    }

    public static function profileComplete(array $u): bool
    {
        return self::validateProfile($u)[1] === [];
    }
}

final class Cart
{
    public const MAX = 20;

    /** @return string[] FQDNs */
    public static function items(): array
    {
        return $_SESSION['cart'] ?? [];
    }

    public static function add(string $name, string $tld): bool
    {
        $fqdn = "$name.$tld";
        $c = self::items();
        if (!Domain::tld($tld) || count($c) >= self::MAX) {
            return false;
        }
        if (!in_array($fqdn, $c, true)) {
            $c[] = $fqdn;
        }
        $_SESSION['cart'] = $c;
        return true;
    }

    public static function remove(string $fqdn): void
    {
        $_SESSION['cart'] = array_values(array_filter(self::items(), fn($i) => $i !== $fqdn));
    }

    public static function clear(): void
    {
        unset($_SESSION['cart']);
    }

    /** @return array{lines:array,total:int} Preise stets frisch aus der Datenbank */
    public static function summary(): array
    {
        $lines = [];
        $total = 0;
        foreach (self::items() as $fqdn) {
            [$name, $tld] = explode('.', $fqdn, 2);
            $t = Domain::tld($tld);
            if (!$t) {
                continue;
            }
            $lines[] = ['fqdn' => $fqdn, 'name' => $name, 'tld' => $tld, 'display' => Domain::display($name) . '.' . $tld,
                'price_cents' => (int)$t['price_cents'], 'renew_cents' => (int)$t['renew_cents']];
            $total += (int)$t['price_cents'];
        }
        return ['lines' => $lines, 'total' => $total];
    }
}

final class Orders
{
    /** @return array{0:?int,1:string[]} Bestell-ID oder Fehler */
    public static function create(array $user): array
    {
        $sum = Cart::summary();
        if (!$sum['lines']) {
            return [null, ['Dein Warenkorb ist leer.']];
        }
        $err = [];
        foreach ($sum['lines'] as $l) {
            if (Domain::checkOne($l['name'], $l['tld']) !== 'free') {
                $err[] = $l['display'] . ' ist leider nicht mehr verfügbar und wurde aus dem Warenkorb entfernt.';
                Cart::remove($l['fqdn']);
            }
        }
        if ($err) {
            return [null, $err];
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $oid = Db::insert('INSERT INTO orders (user_id, status, total_cents, currency, billing, created_at) VALUES (?,?,?,?,?,?)',
                [$user['id'], 'pending', $sum['total'], Config::get('currency'), json_encode(self::contactFrom($user)), now()]);
            foreach ($sum['lines'] as $l) {
                Db::run('INSERT INTO order_items (order_id, name, tld, price_cents) VALUES (?,?,?,?)', [$oid, $l['name'], $l['tld'], $l['price_cents']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return [$oid, []];
    }

    public static function contactFrom(array $u): array
    {
        return array_intersect_key($u, array_flip(['type', 'company', 'first_name', 'last_name', 'street', 'zip', 'city', 'country', 'phone', 'email']));
    }

    /** Wird nach erfolgreicher Zahlung genau einmal ausgeführt. */
    public static function fulfill(int $orderId, string $captureId): void
    {
        // Atomarer Statuswechsel verhindert doppelte Ausführung (Reload, Doppelklick, parallele Requests).
        if (Db::run("UPDATE orders SET status = 'paid', paid_at = ?, paypal_capture_id = ? WHERE id = ? AND status = 'pending'", [now(), $captureId, $orderId]) !== 1) {
            return;
        }
        $order = Db::one('SELECT * FROM orders WHERE id = ?', [$orderId]);
        $user = Db::one('SELECT * FROM users WHERE id = ?', [$order['user_id']]);
        $contact = json_decode((string)$order['billing'], true) ?: [];
        $ns = (array)Config::get('default_nameservers');
        $failed = 0;
        $lines = [];
        foreach (Db::all('SELECT * FROM order_items WHERE order_id = ?', [$orderId]) as $it) {
            $fqdn = $it['name'] . '.' . $it['tld'];
            try {
                $auth = RegistrarFactory::make()->register($fqdn, $contact, $ns, 1);
                Db::insert('INSERT INTO domains (user_id, order_id, name, tld, status, registered_at, expires_at, auto_renew, locked, auth_code, nameservers, contact) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$order['user_id'], $orderId, $it['name'], $it['tld'], 'active', now(), gmdate('Y-m-d H:i:s', strtotime('+1 year')), 1, 1, $auth, json_encode($ns), json_encode($contact)]);
                Db::run("UPDATE order_items SET status = 'done' WHERE id = ?", [$it['id']]);
                $lines[] = '✓ ' . Domain::display($it['name']) . '.' . $it['tld'];
            } catch (\Throwable $e) {
                $failed++;
                error_log("Registrierung $fqdn (Bestellung $orderId) fehlgeschlagen: " . $e->getMessage());
                Db::run("UPDATE order_items SET status = 'failed', note = ? WHERE id = ?", [mb_substr($e->getMessage(), 0, 250), $it['id']]);
                $lines[] = '✗ ' . $fqdn . ' (Registrierung nicht möglich – wir melden uns bei dir)';
            }
        }
        Db::run('UPDATE orders SET status = ? WHERE id = ?', [$failed ? 'attention' : 'completed', $orderId]);
        Mailer::send($user['email'], 'Deine Bestellung #' . $orderId,
            "Vielen Dank für deine Bestellung!\n\n" . implode("\n", $lines) . "\n\nGesamt: " . money((int)$order['total_cents'])
            . " (inkl. MwSt.)\n\nDeine Domains verwaltest du hier: " . Http::baseUrl() . "/konto\n"
            . ($failed ? "\nFür nicht registrierbare Domains erstatten wir den Betrag oder melden uns mit einer Lösung.\n" : ''));
        if ($failed) {
            Mailer::send((string)Config::get('support_mail'), "Bestellung #$orderId braucht Aufmerksamkeit",
                "$failed Domain(s) konnten nach erfolgreicher Zahlung nicht registriert werden. Siehe order_items.status = failed.");
        }
    }
}

final class Domains
{
    public static function owned(int $uid, int $id): array
    {
        $d = Db::one('SELECT * FROM domains WHERE id = ? AND user_id = ?', [$id, $uid]);
        if (!$d) {
            Http::abort(404, 'Domain nicht gefunden.');
        }
        $d['fqdn'] = $d['name'] . '.' . $d['tld'];
        $d['display'] = Domain::display($d['name']) . '.' . $d['tld'];
        $d['ns'] = json_decode($d['nameservers'], true) ?: [];
        $d['owner'] = json_decode($d['contact'], true) ?: [];
        return $d;
    }

    public static function usesDefaultNs(array $d): bool
    {
        return array_map('strtolower', $d['ns']) === array_map('strtolower', (array)Config::get('default_nameservers'));
    }
}
