<?php
declare(strict_types=1);

namespace App\Services;

/**
 * RFC 6238 TOTP (SHA-1, 30 seconds, 6 digits) with an encrypted secret.
 * Enrollment stays off until ZAZ_APP_KEY is set. Existing accounts are not enrolled automatically.
 */
final class Totp
{
    public static function available(): bool
    {
        $key = getenv('ZAZ_APP_KEY');
        return is_string($key) && strlen($key) >= 16 && function_exists('openssl_encrypt');
    }

    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function encrypt(string $secret): string
    {
        $key = self::key();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($secret, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false || strlen($tag) !== 16) {
            throw new \RuntimeException('Could not protect authenticator secret');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $stored): string
    {
        $raw = base64_decode($stored, true);
        if ($raw === false || strlen($raw) < 29) {
            throw new \RuntimeException('Authenticator secret is unreadable');
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new \RuntimeException('Authenticator secret is unreadable');
        }
        return $plain;
    }

    /** @return int|false Accepted time step, or false. */
    public static function verify(string $secret, string $code, int $lastStep): int|false
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $step = intdiv(time(), 30);
        for ($offset = -1; $offset <= 1; $offset++) {
            $candidate = $step + $offset;
            if ($candidate <= $lastStep) {
                continue;
            }
            if (hash_equals(self::codeAt($secret, $candidate), $code)) {
                return $candidate;
            }
        }
        return false;
    }

    public static function provisioningUri(string $username, string $secret): string
    {
        $label = rawurlencode('ZAZ Agriculture:' . $username);
        return 'otpauth://totp/' . $label
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode('ZAZ Agriculture')
            . '&digits=6&period=30';
    }

    public static function codeAt(string $secret, int $step): string
    {
        $binary = self::base32Decode($secret);
        $counter = pack('N*', 0) . pack('N*', $step);
        $hash = hash_hmac('sha1', $counter, $binary, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = (
            ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        return str_pad((string)$value, 6, '0', STR_PAD_LEFT);
    }

    private static function key(): string
    {
        $raw = getenv('ZAZ_APP_KEY');
        if (!is_string($raw) || strlen($raw) < 16) {
            throw new \RuntimeException('ZAZ_APP_KEY is not set');
        }
        return hash('sha256', $raw, true);
    }

    private static function base32Encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $out .= $alphabet[bindec($chunk)];
        }
        return $out;
    }

    private static function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $bits = '';
        $length = strlen($secret);
        for ($i = 0; $i < $length; $i++) {
            $pos = strpos($alphabet, $secret[$i]);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }
        return $bytes;
    }
}
