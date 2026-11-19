<?php

declare(strict_types=1);

namespace App\Support;

final class Totp
{
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function currentCode(string $secret, ?int $timestamp = null): string
    {
        $key = self::base32Decode($secret);

        return self::code($key, intdiv($timestamp ?? time(), 30));
    }

    public static function verify(string $secret, string $code): bool
    {
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        $key = self::base32Decode($secret);
        $counter = intdiv(time(), 30);

        foreach ([-1, 0, 1] as $offset) {
            if (hash_equals(self::code($key, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function provisioningUri(string $email, string $secret): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&digits=6&period=30',
            rawurlencode('OneFlo'),
            rawurlencode($email),
            $secret,
            rawurlencode('OneFlo'),
        );
    }

    private static function code(string $key, int $counter): string
    {
        $binary = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $binary, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $truncated = unpack('N', substr($hash, $offset, 4));
        $value = ($truncated[1] ?? 0) & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $length = strlen($data);

        for ($index = 0; $index < $length; $index++) {
            $bits .= str_pad(decbin(ord($data[$index])), 8, '0', STR_PAD_LEFT);
        }

        $secret = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $secret .= $alphabet[bindec($chunk)];
        }

        return $secret;
    }

    private static function base32Decode(string $secret): string
    {
        $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
        $bits = '';

        foreach (str_split(strtoupper($secret)) as $character) {
            if (! isset($alphabet[$character])) {
                continue;
            }
            $bits .= str_pad(decbin($alphabet[$character]), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(bindec($chunk));
            }
        }

        return $decoded;
    }
}
