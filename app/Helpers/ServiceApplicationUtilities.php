<?php
/**
 * helpers/ServiceApplicationUtilities.php
 * 
 * Utility functions for service application system
 */

if (!function_exists('slugify')) {
    /**
     * Convert string to URL-friendly slug
     * 
     * @param string $str Input string
     * @param string $separator Separator character (default: -)
     * @return string Slugified string
     */
    function slugify($str, $separator = '-') {
        $str = mb_strtolower($str, 'UTF-8');
        $str = preg_replace('/[^\p{L}\p{N}]+/u', $separator, $str);
        $str = trim($str, $separator);
        return $str;
    }
}

if (!function_exists('getClientIp')) {
    /**
     * Get client IP address
     * 
     * @return string IP address
     */
    function getClientIp() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }

        // Validate IP
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        return '0.0.0.0';
    }
}

if (!function_exists('getClientIP')) {
    /**
     * @deprecated Use getClientIp().
     */
    function getClientIP() {
        return getClientIp();
    }
}

if (!function_exists('sanitizeInput')) {
    /**
     * Sanitize user input
     * 
     * @param mixed $input Input to sanitize
     * @return mixed Sanitized input
     */
    function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map('sanitizeInput', $input);
        }

        if (!is_string($input)) {
            return $input;
        }

        // Remove HTML tags
        $input = strip_tags($input);
        
        // Trim whitespace
        $input = trim($input);
        
        // Remove special characters but keep common ones
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        return $input;
    }
}

if (!function_exists('validateCSRFToken')) {
    /**
     * Validate CSRF token
     * 
     * @param string $token Token to validate
     * @return bool True if valid
     */
    function validateCSRFToken($token) {
        // @deprecated Prefer validateCsrfToken() from Config/Functions.php.
        return validateCsrfToken($token);
    }
}

if (!function_exists('generateCSRFToken')) {
    /**
     * Generate and store CSRF token in session
     * CONSOLIDATED: Delegates to centralized generateCsrfToken in Functions.php
     * which in turn uses SessionManager
     * 
     * @return string Generated token
     */
    function generateCSRFToken() {
        // @deprecated Prefer generateCsrfToken() from Config/Functions.php.
        return generateCsrfToken();
    }
}

if (!function_exists('isValidEmail')) {
    /**
     * Validate email address
     * 
     * @param string $email Email to validate
     * @return bool True if valid
     */
    function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('isValidPhoneNumber')) {
    /**
     * Validate phone number (basic)
     * 
     * @param string $phone Phone number to validate
     * @return bool True if valid
     */
    function isValidPhoneNumber($phone) {
        // Remove non-digit characters
        $digits = preg_replace('/\D/', '', $phone);

        // Check if between 7 and 15 digits
        return strlen($digits) >= 7 && strlen($digits) <= 15;
    }
}
