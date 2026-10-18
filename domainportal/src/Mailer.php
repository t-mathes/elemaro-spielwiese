<?php
declare(strict_types=1);

namespace App;

final class Mailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        $from = (string)Config::get('mail.from');
        $driver = Config::get('mail.driver');
        $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = ["From: " . Config::get('brand') . " <$from>", 'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: 8bit'];

        if ($driver === 'mail') {
            return mail($to, $subjectEnc, $body, implode("\r\n", $headers));
        }
        if ($driver === 'smtp') {
            try {
                return self::smtp($to, $subjectEnc, $body, $from, $headers);
            } catch (\Throwable $e) {
                error_log('SMTP: ' . $e->getMessage());
                return false;
            }
        }
        $line = sprintf("---- %s ----\nTo: %s\nSubject: %s\n\n%s\n\n", now(), $to, $subject, $body);
        return file_put_contents(dirname(__DIR__) . '/var/mail.log', $line, FILE_APPEND | LOCK_EX) !== false;
    }

    private static function smtp(string $to, string $subject, string $body, string $from, array $headers): bool
    {
        $c = Config::get('mail.smtp');
        $secure = $c['secure'] ?? 'tls';
        $host = ($secure === 'ssl' ? 'ssl://' : '') . $c['host'];
        $fp = stream_socket_client("$host:{$c['port']}", $errno, $err, 10);
        if (!$fp) {
            throw new \RuntimeException("Verbindung fehlgeschlagen: $err");
        }
        stream_set_timeout($fp, 10);
        $read = function () use ($fp): string {
            $out = '';
            while (($l = fgets($fp, 515)) !== false) {
                $out .= $l;
                if (strlen($l) < 4 || $l[3] === ' ') {
                    break;
                }
            }
            return $out;
        };
        $cmd = function (string $line, string $expect) use ($fp, $read): void {
            fwrite($fp, $line . "\r\n");
            $r = $read();
            if (!str_starts_with($r, $expect)) {
                throw new \RuntimeException("SMTP erwartete $expect, bekam: " . trim($r));
            }
        };
        $read();
        $name = parse_url(Http::baseUrl(), PHP_URL_HOST) ?: 'localhost';
        $cmd("EHLO $name", '250');
        if ($secure === 'tls') {
            $cmd('STARTTLS', '220');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('TLS fehlgeschlagen');
            }
            $cmd("EHLO $name", '250');
        }
        if (!empty($c['user'])) {
            $cmd('AUTH LOGIN', '334');
            $cmd(base64_encode($c['user']), '334');
            $cmd(base64_encode($c['pass']), '235');
        }
        $cmd("MAIL FROM:<$from>", '250');
        $cmd("RCPT TO:<$to>", '250');
        $cmd('DATA', '354');
        $msg = implode("\r\n", array_merge($headers, ["To: $to", "Subject: $subject", 'Date: ' . date('r')]))
            . "\r\n\r\n" . preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], "\r\n", $body));
        $cmd($msg . "\r\n.", '250');
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return true;
    }
}
