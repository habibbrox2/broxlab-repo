<?php

/**
 * ClientInfoHelper Class
 * Consolidated client information utilities
 */
class ClientInfoHelper
{
    /**
     * Get client IP address
     * @return string
     */
    public function getIp(): string
    {
        // Check for IP from a shared internet connection
        if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
            $ip = $_SERVER["HTTP_CLIENT_IP"];
        }

        // Check for IP passed from shared internet connection
        elseif (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            // Use the first IP if multiple IPs are present
            $ips = explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"]);
            $ip = trim($ips[0]);
        }

        // Check for remote address
        else {
            $ip = $_SERVER["REMOTE_ADDR"] ?? "UNKNOWN";
        }

        // Validate IP address
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        return "UNKNOWN";
    }

    /**
     * Get browser and OS information from user agent
     * @return string
     */
    public function getBrowser(): string
    {
        $user_agent = $_SERVER["HTTP_USER_AGENT"] ?? "UNKNOWN";

        if ($user_agent === "UNKNOWN") {
            return "UNKNOWN";
        }

        $browser = "UNKNOWN";
        $os = "UNKNOWN";
        $version = "";

        // Detect Browser
        if (preg_match("/MSIE (\d+)/i", $user_agent, $matches)) {
            $browser = "Internet Explorer";
            $version = $matches[1];
        } elseif (preg_match("/Trident.*rv:(\d+)/i", $user_agent, $matches)) {
            $browser = "Internet Explorer";
            $version = $matches[1];
        } elseif (preg_match("/Edge\/(\d+)/i", $user_agent, $matches)) {
            $browser = "Edge";
            $version = $matches[1];
        } elseif (preg_match("/Chrome\/(\d+)/i", $user_agent, $matches)) {
            $browser = "Chrome";
            $version = $matches[1];
        } elseif (preg_match("/Safari\/(\d+)/i", $user_agent, $matches)) {
            if (preg_match("/Version\/(\d+)/i", $user_agent, $vmatches)) {
                $browser = "Safari";
                $version = $vmatches[1];
            }
        } elseif (preg_match("/Firefox\/(\d+)/i", $user_agent, $matches)) {
            $browser = "Firefox";
            $version = $matches[1];
        } elseif (preg_match("/Opera.*Version\/(\d+)/i", $user_agent, $matches)) {
            $browser = "Opera";
            $version = $matches[1];
        }

        // Detect Operating System
        if (preg_match("/windows|win32|win64/i", $user_agent)) {
            if (preg_match("/Windows NT 10\.0/i", $user_agent)) {
                $os = "Windows 10/11";
            } elseif (preg_match("/Windows NT 6\.3/i", $user_agent)) {
                $os = "Windows 8.1";
            } elseif (preg_match("/Windows NT 6\.2/i", $user_agent)) {
                $os = "Windows 8";
            } elseif (preg_match("/Windows NT 6\.1/i", $user_agent)) {
                $os = "Windows 7";
            } else {
                $os = "Windows";
            }
        } elseif (preg_match("/macintosh|mac os x/i", $user_agent)) {
            $os = "macOS";
        } elseif (preg_match("/linux/i", $user_agent)) {
            $os = "Linux";
        } elseif (preg_match("/iphone|ipad|ipod/i", $user_agent)) {
            $os = "iOS";
        } elseif (preg_match("/android/i", $user_agent)) {
            $os = "Android";
        }

        // Build info string
        $info = $browser;
        if ($version) {
            $info .= " {$version}";
        }
        $info .= " ({$os})";

        return $info;
    }

    /**
     * Get user details (IP, UA, referer)
     * @return array
     */
    public function getDetails(): array
    {
        return [
            "ip_address" => $this->getIp(),
            "user_agent" => $_SERVER["HTTP_USER_AGENT"] ?? "Unknown",
            "referer" => $_SERVER["HTTP_REFERER"] ?? "Unknown",
        ];
    }
}

