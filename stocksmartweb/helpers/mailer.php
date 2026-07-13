<?php
/**
 * ============================================================================
 *  StockSmart — Mailer (helpers/mailer.php)
 * ============================================================================
 *  Single entry point: mail_send($to, $subject, $body). Used by
 *  forgot-password.php to deliver password-reset links — registration no
 *  longer requires email verification, so this is its only caller.
 *
 *  Driver is chosen by the MAIL_DRIVER env var (set in .env — see .env.example):
 *    - "smtp": sends a real email via PHPMailer, authenticated against the
 *      SMTP server named by MAIL_SMTP_HOST/MAIL_SMTP_PORT/MAIL_SMTP_USER/
 *      MAIL_SMTP_PASS/MAIL_SMTP_ENCRYPTION. This is the production path —
 *      required for real inboxes (e.g. Gmail) to receive reset emails.
 *    - unset / "log" (default): appends the message to logs/mail.log instead
 *      of sending it. No outbound network call — only meant for local
 *      development when no SMTP credentials are configured yet. The
 *      rendered body is never returned to the browser/API response, so it
 *      can't leak the reset link onto the page even in this mode.
 *
 *  Delivery failures (auth errors, connection refused, etc.) are caught and
 *  logged to logs/mail_errors.log with the SMTP debug transcript, and never
 *  thrown — callers decide how to surface `ok: false` to the user.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * @return array{ok: bool, driver: string, preview: ?string, error: ?string, debug: ?string}
 *   preview is the rendered message body, only populated in "log" mode so
 *   local dev screens could surface it — callers must not forward reset-link
 *   content to the browser (see forgot-password.php).
 *   debug is the full SMTP transcript + config diagnosis, only populated on
 *   failure; callers should only ever expose it to the client when
 *   APP_DEBUG=true (see mail_debug_enabled()).
 */
