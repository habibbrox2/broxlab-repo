<?php

/**
 * BreadcrumbGenerator Class
 * Consolidated breadcrumb generation for all page types
 */
class BreadcrumbGenerator
{
    private $configs;

    public function __construct()
    {
        $this->configs = [
            "post" => [
                "base" => [["label" => "Articles", "url" => "/posts", "icon" => "newspaper"]],
                "category" => ["label" => "%s", "url" => "/posts?category=%s", "icon" => "folder"],
                "item" => ["label" => "%s", "icon" => "file-text", "max_length" => 50],
            ],
            "page" => [
                "base" => [["label" => "Pages", "url" => "/pages", "icon" => "file-earmark"]],
                "item" => ["label" => "%s", "icon" => "file-text", "max_length" => 30],
            ],
            "mobile" => [
                "base" => [["label" => "Phones", "url" => "/mobiles", "icon" => "mobile"]],
                "brand" => ["label" => "%s", "url" => "/mobiles?brand=%s", "icon" => "smartphone"],
                "item" => ["label" => "%s", "icon" => "phone", "max_length" => 20],
            ],
            "category" => [
                "base" => [["label" => "Categories", "url" => "/", "icon" => "layers"]],
                "item" => ["label" => "%s", "icon" => "folder-open"],
            ],
            "tag" => [
                "base" => [["label" => "Tags", "url" => "/", "icon" => "tags"]],
                "item" => ["label" => "%s", "icon" => "tag"],
            ],
            "search" => [
                "base" => [["label" => "Search", "url" => "/search", "icon" => "search"]],
                "item" => ["label" => "%s", "icon" => "magnifying-glass", "max_length" => 30],
            ],
            "contact" => [
                "base" => [["label" => "Contact", "url" => "/contact", "icon" => "envelope"]],
            ],
            "about" => [
                "base" => [["label" => "About Us", "url" => "/about", "icon" => "info-circle"]],
            ],
        ];
    }

    /**
     * Generate breadcrumbs for a given type
     * @param string $type The breadcrumb type
     * @param array $params Parameters for the type
     * @return array
     */
    public function generate(string $type, array $params = []): array
    {
        if (!isset($this->configs[$type])) {
            return [];
        }

        $config = $this->configs[$type];
        $breadcrumbs = $config["base"] ?? [];

        // Handle category/brand
        $categoryKey = isset($config["category"]) ? "category" : (isset($config["brand"]) ? "brand" : null);
        if ($categoryKey && isset($params[$categoryKey])) {
            $crumb = $config[$categoryKey];
            $crumb["label"] = sprintf($crumb["label"], $params[$categoryKey]);
            $crumb["url"] = sprintf($crumb["url"], urlencode($params[$categoryKey]));
            $breadcrumbs[] = $crumb;
        }

        // Handle item
        if (isset($config["item"]) && isset($params["item"])) {
            $crumb = $config["item"];
            $label = $params["item"];
            if (isset($crumb["max_length"])) {
                $label = substr($label, 0, $crumb["max_length"]);
            }
            $crumb["label"] = $label;
            unset($crumb["max_length"]);
            $breadcrumbs[] = $crumb;
        }

        return $breadcrumbs;
    }

