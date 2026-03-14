<?php
declare(strict_types = 1)
;

namespace App\Modules\SmsGateway;

/**
 * SmsConfig.php
 * Module-specific configuration for the SMS Gateway.
 */
class SmsConfig
{
    public const MAX_SMS_PER_REQUEST = 5;
    public const RETRY_ATTEMPTS = 3;
    public const ALLOWED_SENDER_PATTERNS = ['*']; // Regex or list
}
