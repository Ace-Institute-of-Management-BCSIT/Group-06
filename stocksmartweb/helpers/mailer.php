<?php
/**
 * ============================================================================
 *  StockSmart — Mailer (helpers/mailer.php)
 * ============================================================================
 *  Single entry point: mail_send($to, $subject, $body, $isHtml = false).
 *  Supports:
 *    - "brevo": sends real email via Brevo HTTP REST API (https://api.brevo.com/v3/smtp/email)
 *               using BREVO_API_KEY from environment variables (.env / webserver).
 *    - "smtp": sends real email via PHPMailer SMTP.
 *    - "log" (default fallback): appends the message to logs/mail.log.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Send email using the configured driver (brevo, smtp, or log).
 *
 * @return array{ok: bool, driver: string, preview: ?string, error: ?string, debug: ?string}
 */
function mail_send(string $to, string $subject, string $body, bool $isHtml = false): array
{
    $driver = app_env('MAIL_DRIVER', '');
    $brevoKey = app_env('BREVO_API_KEY', '');

    if ($driver === 'brevo' || $brevoKey !== '') {
        return mail_send_brevo($to, $subject, $body, $isHtml);
    }

    if ($driver === 'smtp') {
        return mail_send_smtp($to, $subject, $body, $isHtml);
    }

    // Default to log driver
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

/**
 * Sends email using the Brevo HTTP REST API (v3).
 */
function mail_send_brevo(string $to, string $subject, string $body, bool $isHtml = false): array
{
    $apiKey = app_env('BREVO_API_KEY', '');
    if ($apiKey === '') {
        $apiKey = app_env('MAIL_SMTP_PASS', '');
    }

    if ($apiKey === '') {
        return [
            'ok'      => false,
            'driver'  => 'brevo',
            'preview' => null,
            'error'   => 'BREVO_API_KEY is not set in environment or .env file.',
            'debug'   => mail_debug_enabled() ? 'Missing BREVO_API_KEY' : null,
        ];
    }

    $senderEmail = app_env('MAIL_FROM', 'noreply@stocksmart.live');
    $senderName  = app_env('MAIL_FROM_NAME', 'StockSmart');

    $payload = [
        'sender' => [
            'name'  => $senderName,
            'email' => $senderEmail,
        ],
        'to' => [
            ['email' => $to]
        ],
        'subject' => $subject,
    ];

    if ($isHtml || str_contains($body, '<html') || str_contains($body, '<p>') || str_contains($body, '<div')) {
        $payload['htmlContent'] = $body;
    } else {
        $payload['textContent'] = $body;
        $payload['htmlContent'] = nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    $jsonPayload = json_encode($payload);

    if (function_exists('curl_init')) {
        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            mail_log_error($to, $subject, "Brevo cURL error: {$curlErr}");
            return [
                'ok'      => false,
                'driver'  => 'brevo',
                'preview' => null,
                'error'   => "Network error sending email via Brevo: {$curlErr}",
                'debug'   => mail_debug_enabled() ? $curlErr : null,
            ];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['ok' => true, 'driver' => 'brevo', 'preview' => null, 'error' => null, 'debug' => null];
        }

        $errMsg = "Brevo API returned HTTP {$httpCode}: {$response}";
        mail_log_error($to, $subject, $errMsg);
        return [
            'ok'      => false,
            'driver'  => 'brevo',
            'preview' => null,
            'error'   => "Brevo email delivery failed (HTTP {$httpCode}).",
            'debug'   => mail_debug_enabled() ? $errMsg : null,
        ];
    } else {
        // Fallback using file_get_contents stream context
        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => "accept: application/json\r\n" .
                             "api-key: {$apiKey}\r\n" .
                             "content-type: application/json\r\n",
                'content' => $jsonPayload,
                'timeout' => 15,
                'ignore_errors' => true,
            ]
        ];

        $context  = stream_context_create($opts);
        $response = @file_get_contents('https://api.brevo.com/v3/smtp/email', false, $context);
        $headers  = $http_response_header ?? [];
        $statusLine = $headers[0] ?? '';

        if (str_contains($statusLine, '200') || str_contains($statusLine, '201')) {
            return ['ok' => true, 'driver' => 'brevo', 'preview' => null, 'error' => null, 'debug' => null];
        }

        $errMsg = "Brevo stream delivery failed: {$statusLine} — {$response}";
        mail_log_error($to, $subject, $errMsg);
        return [
            'ok'      => false,
            'driver'  => 'brevo',
            'preview' => null,
            'error'   => 'Brevo email delivery failed.',
            'debug'   => mail_debug_enabled() ? $errMsg : null,
        ];
    }
}

