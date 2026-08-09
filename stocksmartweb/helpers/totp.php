<?php
/**
 * ============================================================================
 *  StockSmart — TOTP (RFC 6238) Module (helpers/totp.php)
 * ============================================================================
 *  Provides Base32 secret generation, HMAC-SHA1 TOTP code calculation,
 *  6-digit code verification, and provisioning URI generation compatible with
 *  Ente Auth, Google Authenticator, Authy, and standard Auth apps.
 * ============================================================================
 */

declare(strict_types=1);

/**
 * Generate a random Base32 secret key (16 characters by default).
 */
function totp_generate_secret(int $length = 16): string
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[random_int(0, 31)];
    }
    return $secret;
}

/**
 * Decode a Base32 string to binary data.
 */
function totp_base32_decode(string $base32): string
{
    $base32 = strtoupper($base32);
    $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $base32Lookup = array_flip(str_split($base32Chars));

    $binary = '';
    $buffer = 0;
    $bitsLeft = 0;

    for ($i = 0, $len = strlen($base32); $i < $len; $i++) {
        $char = $base32[$i];
        if (!isset($base32Lookup[$char])) {
            continue;
        }

        $buffer = ($buffer << 5) | $base32Lookup[$char];
        $bitsLeft += 5;

        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $binary .= chr(($buffer >> $bitsLeft) & 0xFF);
        }
    }

    return $binary;
}

/**
 * Calculate the HMAC-SHA1 TOTP code for a secret and time step.
 */
function totp_calculate_code(string $secret, ?int $timeSlice = null): string
{
    if ($timeSlice === null) {
        $timeSlice = (int) floor(time() / 30);
    }

    $secretBinary = totp_base32_decode($secret);

    // Pack time into 8-byte big-endian binary string
    $timeBinary = pack('N*', 0, $timeSlice);

    // HMAC-SHA1
    $hmac = hash_hmac('sha1', $timeBinary, $secretBinary, true);

    // Dynamic truncation
    $offset = ord($hmac[strlen($hmac) - 1]) & 0x0F;
    $value = (
        ((ord($hmac[$offset]) & 0x7F) << 24) |
        ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
        ((ord($hmac[$offset + 2]) & 0xFF) << 8) |
        (ord($hmac[$offset + 3]) & 0xFF)
    );

    $code = $value % 1000000;
    return sprintf('%06d', $code);
}

/**
 * Verify a user-provided 6-digit TOTP code against a secret.
 * Allows for a time window discrepancy (+/- 1 time slice = 30 seconds).
 */
function totp_verify_code(string $secret, string $code, int $discrepancy = 1): bool
{
    $code = trim($code);
    if (strlen($code) !== 6 || !ctype_digit($code)) {
        return false;
    }

    $currentTimeSlice = (int) floor(time() / 30);

    for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
        $calculatedCode = totp_calculate_code($secret, $currentTimeSlice + $i);
        if (hash_equals($calculatedCode, $code)) {
            return true;
        }
    }

    return false;
}

/**
 * Returns an otpauth:// URL suitable for generating a QR Code.
 */
function totp_get_provisioning_uri(string $accountName, string $secret, string $issuer = 'StockSmart'): string
{
    $encodedAccount = rawurlencode($accountName);
    $encodedIssuer  = rawurlencode($issuer);
    return "otpauth://totp/{$encodedIssuer}:{$encodedAccount}?secret={$secret}&issuer={$encodedIssuer}&algorithm=SHA1&digits=6&period=30";
}
