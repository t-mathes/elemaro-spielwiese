<?php
declare(strict_types=1);

use App\{Cart, Config, Db, Domain, Domains, Http, Mailer, Orders, PayPal, Session, Users, View};
use App\Registrar\RegistrarFactory;

$page = fn(string $tpl, array $vars = []) => fn() => View::render($tpl, $vars);
$postStr = fn(string $k, int $max = 500): string => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);

/** Sichere interne Weiterleitung nach dem Login. */
$takeNext = function (string $default): string {
    $n = $_SESSION['next'] ?? $default;
    unset($_SESSION['next']);
    return (is_string($n) && str_starts_with($n, '/') && !str_starts_with($n, '//')) ? $n : $default;
};

$cartJson = function (): array {
    $s = Cart::summary();
    return ['items' => array_map(fn($l) => ['fqdn' => $l['fqdn'], 'display' => $l['display'], 'price' => money($l['price_cents'])], $s['lines']),
        'total' => money($s['total']), 'count' => count($s['lines'])];
};

return [
    // ---------- Startseite & Domaincheck ----------
    ['GET', '/', fn() => View::render('home', ['layout' => 'layout_home', 'title' => null])],

    ['GET', '/api/check', function () {
        if (!Session::throttle('check', 30, 60)) {
            Http::json(['error' => 'Zu viele Anfragen. Bitte kurz warten.'], 429);
        }
        $name = Domain::normalizeName((string)($_GET['name'] ?? ''));
        if ($name === null) {
            Http::json(['error' => 'Bitte einen gültigen Domainnamen eingeben (Buchstaben, Ziffern, Bindestrich).'], 422);
        }
        $inCart = Cart::items();
        $results = array_map(fn($r) => $r + ['in_cart' => in_array($r['fqdn'], $inCart, true)], Domain::check($name));
        Http::json(['name' => $name, 'display' => Domain::display($name), 'results' => $results]);
    }],

    ['POST', '/api/cart', function () use ($cartJson) {
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            $name = Domain::normalizeName((string)($_POST['name'] ?? ''));
            $tld = (string)($_POST['tld'] ?? '');
            if (!$name || !Domain::tld($tld) || Domain::checkOne($name, $tld) !== 'free') {
                Http::json(['error' => 'Diese Domain kann nicht bestellt werden.'], 422);
            }
            if (!Cart::add($name, $tld)) {
                Http::json(['error' => 'Der Warenkorb ist voll.'], 422);
            }
        } elseif ($action === 'remove') {
            Cart::remove((string)($_POST['fqdn'] ?? ''));
        } elseif ($action === 'clear') {
            Cart::clear();
        }
        Http::json($cartJson());
    }],
    ['GET', '/api/cart', fn() => Http::json($cartJson())],

    // ---------- Registrierung / Login ----------
    ['GET', '/registrieren', function () {
        if (Users::current()) {
            Http::redirect('/bestellung');
        }
        View::render('auth/register', ['title' => 'Konto erstellen', 'errors' => [], 'email' => '']);
    }],
    ['POST', '/registrieren', function () use ($postStr) {
        if (!Session::throttle('register', 5, 600)) {
            Http::abort(429, 'Zu viele Versuche. Bitte warte ein paar Minuten.');
        }
        $email = $postStr('email', 190);
        $errors = Users::register($email, (string)($_POST['password'] ?? ''), isset($_POST['terms']));
        if ($errors) {
            View::render('auth/register', ['title' => 'Konto erstellen', 'errors' => $errors, 'email' => $email]);
            return;
        }
        Http::redirect('/bestaetigen');
    }],
    ['GET', '/anmelden', function () {
        if (Users::current()) {
            Http::redirect('/konto');
        }
        View::render('auth/login', ['title' => 'Anmelden', 'error' => null, 'email' => '']);
    }],
    ['POST', '/anmelden', function () use ($postStr, $takeNext) {
        $email = $postStr('email', 190);
        if (!Session::throttle('login', 8, 600)) {
            Http::abort(429, 'Zu viele Anmeldeversuche. Bitte warte ein paar Minuten.');
        }
        if (!Users::attempt($email, (string)($_POST['password'] ?? ''))) {
            View::render('auth/login', ['title' => 'Anmelden', 'error' => 'E-Mail oder Passwort stimmt nicht.', 'email' => $email]);
            return;
        }
        Http::redirect($takeNext('/konto'));
    }],
    ['POST', '/abmelden', function () {
        Session::logout();
        Http::redirect('/');
    }],

    ['GET', '/bestaetigen', function () {
        $u = Users::require();
        if ($u['verified_at']) {
            Http::redirect(Cart::items() ? '/bestellung' : '/konto');
        }
        View::render('auth/verify', ['title' => 'E-Mail bestätigen', 'user' => $u, 'state' => 'pending']);
    }],
    ['POST', '/bestaetigen/senden', function () {
        $u = Users::require();
        if (!$u['verified_at'] && Session::throttle('resend', 3, 900)) {
            Users::sendVerification((int)$u['id'], $u['email']);
            Session::flash('ok', 'Wir haben dir die Bestätigungsmail erneut geschickt.');
        } else {
            Session::flash('error', 'Bitte warte einen Moment, bevor du die Mail erneut anforderst.');
        }
        Http::redirect('/bestaetigen');
    }],
    ['GET', '/bestaetigen/([a-f0-9]{48})', function (string $tok) {
        $ok = Users::verify($tok);
        View::render('auth/verify', ['title' => 'E-Mail bestätigen', 'user' => Users::current(), 'state' => $ok ? 'done' : 'invalid']);
    }],

    ['GET', '/passwort/vergessen', fn() => View::render('auth/forgot', ['title' => 'Passwort vergessen', 'sent' => false])],
    ['POST', '/passwort/vergessen', function () use ($postStr) {
        if (Session::throttle('forgot', 3, 900)) {
            Users::requestReset($postStr('email', 190));
        }
        View::render('auth/forgot', ['title' => 'Passwort vergessen', 'sent' => true]);
    }],
    ['GET', '/passwort/neu/([a-f0-9]{48})', function (string $tok) {
        View::render('auth/reset', ['title' => 'Neues Passwort', 'tok' => $tok, 'valid' => (bool)Users::findByResetToken($tok), 'error' => null]);
    }],
    ['POST', '/passwort/neu/([a-f0-9]{48})', function (string $tok) {
        $u = Users::findByResetToken($tok);
        $pw = (string)($_POST['password'] ?? '');
        if ($u && mb_strlen($pw) < 10) {
            View::render('auth/reset', ['title' => 'Neues Passwort', 'tok' => $tok, 'valid' => true, 'error' => 'Das Passwort braucht mindestens 10 Zeichen.']);
            return;
        }
        if (!$u) {
            View::render('auth/reset', ['title' => 'Neues Passwort', 'tok' => $tok, 'valid' => false, 'error' => null]);
            return;
        }
        Users::setPassword((int)$u['id'], $pw);
        Session::flash('ok', 'Dein Passwort wurde geändert. Du kannst dich jetzt anmelden.');
        Http::redirect('/anmelden');
    }],

    // ---------- Bestellstrecke ----------
    ['GET', '/bestellung', function () {
        if (!Cart::items()) {
            Http::redirect('/');
        }
        $u = Users::current();
        if (!$u) {
            $_SESSION['next'] = '/bestellung';
            Http::redirect('/registrieren');
        }
        if (!$u['verified_at']) {
            Http::redirect('/bestaetigen');
        }
        $sum = Cart::summary();
        View::render('checkout', ['title' => 'Bestellung', 'user' => $u, 'cart' => $sum, 'errors' => [], 'form' => $u,
            'step' => Users::profileComplete($u) && !isset($_GET['daten']) ? 3 : 2]);
    }],
    ['POST', '/bestellung/daten', function () {
        $u = Users::requireVerified();
        [$data, $errors] = Users::validateProfile($_POST);
        if ($errors) {
            View::render('checkout', ['title' => 'Bestellung', 'user' => $u, 'cart' => Cart::summary(), 'errors' => $errors, 'form' => $data, 'step' => 2]);
            return;
        }
        Users::saveProfile((int)$u['id'], $data);
        Http::redirect('/bestellung');
    }],
    ['POST', '/bestellung/zahlen', function () {
        $u = Users::requireVerified();
        if (!Users::profileComplete($u) || !isset($_POST['terms'])) {
            Session::flash('error', 'Bitte vervollständige deine Daten und bestätige AGB und Widerrufsbelehrung.');
            Http::redirect('/bestellung');
        }
        [$oid, $errors] = Orders::create($u);
        if ($oid === null) {
            foreach ($errors as $e) {
                Session::flash('error', $e);
            }
            Http::redirect(Cart::items() ? '/bestellung' : '/');
        }
        $order = Db::one('SELECT * FROM orders WHERE id = ?', [$oid]);
        try {
            $pp = PayPal::createOrder($oid, (int)$order['total_cents'], $order['currency'], Config::get('brand') . ' Bestellung #' . $oid);
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            Db::run("UPDATE orders SET status = 'failed' WHERE id = ?", [$oid]);
            Session::flash('error', $e->getMessage());
            Http::redirect('/bestellung');
        }
        Db::run('UPDATE orders SET paypal_order_id = ? WHERE id = ?', [$pp['id'], $oid]);
        $_SESSION['pending_order'] = $oid;
        header('Location: ' . $pp['approve_url'], true, 303);
        exit;
    }],

    // Demo-Zahlung (nur wenn PayPal nicht konfiguriert ist UND debug = true)
    ['GET', '/paypal/demo', function () {
        $u = Users::requireVerified();
        if (!PayPal::isDemo() || !Config::get('debug')) {
            Http::abort(404);
        }
        $o = Db::one('SELECT * FROM orders WHERE paypal_order_id = ? AND user_id = ?', [(string)($_GET['token'] ?? ''), $u['id']]);
        $o || Http::abort(404);
        View::render('paypal_demo', ['title' => 'Demo-Zahlung', 'order' => $o]);
    }],
    ['GET', '/paypal/return', function () {
        $u = Users::requireVerified();
        $token = (string)($_GET['token'] ?? '');
        $o = Db::one('SELECT * FROM orders WHERE paypal_order_id = ? AND user_id = ?', [$token, $u['id']]);
        if (!$o) {
            Http::abort(404, 'Bestellung nicht gefunden.');
        }
        if ($o['status'] === 'pending') {
            try {
                $cap = PayPal::capture($token, (int)$o['total_cents'], $o['currency']);
            } catch (\Throwable $e) {
                error_log($e->getMessage());
                Session::flash('error', 'Die Zahlung konnte nicht abgeschlossen werden. Es wurde nichts berechnet. Bitte versuche es erneut.');
                Http::redirect('/bestellung');
            }
            if (!$cap['ok'] || $cap['amount_cents'] !== (int)$o['total_cents'] || $cap['currency'] !== $o['currency']) {
                Session::flash('error', 'Die Zahlung wurde nicht bestätigt. Bitte versuche es erneut oder wähle eine andere Zahlungsart bei PayPal.');
                Http::redirect('/bestellung');
            }
            Orders::fulfill((int)$o['id'], (string)$cap['capture_id']);
            Cart::clear();
        }
        Http::redirect('/bestellung/fertig/' . (int)$o['id']);
    }],
    ['GET', '/paypal/cancel', function () {
        Session::flash('error', 'Die Zahlung wurde abgebrochen. Deine Auswahl ist noch im Warenkorb.');
        Http::redirect('/bestellung');
    }],
    ['GET', '/bestellung/fertig/(\d+)', function (string $id) {
        $u = Users::requireVerified();
        $o = Db::one('SELECT * FROM orders WHERE id = ? AND user_id = ?', [(int)$id, $u['id']]) ?: Http::abort(404);
        View::render('order_done', ['title' => 'Bestellung abgeschlossen', 'order' => $o,
            'items' => Db::all('SELECT * FROM order_items WHERE order_id = ?', [$o['id']])]);
    }],

    // ---------- Kundenbereich ----------
    ['GET', '/konto', function () {
        $u = Users::requireVerified();
        $domains = Db::all('SELECT * FROM domains WHERE user_id = ? ORDER BY name, tld', [$u['id']]);
        View::render('account/domains', ['title' => 'Meine Domains', 'user' => $u, 'domains' => $domains, 'nav' => 'domains']);
    }],
    ['GET', '/konto/bestellungen', function () {
        $u = Users::requireVerified();
        $orders = Db::all('SELECT * FROM orders WHERE user_id = ? AND status <> ? ORDER BY id DESC', [$u['id'], 'pending']);
        foreach ($orders as &$o) {
            $o['items'] = Db::all('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]);
        }
        View::render('account/orders', ['title' => 'Bestellungen', 'user' => $u, 'orders' => $orders, 'nav' => 'orders']);
    }],
    ['GET', '/konto/profil', function () {
        $u = Users::requireVerified();
        View::render('account/profile', ['title' => 'Meine Daten', 'user' => $u, 'form' => $u, 'errors' => [], 'nav' => 'profile']);
    }],
    ['POST', '/konto/profil', function () {
        $u = Users::requireVerified();
        [$data, $errors] = Users::validateProfile($_POST);
        if ($errors) {
            View::render('account/profile', ['title' => 'Meine Daten', 'user' => $u, 'form' => $data, 'errors' => $errors, 'nav' => 'profile']);
            return;
        }
        Users::saveProfile((int)$u['id'], $data);
        Session::flash('ok', 'Deine Daten wurden gespeichert. Für bestehende Domains änderst du die Inhaberdaten in der jeweiligen Domain.');
        Http::redirect('/konto/profil');
    }],
    ['POST', '/konto/passwort', function () {
        $u = Users::requireVerified();
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify((string)($_POST['current'] ?? ''), $u['password_hash'])) {
            Session::flash('error', 'Das aktuelle Passwort stimmt nicht.');
        } elseif (mb_strlen($new) < 10) {
            Session::flash('error', 'Das neue Passwort braucht mindestens 10 Zeichen.');
        } else {
            Users::setPassword((int)$u['id'], $new);
            Session::flash('ok', 'Passwort geändert.');
        }
        Http::redirect('/konto/profil');
    }],

    ['GET', '/konto/domains/(\d+)', function (string $id) {
        $u = Users::requireVerified();
        $d = Domains::owned((int)$u['id'], (int)$id);
        $tld = Domain::tld($d['tld']);
        View::render('account/domain', ['title' => $d['display'], 'user' => $u, 'd' => $d, 'nav' => 'domains',
            'dns' => Db::all('SELECT * FROM dns_records WHERE domain_id = ? ORDER BY type, host', [$d['id']]),
            'defaultNs' => Domains::usesDefaultNs($d), 'renew' => $tld ? money((int)$tld['renew_cents']) : null,
            'errors' => [], 'form' => $d['owner']]);
    }],

    ['POST', '/konto/domains/(\d+)/nameserver', function (string $id) {
        $u = Users::requireVerified();
        $d = Domains::owned((int)$u['id'], (int)$id);
        if (isset($_POST['reset'])) {
            $ns = (array)Config::get('default_nameservers');
        } else {
            $ns = [];
            foreach ((array)($_POST['ns'] ?? []) as $h) {
                $h = strtolower(trim((string)$h));
                if ($h !== '') {
                    $ns[] = rtrim($h, '.');
                }
            }
            $ns = array_values(array_unique($ns));
            $valid = count($ns) >= 2 && count($ns) <= 5 && !array_filter($ns, fn($h) => !preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $h));
            if (!$valid) {
                Session::flash('error', 'Bitte 2 bis 5 gültige, verschiedene Nameserver angeben (z. B. ns1.beispiel.de).');
                Http::redirect('/konto/domains/' . $d['id']);
            }
        }
        try {
            RegistrarFactory::make()->setNameservers($d['fqdn'], $ns);
            Db::run('UPDATE domains SET nameservers = ? WHERE id = ?', [json_encode($ns), $d['id']]);
            Session::flash('ok', 'Nameserver gespeichert. Die Änderung kann bis zu 24 Stunden brauchen, bis sie weltweit sichtbar ist.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Die Nameserver konnten nicht geändert werden: ' . $e->getMessage());
        }
        Http::redirect('/konto/domains/' . $d['id']);
    }],

    ['POST', '/konto/domains/(\d+)/einstellung', function (string $id) {
        $u = Users::requireVerified();
        $d = Domains::owned((int)$u['id'], (int)$id);
        try {
            if (($_POST['setting'] ?? '') === 'lock') {
                $v = (int)isset($_POST['value']);
                RegistrarFactory::make()->setLock($d['fqdn'], (bool)$v);
                Db::run('UPDATE domains SET locked = ? WHERE id = ?', [$v, $d['id']]);
                Session::flash('ok', $v ? 'Transfersperre aktiviert.' : 'Transfersperre aufgehoben – die Domain kann jetzt mit dem Auth-Code umgezogen werden.');
            } elseif (($_POST['setting'] ?? '') === 'auto_renew') {
                $v = (int)isset($_POST['value']);
                Db::run('UPDATE domains SET auto_renew = ? WHERE id = ?', [$v, $d['id']]);
                Session::flash('ok', $v ? 'Automatische Verlängerung ist an.' : 'Automatische Verlängerung ist aus. Die Domain läuft zum Ablaufdatum aus.');
            }
        } catch (\Throwable $e) {
            Session::flash('error', 'Das hat leider nicht geklappt: ' . $e->getMessage());
        }
        Http::redirect('/konto/domains/' . $d['id']);
    }],

    ['POST', '/konto/domains/(\d+)/authcode', function (string $id) {
        $u = Users::requireVerified();
        $d = Domains::owned((int)$u['id'], (int)$id);
        try {
            $code = RegistrarFactory::make()->authCode($d['fqdn']);
            Db::run('UPDATE domains SET auth_code = ? WHERE id = ?', [$code, $d['id']]);
            $_SESSION['show_auth'] = [$d['id'], $code];
        } catch (\Throwable $e) {
            Session::flash('error', 'Der Auth-Code konnte nicht abgerufen werden: ' . $e->getMessage());
        }
        Http::redirect('/konto/domains/' . $d['id'] . '#transfer');
    }],

    ['POST', '/konto/domains/(\d+)/inhaber', function (string $id) {
        $u = Users::requireVerified();
        $d = Domains::owned((int)$u['id'], (int)$id);
        [$data, $errors] = Users::validateProfile($_POST);
        if ($errors) {
            $tld = Domain::tld($d['tld']);
            View::render('account/domain', ['title' => $d['display'], 'user' => $u, 'd' => $d, 'nav' => 'domains',
                'dns' => Db::all('SELECT * FROM dns_records WHERE domain_id = ? ORDER BY type, host', [$d['id']]),
                'defaultNs' => Domains::usesDefaultNs($d), 'renew' => $tld ? money((int)$tld['renew_cents']) : null,
                'errors' => $errors, 'form' => $data]);
            return;
        }
        $contact = $data + ['email' => $u['email']];
        try {
            RegistrarFactory::make()->updateContact($d['fqdn'], $contact);
            Db::run('UPDATE domains SET contact = ? WHERE id = ?', [json_encode($contact), $d['id']]);
            Session::flash('ok', 'Inhaberdaten gespeichert.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Die Inhaberdaten konnten nicht geändert werden: ' . $e->getMessage());
        }
        Http::redirect('/konto/domains/' . $d['id'] . '#inhaber');
    }],

    ['POST', '/konto/domains/(\d+)/dns', function (string $id) use ($postStr) {
        $u = Users::requireVerified();
        $d = Domains::owned((int)$u['id'], (int)$id);
        $type = strtoupper($postStr('type', 10));
        $host = strtolower($postStr('host', 190)) ?: '@';
        $value = $postStr('value', 500);
        $ttl = max(60, min(86400, (int)($_POST['ttl'] ?? 3600)));
        $prio = $type === 'MX' ? max(0, min(65535, (int)($_POST['prio'] ?? 10))) : null;
        $err = null;
        if (!Domains::usesDefaultNs($d)) {
            $err = 'DNS-Einträge lassen sich nur bearbeiten, wenn die Standard-Nameserver aktiv sind.';
        } elseif (!in_array($type, ['A', 'AAAA', 'CNAME', 'MX', 'TXT'], true)) {
            $err = 'Unbekannter Eintragstyp.';
        } elseif (!preg_match('/^(@|\*|[a-z0-9_*]([a-z0-9._*-]{0,189}))$/', $host)) {
            $err = 'Der Host-Name ist ungültig (z. B. @, www oder mail).';
        } elseif ($type === 'A' && !filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $err = 'Für A-Einträge wird eine IPv4-Adresse benötigt.';
        } elseif ($type === 'AAAA' && !filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $err = 'Für AAAA-Einträge wird eine IPv6-Adresse benötigt.';
        } elseif (in_array($type, ['CNAME', 'MX'], true) && !preg_match('/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}\.?$/i', $value)) {
            $err = 'Für ' . $type . '-Einträge wird ein Hostname benötigt (z. B. mail.beispiel.de).';
        } elseif ($type === 'TXT' && $value === '') {
            $err = 'Bitte einen Wert eingeben.';
        } elseif ($type === 'CNAME' && $host === '@') {
            $err = 'Ein CNAME ist für die Hauptdomain (@) nicht erlaubt.';
        } elseif (Db::one('SELECT COUNT(*) c FROM dns_records WHERE domain_id = ?', [$d['id']])['c'] >= 100) {
            $err = 'Maximal 100 Einträge je Domain.';
        }
        if ($err) {
            Session::flash('error', $err);
        } else {
            Db::run('INSERT INTO dns_records (domain_id, type, host, value, ttl, prio) VALUES (?,?,?,?,?,?)', [$d['id'], $type, $host, $value, $ttl, $prio]);
            Session::flash('ok', 'DNS-Eintrag hinzugefügt.');
        }
        Http::redirect('/konto/domains/' . $d['id'] . '#dns');
    }],
    ['POST', '/konto/domains/(\d+)/dns/(\d+)/loeschen', function (string $id, string $rid) {
        $u = Users::requireVerified();
        $d = Domains::owned((int)$u['id'], (int)$id);
        Db::run('DELETE FROM dns_records WHERE id = ? AND domain_id = ?', [(int)$rid, $d['id']]);
        Session::flash('ok', 'DNS-Eintrag gelöscht.');
        Http::redirect('/konto/domains/' . $d['id'] . '#dns');
    }],

    // ---------- Rechtstexte (Platzhalter) ----------
    ['GET', '/impressum', fn() => View::render('legal', ['title' => 'Impressum', 'kind' => 'Impressum'])],
    ['GET', '/datenschutz', fn() => View::render('legal', ['title' => 'Datenschutz', 'kind' => 'Datenschutzerklärung'])],
    ['GET', '/agb', fn() => View::render('legal', ['title' => 'AGB', 'kind' => 'Allgemeine Geschäftsbedingungen'])],
];