/** True when SMTP/API exceptions may be surfaced to the client for debugging. */
function mail_debug_enabled(): bool
{
    return app_env('APP_DEBUG', 'false') === 'true';
}

function mail_send_smtp(string $to, string $subject, string $body, bool $isHtml = false): array
{
    if (!class_exists(PHPMailer::class)) {
        return [
            'ok'      => false,
            'driver'  => 'smtp',
            'preview' => null,
            'error'   => 'PHPMailer is not installed.',
            'debug'   => null,
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
        $mail->isHTML($isHtml);

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

/**
 * Render a beautiful HTML template for OTP verification.
 */
function mail_render_otp(string $fullName, string $otpCode, string $type = 'register'): string
{
    $heading = $type === 'register' ? 'Verify Your Registration' : 'Reset Your Password';
    $message = $type === 'register' 
        ? 'Thank you for signing up for StockSmart. Please use the verification code below to complete your registration:'
        : 'A password reset was requested for your StockSmart account. Please use the verification code below:';

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background-color: #f4f6fb; margin: 0; padding: 30px; color: #111827; }
    .container { max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 14px; padding: 36px; box-shadow: 0 10px 25px rgba(0,0,0,0.06); border: 1px solid #e5e7eb; }
    .header { text-align: center; margin-bottom: 28px; }
    .logo { font-size: 24px; font-weight: 800; color: #0f1b35; letter-spacing: -0.02em; }
    .logo span { color: #5b6ef5; }
    .title { font-size: 20px; font-weight: 700; color: #0f1b35; margin-top: 16px; margin-bottom: 8px; }
    .subtitle { font-size: 14px; color: #6b7a96; line-height: 1.6; }
    .otp-box { background: #f0f3ff; border: 2px dashed #5b6ef5; border-radius: 12px; padding: 20px; text-align: center; margin: 28px 0; }
    .otp-code { font-family: 'Courier New', monospace; font-size: 36px; font-weight: 800; color: #0f1b35; letter-spacing: 10px; margin: 0; }
    .expiry { font-size: 12px; color: #6b7a96; margin-top: 8px; }
    .footer { font-size: 12px; color: #9ca3af; text-align: center; margin-top: 28px; border-top: 1px solid #f3f4f6; padding-top: 20px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="logo">Stock<span>Smart</span></div>
      <div class="title">{$heading}</div>
      <p class="subtitle">Hi {$fullName},</p>
      <p class="subtitle">{$message}</p>
    </div>
    
    <div class="otp-box">
      <div class="otp-code">{$otpCode}</div>
      <div class="expiry">This OTP code is valid for 15 minutes.</div>
    </div>

    <p class="subtitle" style="font-size: 13px;">If you did not request this code, please ignore this email.</p>
    
    <div class="footer">
      &copy; StockSmart Inventory OS — Secure Authentication
    </div>
  </div>
</body>
</html>
HTML;
}

function mail_render_reset_link(string $fullName, string $resetUrl): string
{
    return "Hi {$fullName},\n\nA password reset was requested for your StockSmart account.\n"
        . "Reset your password using the link below (valid for 30 minutes):\n\n{$resetUrl}\n\n"
        . "If you did not request this, you can ignore this email.";
}
