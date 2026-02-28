<?php

namespace Escalated\Laravel\Services;

class TwoFactorService
{
    /**
     * Base32 alphabet for secret generation.
     */
    protected string $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a random 16-character base32 secret.
     */
    public function generateSecret(): string
    {
        $secret = '';
        $bytes = random_bytes(16);

        for ($i = 0; $i < 16; $i++) {
            $secret .= $this->base32Chars[ord($bytes[$i]) % 32];
        }

        return $secret;
    }

    /**
     * Generate an otpauth:// URI for QR code display.
     */
    public function generateQrUri(string $secret, string $email): string
    {
        $issuer = config('app.name', 'Escalated');
        $label = rawurlencode($issuer.':'.$email);
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ]);

        return "otpauth://totp/{$label}?{$params}";
    }

    /**
     * Verify a TOTP code against a secret.
     *
     * Uses a time window of +/- 1 period (30 seconds) for clock drift tolerance.
     */
    public function verify(string $secret, string $code): bool
    {
        $timeSlice = floor(time() / 30);

        // Check current time slice and +/- 1 for clock drift
        for ($i = -1; $i <= 1; $i++) {
            $calculatedCode = $this->generateTotp($secret, $timeSlice + $i);

            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a TOTP code for a given time slice.
     */
    protected function generateTotp(string $secret, int $timeSlice): string
    {
        $secretKey = $this->base32Decode($secret);

        // Pack the time slice as a 64-bit big-endian integer
        $time = pack('N*', 0, $timeSlice);

        // HMAC-SHA1
        $hmac = hash_hmac('sha1', $time, $secretKey, true);

        // Dynamic truncation
        $offset = ord($hmac[strlen($hmac) - 1]) & 0x0F;
        $code = (
            ((ord($hmac[$offset]) & 0x7F) << 24) |
            ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
            ((ord($hmac[$offset + 2]) & 0xFF) << 8) |
            (ord($hmac[$offset + 3]) & 0xFF)
        ) % 1000000;

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Decode a base32-encoded string.
     */
    protected function base32Decode(string $input): string
    {
        $map = array_flip(str_split($this->base32Chars));
        $input = strtoupper($input);
        $input = rtrim($input, '=');

        $binaryString = '';
        foreach (str_split($input) as $char) {
            if (! isset($map[$char])) {
                continue;
            }
            $binaryString .= str_pad(decbin($map[$char]), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        for ($i = 0; $i + 8 <= strlen($binaryString); $i += 8) {
            $output .= chr(bindec(substr($binaryString, $i, 8)));
        }

        return $output;
    }

    /**
     * Generate an array of 8 random recovery codes.
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4))).'-'.strtoupper(bin2hex(random_bytes(4)));
        }

        return $codes;
    }
}