function mail_send(string $to, string $subject, string $body): array
{
    $driver = app_env('MAIL_DRIVER', 'log');

    if ($driver === 'smtp') {
        return mail_send_smtp($to, $subject, $body);
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

    return ['ok' => true, 'driver' => 'log', 'preview' => $body, 'error' => null, 'debug' => null];
}

/** True when SMTP exceptions/transcripts may be surfaced to the client, not just logged server-side. */
function mail_debug_enabled(): bool
{
    return app_env('APP_DEBUG', 'false') === 'true';
}

/**
 * Checks the exact things Gmail SMTP requires, without ever attempting a
 * connection — catches misconfiguration (empty/placeholder creds, wrong
 * port/encryption pairing, From != Username, an App-Password-shaped check)
 * immediately, with a precise reason instead of a generic connection error.
 *
 * @return string[] human-readable problems found; empty means config looks sane.
 */
function mail_diagnose_config(): array
{
    $issues = [];

    $host       = app_env('MAIL_SMTP_HOST', 'smtp.gmail.com');
    $port       = app_env('MAIL_SMTP_PORT', '587');
    $encryption = strtolower(app_env('MAIL_SMTP_ENCRYPTION', 'tls'));
    $user       = app_env('MAIL_SMTP_USER');
    $pass       = app_env('MAIL_SMTP_PASS');
    $from       = app_env('MAIL_FROM');

    if ($host === '') {
        $issues[] = 'MAIL_SMTP_HOST is empty — expected "smtp.gmail.com" for Gmail.';
    } elseif ($host !== 'smtp.gmail.com') {
        $issues[] = "MAIL_SMTP_HOST is \"{$host}\", not \"smtp.gmail.com\" — double-check this is intentional.";
    }

    if (!in_array($port, ['587', '465'], true)) {
        $issues[] = "MAIL_SMTP_PORT is \"{$port}\" — Gmail expects 587 (STARTTLS) or 465 (implicit SSL).";
    } elseif ($port === '587' && $encryption !== 'tls') {
        $issues[] = "Port 587 requires MAIL_SMTP_ENCRYPTION=tls (STARTTLS), but it's set to \"{$encryption}\".";
    } elseif ($port === '465' && $encryption !== 'ssl') {
        $issues[] = "Port 465 requires MAIL_SMTP_ENCRYPTION=ssl, but it's set to \"{$encryption}\".";
    }

    $userIsPlaceholder = $user === '' || str_starts_with($user, 'REPLACE_WITH');
    if ($userIsPlaceholder) {
        $issues[] = 'MAIL_SMTP_USER is still the placeholder from .env.example — put your real Gmail address in .env.';
    } elseif (!filter_var($user, FILTER_VALIDATE_EMAIL)) {
        $issues[] = "MAIL_SMTP_USER (\"{$user}\") is not a valid email address.";
    }

    $passIsPlaceholder = $pass === '' || str_starts_with($pass, 'REPLACE_WITH');
    if ($passIsPlaceholder) {
        $issues[] = 'MAIL_SMTP_PASS is still the placeholder from .env.example — generate a Gmail App Password at https://myaccount.google.com/apppasswords and put it in .env.';
    } else {
        $normalizedPass = str_replace(' ', '', $pass);
        if (strlen($normalizedPass) !== 16 || !ctype_alnum($normalizedPass)) {
            $issues[] = 'MAIL_SMTP_PASS does not look like a Gmail App Password (should be exactly 16 letters/digits, no spaces once normalized) — this looks like it might be your regular Gmail account password instead, which Gmail SMTP will reject.';
        }
    }

    $fromIsPlaceholder = $from === '' || str_starts_with($from, 'REPLACE_WITH');
    if ($fromIsPlaceholder) {
        $issues[] = 'MAIL_FROM is still the placeholder from .env.example.';
    } elseif (!$userIsPlaceholder && strcasecmp($from, $user) !== 0) {
        $issues[] = "MAIL_FROM (\"{$from}\") does not match MAIL_SMTP_USER (\"{$user}\") — Gmail SMTP rejects sending From an address other than the authenticated account.";
    }

    return $issues;
}

function mail_send_smtp(string $to, string $subject, string $body): array
{
    $configIssues = mail_diagnose_config();
    if (!empty($configIssues)) {
        $summary = implode(' | ', $configIssues);
        mail_log_error($to, $subject, "Config check failed before attempting to connect: {$summary}", $configIssues, []);

        return [
            'ok'      => false,
            'driver'  => 'smtp',
            'preview' => null,
            'error'   => 'SMTP is misconfigured: ' . $configIssues[0],
            'debug'   => mail_debug_enabled() ? implode("\n", $configIssues) : null,
        ];
    }

    $mail = new PHPMailer(true);
    $transcript = [];

    try {
        $mail->isSMTP();
        $mail->Host       = app_env('MAIL_SMTP_HOST', 'smtp.gmail.com');
        $mail->Port       = (int) app_env('MAIL_SMTP_PORT', '587');
        $mail->SMTPAuth   = true;
        $mail->Username   = app_env('MAIL_SMTP_USER');
        $mail->Password   = app_env('MAIL_SMTP_PASS');

        $encryption = app_env('MAIL_SMTP_ENCRYPTION', 'tls');
        $mail->SMTPSecure = $encryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPAutoTLS = true;

        // Full SMTP client<->server conversation (auth challenge/response,
        // TLS negotiation, RCPT/DATA replies) — captured for the log file
        // and, in debug mode, the API response. DEBUG_SERVER excludes the
        // lowest-level packet dump, keeping the transcript readable.
        $mail->SMTPDebug   = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function (string $str, int $level) use (&$transcript): void {
            $transcript[] = trim($str);
        };

        $mail->CharSet = 'UTF-8';
        $mail->setFrom(
            app_env('MAIL_FROM', $mail->Username),
            app_env('MAIL_FROM_NAME', 'StockSmart')
        );
        $mail->addAddress($to);

        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->isHTML(false);

        $mail->send();

        return ['ok' => true, 'driver' => 'smtp', 'preview' => null, 'error' => null, 'debug' => null];
    } catch (PHPMailerException $e) {
        $errorMessage = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
        mail_log_error($to, $subject, $errorMessage, [], $transcript);

        $debugText = null;
        if (mail_debug_enabled()) {
            $debugText = $errorMessage;
            if (!empty($transcript)) {
                $debugText .= "\n\nSMTP transcript:\n" . implode("\n", $transcript);
            }
        }

        return ['ok' => false, 'driver' => 'smtp', 'preview' => null, 'error' => $errorMessage, 'debug' => $debugText];
    }
}

/**
 * @param string[] $configIssues
 * @param string[] $transcript
 */
function mail_log_error(string $to, string $subject, string $error, array $configIssues = [], array $transcript = []): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    $lines = [
        sprintf('[%s] FAILED TO: %s | SUBJECT: %s', date('Y-m-d H:i:s'), $to, $subject),
        "ERROR: {$error}",
    ];

    if (!empty($configIssues)) {
        $lines[] = 'CONFIG ISSUES:';
        foreach ($configIssues as $issue) {
            $lines[] = "  - {$issue}";
        }
    }

    if (!empty($transcript)) {
        $lines[] = 'SMTP TRANSCRIPT:';
        foreach ($transcript as $line) {
            $lines[] = "  {$line}";
        }
    }

    $lines[] = str_repeat('-', 70);
    $lines[] = '';

    @file_put_contents($logDir . '/mail_errors.log', implode("\n", $lines) . "\n", FILE_APPEND | LOCK_EX);
    error_log("StockSmart mail_send failed for {$to}: {$error}");
}

function mail_render_reset_link(string $fullName, string $resetUrl): string
{
    return "Hi {$fullName},\n\nA password reset was requested for your StockSmart account.\n"
        . "Reset your password using the link below (valid for 30 minutes):\n\n{$resetUrl}\n\n"
        . "If you did not request this, you can ignore this email.";
}
