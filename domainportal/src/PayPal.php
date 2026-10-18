<?php
declare(strict_types=1);

namespace App;

/** PayPal Orders API v2 (Redirect-Flow: Kunde bestätigt bei PayPal, wir erfassen die Zahlung serverseitig). */
final class PayPal
{
    public static function isDemo(): bool
    {
        return Config::get('paypal.client_id') === '' || Config::get('paypal.client_secret') === '';
    }

    /** Demo-Zahlung (ohne PayPal) gibt es nur im Debug-Modus, damit in Produktion nie gratis bestellt werden kann. */
    private static function assertUsable(): void
    {
        if (self::isDemo() && !Config::get('debug')) {
            throw new \RuntimeException('Die Zahlung ist derzeit nicht verfügbar (PayPal nicht konfiguriert).');
        }
    }

    private static function base(): string
    {
        return Config::get('paypal.mode') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    private static function token(): string
    {
        $r = self::curl('POST', '/v1/oauth2/token', 'grant_type=client_credentials', [
            'Content-Type: application/x-www-form-urlencoded',
        ], Config::get('paypal.client_id') . ':' . Config::get('paypal.client_secret'));
        return $r['access_token'] ?? throw new \RuntimeException('PayPal-Anmeldung fehlgeschlagen.');
    }

    private static function curl(string $method, string $path, string $body, array $headers, ?string $userpwd = null): array
    {
        $ch = curl_init(self::base() . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $body,
        ]);
        if ($userpwd) {
            curl_setopt($ch, CURLOPT_USERPWD, $userpwd);
        }
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($raw === false) {
            throw new \RuntimeException('PayPal nicht erreichbar: ' . curl_error($ch));
        }
        $json = json_decode((string)$raw, true) ?: [];
        if ($code >= 400) {
            error_log("PayPal $method $path -> $code $raw");
            throw new \RuntimeException('PayPal hat die Anfrage abgelehnt (' . $code . ').');
        }
        return $json;
    }

    /** @return array{id:string,approve_url:string} */
    public static function createOrder(int $orderId, int $totalCents, string $currency, string $description): array
    {
        self::assertUsable();
        if (self::isDemo()) {
            $id = 'DEMO-' . bin2hex(random_bytes(8));
            return ['id' => $id, 'approve_url' => '/paypal/demo?token=' . $id];
        }
        $base = Http::baseUrl();
        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => 'order-' . $orderId,
                'custom_id' => (string)$orderId,
                'description' => mb_substr($description, 0, 120),
                'amount' => ['currency_code' => $currency, 'value' => number_format($totalCents / 100, 2, '.', '')],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'brand_name' => Config::get('brand'), 'locale' => 'de-DE', 'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
                'return_url' => $base . '/paypal/return', 'cancel_url' => $base . '/paypal/cancel',
            ]]],
        ];
        $r = self::curl('POST', '/v2/checkout/orders', json_encode($payload), [
            'Content-Type: application/json', 'Authorization: Bearer ' . self::token(),
            'PayPal-Request-Id: dp-order-' . $orderId . '-' . bin2hex(random_bytes(4)),
        ]);
        $url = null;
        foreach ($r['links'] ?? [] as $l) {
            if (in_array($l['rel'] ?? '', ['payer-action', 'approve'], true)) {
                $url = $l['href'];
            }
        }
        if (empty($r['id']) || !$url) {
            throw new \RuntimeException('PayPal-Bestellung konnte nicht angelegt werden.');
        }
        return ['id' => $r['id'], 'approve_url' => $url];
    }

    /** @return array{ok:bool,capture_id:?string,amount_cents:int,currency:string} */
    public static function capture(string $paypalOrderId, int $expectedCents, string $currency): array
    {
        self::assertUsable();
        if (self::isDemo()) {
            return ['ok' => str_starts_with($paypalOrderId, 'DEMO-'), 'capture_id' => 'DEMO-CAP', 'amount_cents' => $expectedCents, 'currency' => $currency];
        }
        $r = self::curl('POST', '/v2/checkout/orders/' . rawurlencode($paypalOrderId) . '/capture', '{}', [
            'Content-Type: application/json', 'Authorization: Bearer ' . self::token(),
            'PayPal-Request-Id: dp-capture-' . $paypalOrderId,
        ]);
        $cap = $r['purchase_units'][0]['payments']['captures'][0] ?? null;
        if (!$cap) {
            return ['ok' => false, 'capture_id' => null, 'amount_cents' => 0, 'currency' => ''];
        }
        return [
            'ok' => ($r['status'] ?? '') === 'COMPLETED' && ($cap['status'] ?? '') === 'COMPLETED',
            'capture_id' => $cap['id'] ?? null,
            'amount_cents' => (int)round(((float)$cap['amount']['value']) * 100),
            'currency' => $cap['amount']['currency_code'] ?? '',
        ];
    }
}