    /**
     * Generate admin breadcrumbs
     * @param string|null $page
     * @param string|null $subpage
     * @param mixed $item
     * @return array
     */
    public function generateAdmin(?string $page = null, ?string $subpage = null, $item = null): array
    {
        $breadcrumbs = [
            ["label" => "Admin Dashboard", "url" => "/admin/dashboard", "icon" => "speedometer2"],
        ];

        $pages = [
            "dashboard" => ["label" => "Dashboard", "url" => "/admin/dashboard", "icon" => "speedometer2"],
            "mobiles" => ["label" => "Mobiles", "url" => "/admin/mobiles/list", "icon" => "phone"],
            "posts" => ["label" => "Posts", "url" => "/admin/posts", "icon" => "journal-text"],
            "pages" => ["label" => "Pages", "url" => "/admin/pages", "icon" => "file-earmark-text"],
            "categories" => ["label" => "Categories", "url" => "/admin/categories", "icon" => "tags"],
            "tags" => ["label" => "Tags", "url" => "/admin/tags", "icon" => "hash"],
            "media" => ["label" => "Media Manager", "url" => "/admin/media", "icon" => "image"],
            "comments" => ["label" => "Comments", "url" => "/admin/comments", "icon" => "chat-left-text"],
            "users" => ["label" => "Users", "url" => "/admin/users", "icon" => "people"],
            "roles" => ["label" => "Roles", "url" => "/admin/roles", "icon" => "diagram-3"],
            "permissions" => ["label" => "Permissions", "url" => "/admin/permissions", "icon" => "shield-lock"],
            "contact" => ["label" => "Contact Messages", "url" => "/admin/contact", "icon" => "envelope"],
            "notifications" => ["label" => "Notifications", "url" => "/admin/notifications", "icon" => "bell-fill"],
            "email-templates" => ["label" => "Email Templates", "url" => "/admin/email-templates", "icon" => "file-earmark-text"],
            "settings" => ["label" => "Settings", "url" => "/admin/settings", "icon" => "gear"],
            "activity" => ["label" => "Activity Log", "url" => "/admin/log-activity", "icon" => "activity"],
            "profile" => ["label" => "Profile", "url" => "/admin/profile", "icon" => "person-circle"],
        ];

        if ($page && isset($pages[$page])) {
            $breadcrumbs[] = $pages[$page];
        }

        $subpages = [
            "list" => ["label" => "List", "icon" => "list-ul"],
            "create" => ["label" => "Create New", "icon" => "plus-circle"],
            "edit" => ["label" => "Edit", "icon" => "pencil-square"],
            "view" => ["label" => "View", "icon" => "eye"],
            "add" => ["label" => "Add New", "icon" => "plus-circle"],
            "delete" => ["label" => "Delete", "icon" => "trash"],
            "insert" => ["label" => "Insert New", "icon" => "plus-circle"],
            "send" => ["label" => "Send", "icon" => "send"],
            "drafts" => ["label" => "Drafts", "icon" => "file-earmark-text"],
            "analytics" => ["label" => "Analytics", "icon" => "graph-up"],
            "upload" => ["label" => "Upload", "icon" => "cloud-arrow-up"],
            "library" => ["label" => "Library", "icon" => "collection"],
            "detail" => ["label" => "Details", "icon" => "info-circle"],
            "security" => ["label" => "Security", "icon" => "shield-lock"],
            "2fa" => ["label" => "Two-Factor Auth", "icon" => "shield-check"],
            "password" => ["label" => "Change Password", "icon" => "key"],
            "user-roles" => ["label" => "User Roles", "icon" => "diagram-3"],
        ];

        if ($subpage && isset($subpages[$subpage])) {
            $breadcrumbs[] = $subpages[$subpage];
        }

        if ($item) {
            $breadcrumbs[] = [
                "label" => is_array($item) ? $item["label"] : substr($item, 0, 40),
                "icon" => is_array($item) ? ($item["icon"] ?? "file-earmark") : "file-earmark"
            ];
        }

        return $breadcrumbs;
    }

    /**
     * Auto-generate admin breadcrumbs from request URI
     * @param string|null $requestUri
     * @return array
     */
    public function generateAutoAdmin(?string $requestUri = null): array
    {
        // Simplified version, can be expanded
        $uri = $requestUri ?? ($_SERVER["REQUEST_URI"] ?? "/");
        $path = parse_url($uri, PHP_URL_PATH);
        $parts = array_values(array_filter(explode("/", $path), function($p){ return $p !== ""; }));

        $adminIndex = array_search("admin", $parts);
        if ($adminIndex === false) {
            return [];
        }

        $after = array_slice($parts, $adminIndex + 1);
        $page = $after[0] ?? null;
        $sub = $after[1] ?? null;
        $third = $after[2] ?? null;

        return $this->generateAdmin($page, $sub, $third ? ["label" => $third] : null);
    }

    /**
     * Sanitize breadcrumbs
     * @param array $breadcrumbs
     * @param string|null $baseUrl
     * @return array
     */
    public function sanitize(array $breadcrumbs, ?string $baseUrl = null): array
    {
        if (!$baseUrl) {
            $protocol = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off" ? "https" : "http";
            $host = $_SERVER["HTTP_HOST"] ?? "localhost";
            $baseUrl = $protocol . "://" . $host;
        }

        $sanitized = [];
        
        foreach ($breadcrumbs as $crumb) {
            if (empty($crumb["label"])) {
                continue;
            }

            if (!empty($crumb["url"]) && !filter_var($crumb["url"], FILTER_VALIDATE_URL)) {
                if (strpos($crumb["url"], "/") === 0 || strpos($crumb["url"], "?") === 0) {
                    $crumb["url"] = $baseUrl . $crumb["url"];
                }
            }

            $sanitized[] = $crumb;
        }

        return $sanitized;
    }
}

