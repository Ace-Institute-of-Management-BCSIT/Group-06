<?php
/**
 * ============================================================================
 *  StockSmart — Mailer (helpers/mailer.php)
 * ============================================================================
 *  Single entry point: mail_send($to, $subject, $body).
 *
 *  Driver is chosen by the MAIL_DRIVER env var:
 *    - unset / "log" (default): appends the message to logs/mail.log and
 *      returns it so the calling page can show it in a dev banner. No
 *      outbound network call — safe for local development with no SMTP
 *      credentials configured.
 *    - "smtp": sends via PHP's mail() using the sendmail/SMTP settings in
 *      php.ini, or through MAIL_SMTP_HOST/MAIL_SMTP_USER/MAIL_SMTP_PASS if a
 *      PHPMailer-based transport is later wired in here. Call sites never
 *      change when the driver changes.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

/**
 * @return array{ok: bool, driver: string, preview: ?string}
 *   preview is the rendered message body, only populated in "log" mode so
 *   dev screens can surface the OTP/reset link without a real inbox.
 */
function mail_send(string $to, string $subject, string $body): array
{
    $driver = app_env('MAIL_DRIVER', 'log');

    if ($driver === 'smtp') {
        $headers = "From: " . app_env('MAIL_FROM', 'no-reply@stocksmart.local') . "\r\nContent-Type: text/plain; charset=utf-8";
        $ok = @mail($to, $subject, $body, $headers);
        return ['ok' => $ok, 'driver' => 'smtp', 'preview' => null];
    }

    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $entry = sprintf(
        "[%s] TO: %s | SUBJECT: %s\n%s\n%s\n\n",
        date('Y-m-d H:i:s'),
        $to,
        $subject,
        str_repeat('-', 60),
        $body
    );
    @file_put_contents($logDir . '/mail.log', $entry, FILE_APPEND | LOCK_EX);

    return ['ok' => true, 'driver' => 'log', 'preview' => $body];
}

function mail_render_otp(string $fullName, string $otp): string
{
    return "Hi {$fullName},\n\nYour StockSmart verification code is: {$otp}\n"
        . "This code expires in 10 minutes.\n\nIf you did not request this, you can ignore this email.";
}

function mail_render_reset_link(string $fullName, string $resetUrl): string
{
    return "Hi {$fullName},\n\nA password reset was requested for your StockSmart account.\n"
        . "Reset your password using the link below (valid for 30 minutes):\n\n{$resetUrl}\n\n"
        . "If you did not request this, you can ignore this email.";
}
