<?php
declare(strict_types=1);

namespace App;

/** Konfiguration, Datenbank, Session, CSRF und kleine Helfer an einem Ort. */
final class Config
{
    private static array $data = [];

    public static function load(array $data): void
    {
        self::$data = $data;
    }

    public static function get(string $path, mixed $default = null): mixed
    {
        $v = self::$data;
        foreach (explode('.', $path) as $k) {
            if (!is_array($v) || !array_key_exists($k, $v)) {
                return $default;
            }
            $v = $v[$k];
        }
        return $v;
    }
}

final class Db
{
    private static ?\PDO $pdo = null;

    public static function pdo(): \PDO
    {
        if (self::$pdo === null) {
            $dsn = (string)Config::get('db.dsn');
            self::$pdo = new \PDO($dsn, Config::get('db.user'), Config::get('db.pass'), [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            if (str_starts_with($dsn, 'sqlite:')) {
                self::$pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;');
            }
            self::migrate(self::$pdo, str_starts_with($dsn, 'sqlite:'));
        }
        return self::$pdo;
    }

    /** Legt das Schema an, falls noch nicht vorhanden. */
    private static function migrate(\PDO $pdo, bool $sqlite): void
    {
        $exists = $sqlite
            ? $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch()
            : $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        if ($exists) {
            return;
        }
        $sql = file_get_contents(dirname(__DIR__) . '/schema.sql');
        $sql = str_replace('{PK}', $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            $pdo->exec($stmt);
        }
        // Start-Endungen mit Beispielpreisen (brutto, in Cent) – im Betrieb per bin/tlds.php anpassen.
        $ins = $pdo->prepare('INSERT INTO tlds (tld, price_cents, renew_cents, active, sort) VALUES (?,?,?,1,?)');
        foreach ([['de', 899, 1199], ['com', 1299, 1799], ['net', 1399, 1899], ['org', 1299, 1699], ['eu', 899, 1299],
                  ['info', 1199, 1999], ['io', 4999, 5999], ['shop', 1999, 3999], ['online', 1499, 3499], ['app', 1699, 2199]] as $i => [$t, $p, $r]) {
            $ins->execute([$t, $p, $r, ($i + 1) * 10]);
        }
    }

    public static function all(string $sql, array $p = []): array
    {
        $s = self::pdo()->prepare($sql);
        $s->execute($p);
        return $s->fetchAll();
    }

    public static function one(string $sql, array $p = []): ?array
    {
        $s = self::pdo()->prepare($sql);
        $s->execute($p);
        return $s->fetch() ?: null;
    }

    public static function run(string $sql, array $p = []): int
    {
        $s = self::pdo()->prepare($sql);
        $s->execute($p);
        return $s->rowCount();
    }

    public static function insert(string $sql, array $p = []): int
    {
        self::run($sql, $p);
        return (int)self::pdo()->lastInsertId();
    }
}

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('dp_session');
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/', 'httponly' => true,
            'secure' => Http::isHttps(), 'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function userId(): ?int
    {
        return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
    }

    public static function login(int $uid): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = $uid;
    }

    public static function logout(): void
    {
        $cart = $_SESSION['cart'] ?? [];
        $_SESSION = ['cart' => $cart];
        session_regenerate_id(true);
    }

    public static function flash(string $type, string $msg): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
    }

    public static function takeFlash(): array
    {
        $f = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $f;
    }

    public static function csrf(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(24));
    }

    public static function checkCsrf(): void
    {
        $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($sent) || !hash_equals(self::csrf(), $sent)) {
            Http::abort(419, 'Die Sitzung ist abgelaufen. Bitte die Seite neu laden und erneut versuchen.');
        }
    }

    /** Sehr einfache Begrenzung: max. $limit Aufrufe je $window Sekunden und Schlüssel (pro Sitzung + IP). */
    public static function throttle(string $key, int $limit, int $window): bool
    {
        $now = time();
        $k = 'rl_' . $key;
        $hits = array_filter($_SESSION[$k] ?? [], fn($t) => $t > $now - $window);
        if (count($hits) >= $limit) {
            $_SESSION[$k] = $hits;
            return false;
        }
        $hits[] = $now;
        $_SESSION[$k] = $hits;
        return true;
    }
}

final class Http
{
    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public static function baseUrl(): string
    {
        $cfg = (string)Config::get('base_url', '');
        if ($cfg !== '') {
            return rtrim($cfg, '/');
        }
        return (self::isHttps() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    public static function redirect(string $path): never
    {
        // nur interne Ziele erlauben
        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
            $path = '/';
        }
        header('Location: ' . $path, true, 303);
        exit;
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function abort(int $status, string $message = ''): never
    {
        http_response_code($status);
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            self::json(['error' => $message ?: 'Fehler'], $status);
        }
        View::render('error', ['status' => $status, 'message' => $message, 'title' => 'Fehler ' . $status]);
        exit;
    }
}

final class View
{
    public static function render(string $tpl, array $vars = []): void
    {
        $vars += ['title' => null];
        extract($vars, EXTR_SKIP);
        ob_start();
        require dirname(__DIR__) . '/templates/' . $tpl . '.php';
        $content = ob_get_clean();
        $layout = $vars['layout'] ?? 'layout';
        require dirname(__DIR__) . '/templates/' . $layout . '.php';
    }
}
