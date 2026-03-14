<?php
/**
 * Email Sending Helper
 * ====================
 * Helper functions for sending emails using templates
 */

require_once __DIR__ . '/../Models/EmailTemplate.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send email using template
 */
function sendTemplateEmail(
    mysqli $mysqli,
    string $templateSlug,
    string $toEmail,
    string $toName = '',
    array $variables = [],
    ?string $replyTo = null
): bool {
    // Get template
    $emailTemplate = new EmailTemplate($mysqli);
    $template = $emailTemplate->getBySlug($templateSlug);

    if (!$template) {
        error_log("Email template not found: $templateSlug");
        return false;
    }

    // Render subject and body
    $subject = $emailTemplate->renderSubject($templateSlug, $variables);
    $body = $emailTemplate->render($templateSlug, $variables);

    // Send email
    return sendEmail($toEmail, $subject, $body, $toName, $replyTo, $templateSlug);
}

/**
 * Send raw email
 */
function sendEmail(
    string $to,
    string $subject,
    string $htmlBody,
    string $toName = '',
    ?string $replyTo = null,
    ?string $templateSlug = null
): bool {
    try {
        // Load app settings
        $appSettings = new AppSettings($GLOBALS['mysqli'] ?? null);
        $settings = $appSettings->getAll();

        // Check if SMTP is configured
        if (empty($settings['smtp_host']) || empty($settings['smtp_username'])) {
            error_log("SMTP not configured. Email not sent to: $to");
            return false;
        }

        // Initialize PHPMailer
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host = $settings['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['smtp_username'];
        $mail->Password = $settings['smtp_password'];
        $mail->SMTPSecure = strtolower($settings['smtp_encryption'] ?? 'tls');
        $mail->Port = (int)($settings['smtp_port'] ?? 587);
        $mail->Timeout = 10;

        // Recipients
        $fromEmail = $settings['mail_from_address'] ?? null;
        $fromName = $settings['mail_from_name'] ?? 'BroxBhai';
        
        // If mail_from_address not set, derive from APP_URL
        if (!$fromEmail) {
            $appUrl = getenv('APP_URL') ?: 'http://localhost';
            $parsedUrl = parse_url($appUrl);
            $host = $parsedUrl['host'] ?? 'localhost';
            $fromEmail = 'noreply@' . $host;
        }
        
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to, $toName);

        if ($replyTo) {
            $mail->addReplyTo($replyTo);
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        // Send
        $mail->send();

        $templateInfo = $templateSlug ? " (Template: $templateSlug)" : '';
        error_log("Email sent successfully to: $to$templateInfo");
        return true;

    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send welcome email
 */
function sendWelcomeEmail(mysqli $mysqli, string $email, string $name, string $verifyLink): bool {
    return sendTemplateEmail(
        $mysqli,
        'welcome_email',
        $email,
        $name,
        [
            'APP_NAME' => 'BroxBhai',
            'USER_NAME' => $name,
            'USER_EMAIL' => $email,
            'VERIFY_LINK' => $verifyLink
        ]
    );
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail(mysqli $mysqli, string $email, string $name, string $resetLink, int $expiryMinutes = 60): bool {
    return sendTemplateEmail(
        $mysqli,
        'password_reset',
        $email,
        $name,
        [
            'APP_NAME' => 'BroxBhai',
            'USER_NAME' => $name,
            'USER_EMAIL' => $email,
            'RESET_LINK' => $resetLink,
            'EXPIRY_TIME' => "$expiryMinutes minutes"
        ]
    );
}

/**
 * Send email verification email
 */
function sendEmailVerificationEmail(mysqli $mysqli, string $email, string $name, string $verifyLink, int $expiryMinutes = 24*60): bool {
    return sendTemplateEmail(
        $mysqli,
        'email_verification',
        $email,
        $name,
        [
            'APP_NAME' => 'BroxBhai',
            'USER_NAME' => $name,
            'USER_EMAIL' => $email,
            'VERIFY_LINK' => $verifyLink,
            'EXPIRY_TIME' => "$expiryMinutes minutes"
        ]
    );
}

/**
 * Send account locked email
 */
/**
 * Send admin notification for new user registration
 */
/**
 * Test email configuration
 */



if (!function_exists('getMailer')) {

    /**

     * Return a configured PHPMailer instance using settings (database/.env)

     * Supports Outlook.com (STARTTLS), App Passwords, and localhost fallback.

     *

     * @return \PHPMailer\PHPMailer\PHPMailer

     */

    function getMailer(): \PHPMailer\PHPMailer\PHPMailer {

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);



        try {

            // -----------------------------

            // SMTP Configuration from Settings

            // -----------------------------

            $smtpHost = getSetting('smtp_host', '127.0.0.1');

            $smtpPort = (int) getSetting('smtp_port', 587);

            $smtpUser = getSetting('smtp_username', '');

            $smtpPass = getSetting('smtp_password', '');

            $smtpEnc  = getSetting('smtp_encryption', 'tls'); // default tls



            // Enable SMTP if host is provided

            if (!empty($smtpHost)) {

                $mail->isSMTP();

                $mail->Host       = $smtpHost;

                $mail->Port       = $smtpPort;



                // Enable authentication if username provided

                if (!empty($smtpUser)) {

                    $mail->SMTPAuth   = true;

                    $mail->Username   = $smtpUser;

                    $mail->Password   = $smtpPass;

                } else {

                    $mail->SMTPAuth   = false;

                }



                // Determine encryption

                if (!empty($smtpEnc)) {

                    $mail->SMTPSecure = strtolower($smtpEnc); // 'tls' or 'ssl'

                } else if ($smtpPort == 587) {

                    $mail->SMTPSecure = 'tls'; // Outlook default

                }



                // Recommended options for secure SMTP

                $mail->SMTPAutoTLS = true;

                $mail->SMTPOptions = [

                    'ssl' => [

                        'verify_peer'       => false,

                        'verify_peer_name'  => false,

                        'allow_self_signed' => true

                    ]

                ];



                // Timeout

                $mail->Timeout = 30;

            }



            // -----------------------------

            // From Address

            // -----------------------------

            $fromAddress = getSetting('mail_from_address', $smtpUser);

            $fromName    = getSetting('mail_from_name', getSetting('site_name', 'Admin'));



            if (!empty($fromAddress)) {

                $mail->setFrom($fromAddress, $fromName);

            }



            // -----------------------------

            // PHPMailer Defaults

            // -----------------------------

            $mail->CharSet = 'UTF-8';

            $mail->isHTML(true);



            // -----------------------------

            // Safe Localhost Fallback

            // -----------------------------

            if ($smtpHost === '127.0.0.1' || $smtpHost === 'localhost') {

                $mail->isMail(); // Use PHP mail() if SMTP not configured

            }



        } catch (Exception $e) {

            error_log('getMailer error: ' . $e->getMessage());

        }



        return $mail;

    }

}



if (!function_exists('sendMail')) {

    /**

     * Helper to send email via PHPMailer using app settings

     * @param string $to

     * @param string $subject

     * @param string $body

     * @param string|null $from

     * @param string|null $fromName

     * @return bool

     */

    function sendMail(string $to, string $subject, string $body, ?string $from = null, ?string $fromName = null): bool {

        $mail = getMailer();



        try {

            if ($from) {

                $mail->setFrom($from, $fromName ?? '');

            }



            $mail->addAddress($to);

            $mail->Subject = $subject;

            $mail->Body = $body;



            return $mail->send();

        } catch (Exception $e) {

            error_log('sendMail error: ' . $e->getMessage());

            return false;

        }

    }

}
