<?php
declare(strict_types=1);

// Front-Controller. Mit dem PHP-Entwicklungsserver: php -S localhost:8080 -t public public/index.php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (PHP_SAPI === 'cli-server' && $path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

$root = dirname(__DIR__);
foreach (['Core', 'helpers', 'Domain', 'Registrar/Registrar', 'Registrar/MockRegistrar', 'Mailer', 'PayPal', 'Shop'] as $f) {
    require "$root/src/$f.php";
}

use App\{Config, Http, Session};

Config::load(require "$root/config.php");
error_reporting(E_ALL);
ini_set('display_errors', Config::get('debug') ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set('Europe/Berlin');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self' https://www.paypal.com https://www.sandbox.paypal.com; frame-ancestors 'none'");
if (Http::isHttps()) {
    header('Strict-Transport-Security: max-age=31536000');
}

Session::start();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$routes = require "$root/src/routes.php";

try {
    foreach ($routes as [$m, $pattern, $handler]) {
        if ($m === $method && preg_match('#^' . $pattern . '$#', rtrim($path, '/') ?: '/', $mt)) {
            if ($m === 'POST') {
                Session::checkCsrf();
            }
            $handler(...array_slice($mt, 1));
            exit;
        }
    }
    Http::abort(404, 'Diese Seite gibt es nicht.');
} catch (\Throwable $e) {
    error_log((string)$e);
    Http::abort(500, Config::get('debug') ? $e->getMessage() : 'Es ist ein Fehler aufgetreten. Bitte versuche es später erneut.');
}
