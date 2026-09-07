<?php

namespace App\Support;

use App\Mail\HtmlMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Port of the legacy sendEmail() helper.
 *
 * Behaviour parity:
 *  - SMTP settings come from `app_settings` (smtp_host, smtp_username, ...).
 *  - If SMTP is not configured, the legacy code logged and skipped the send;
 *    here we fall back to the configured default mailer (usually `log` in
 *    local dev) so the pipeline is testable, and log a notice.
 */
class MailService
{
    public function __construct(
        protected AppSettings $appSettings,
        protected EmailTemplateService $templates,
    ) {}

    /**
     * Send a template-based email.
     */
    public function sendTemplate(
        string $templateSlug,
        string $toEmail,
        string $toName = '',
        array $variables = [],
        ?string $replyTo = null,
    ): bool {
        $rendered = $this->templates->render($templateSlug, $variables);

        if ($rendered['subject'] === '' && $rendered['body'] === '') {
            Log::warning("Email template not found: {$templateSlug}");

            return false;
        }

        return $this->send($toEmail, $rendered['subject'], $rendered['body'], $toName, $replyTo);
    }

    /**
     * Send a raw HTML email, using SMTP from app settings when available.
     */
    public function send(
        string $to,
        string $subject,
        string $htmlBody,
        string $toName = '',
        ?string $replyTo = null,
    ): bool {
        try {
            $settings = $this->appSettings->all();

            $smtpHost = (string) ($settings['smtp_host'] ?? '');
            $smtpUser = (string) ($settings['smtp_username'] ?? '');

            if ($smtpHost === '' || $smtpUser === '') {
                Log::notice("SMTP not configured. Falling back to default mailer (mail not sent externally) to: {$to}");

                return $this->sendWithDefaultMailer($to, $subject, $htmlBody, $toName, $replyTo);
            }

            // Configure the smtp mailer from app settings at runtime.
            $fromEmail = (string) ($settings['mail_from_address'] ?? '');
            if ($fromEmail === '') {
                $host = parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';
                $fromEmail = 'noreply@' . $host;
            }
            $fromName = (string) ($settings['mail_from_name'] ?? $settings['site_name'] ?? 'BroxLab');

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp' => [
                    'transport' => 'smtp',
                    'host' => $smtpHost,
                    'port' => (int) ($settings['smtp_port'] ?? 587),
                    'username' => $smtpUser,
                    'password' => (string) ($settings['smtp_password'] ?? ''),
                    'encryption' => strtolower((string) ($settings['smtp_encryption'] ?? 'tls')),
                    'timeout' => 10,
                ],
                'mail.from' => ['address' => $fromEmail, 'name' => $fromName],
            ]);

            return $this->sendWithDefaultMailer($to, $subject, $htmlBody, $toName, $replyTo);
        } catch (\Throwable $e) {
            Log::error('MailService::send failed: ' . $e->getMessage());

            return false;
        }
    }

    private function sendWithDefaultMailer(
        string $to,
        string $subject,
        string $htmlBody,
        string $toName = '',
        ?string $replyTo = null,
    ): bool {
        $mail = new HtmlMail($subject, $htmlBody);

        // Keep BC with the legacy helper API surface.
        $mail->subject = $subject;

        if ($replyTo) {
            $mail->replyTo($replyTo);
        }

        Mail::to($to, $toName)->send($mail);

        return true;
    }
}