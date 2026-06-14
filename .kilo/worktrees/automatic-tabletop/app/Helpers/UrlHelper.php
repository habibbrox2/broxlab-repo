<?php

/**
 * UrlHelper Class
 * Consolidated URL generation utilities
 */
class UrlHelper
{
    /**
     * Get canonical URL (SEO-safe: removes query params and fragments)
     * @return string
     */
    public function getCanonical(): string
    {
        $host = isset($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : "localhost";
        $scheme = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on" ? "https" : "http";
        $path = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "/";

        // Remove query string and fragment
        $path = strtok($path, "?#");

        // Remove trailing slash if not root
        $path = rtrim($path, "/");
        if (empty($path)) {
            $path = "/";
        }

        return $scheme . "://" . $host . $path;
    }

    /**
     * Get current full URL (including query params)
     * @return string
     */
    public function getCurrent(): string
    {
        $host = isset($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : "localhost";
        $scheme = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on" ? "https" : "http";
        $url = $scheme . "://" . $host . (isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "/");
        return $url;
    }

    /**
     * Get base app URL
     * @return string
     */
    public function getBase(): string
    {
        $appUrl = getenv("APP_URL");

        if ($appUrl) {
            return rtrim($appUrl, "/");
        }

        // Fallback: construct from HTTP_HOST
        $protocol = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https://" : "http://";
        $host = $_SERVER["HTTP_HOST"] ?? "localhost";
        return $protocol . $host;
    }
}

