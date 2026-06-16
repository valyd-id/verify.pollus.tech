<?php

namespace App\Support;

/**
 * Minimal stateless session token for the developer console (no extra deps).
 * Format: base64url(json payload) . base64url(hmac-sha256). Signed with APP_KEY.
 */
class ConsoleToken
{
    private const TTL = 60 * 60 * 24 * 7; // 7 days

    private static function secret(): string
    {
        return (string) config('app.key');
    }

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private static function unb64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'));
    }

    public static function issue(int $userId): string
    {
        $payload = json_encode(['sub' => $userId, 'iat' => time(), 'exp' => time() + self::TTL]);
        $body = self::b64($payload);
        $sig = self::b64(hash_hmac('sha256', $body, self::secret(), true));
        return $body . '.' . $sig;
    }

    /** Returns the user id, or null if invalid/expired. */
    public static function verify(?string $token): ?int
    {
        if (!$token || substr_count($token, '.') !== 1) {
            return null;
        }
        [$body, $sig] = explode('.', $token, 2);
        $expected = self::b64(hash_hmac('sha256', $body, self::secret(), true));
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        $payload = json_decode(self::unb64($body), true);
        if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) {
            return null;
        }
        return (int) ($payload['sub'] ?? 0) ?: null;
    }
}
