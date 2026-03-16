<?php
/**
 * Breadcrumb Helper Functions
 * Generates SEO-friendly breadcrumbs for different page types
 */

/**
 * Generate breadcrumbs for posts
 */
function getBreadcrumbsForPost($category = null, $postTitle = null) {
    $breadcrumbs = [
        ['label' => 'Articles', 'url' => '/posts', 'icon' => 'newspaper'],
    ];
    
    if ($category) {
        $breadcrumbs[] = ['label' => $category, 'url' => '/posts?category=' . urlencode($category), 'icon' => 'folder'];
    }
    
    if ($postTitle) {
        $breadcrumbs[] = ['label' => substr($postTitle, 0, 50), 'icon' => 'file-text'];
    }
    
    return $breadcrumbs;
}

/**
 * Generate breadcrumbs for pages
 */
function getBreadcrumbsForPage($pageTitle = null) {
    $breadcrumbs = [
        ['label' => 'Pages', 'url' => '/pages', 'icon' => 'file-earmark'],
    ];
    
    if ($pageTitle) {
        $breadcrumbs[] = ['label' => substr($pageTitle, 0, 30), 'icon' => 'file-text'];
    }
    
    return $breadcrumbs;
}

/**
 * Generate breadcrumbs for mobiles
 */
function getBreadcrumbsForMobile($brand = null, $model = null) {
    $breadcrumbs = [
        ['label' => 'Phones', 'url' => '/mobiles', 'icon' => 'mobile'],
    ];
    
    if ($brand) {
        $breadcrumbs[] = ['label' => $brand, 'url' => '/mobiles?brand=' . urlencode($brand), 'icon' => 'smartphone'];
    }
    
    if ($model) {
        $breadcrumbs[] = ['label' => substr($model, 0, 20), 'icon' => 'phone'];
    }
    
    return $breadcrumbs;
}

/**
 * Generate breadcrumbs for categories
 */
function getBreadcrumbsForCategory($categoryName = null) {
    $breadcrumbs = [
        ['label' => 'Categories', 'url' => '/', 'icon' => 'layers'],
    ];
    
    if ($categoryName) {
        $breadcrumbs[] = ['label' => $categoryName, 'icon' => 'folder-open'];
    }
    
    return $breadcrumbs;
}

/**
 * Generate breadcrumbs for tags
 */
function getBreadcrumbsForTag($tagName = null) {
    $breadcrumbs = [
        ['label' => 'Tags', 'url' => '/', 'icon' => 'tags'],
    ];
    
    if ($tagName) {
        $breadcrumbs[] = ['label' => $tagName, 'icon' => 'tag'];
    }
    
    return $breadcrumbs;
}

/**
 * Generate breadcrumbs for search
 */
function getBreadcrumbsForSearch($query = null) {
    $breadcrumbs = [
        ['label' => 'Search', 'url' => '/search', 'icon' => 'search'],
    ];
    
    if ($query) {
        $breadcrumbs[] = ['label' => substr($query, 0, 30), 'icon' => 'magnifying-glass'];
    }
    
    return $breadcrumbs;
}

/**
 * Generate breadcrumbs for contact
 */
function getBreadcrumbsForContact() {
    return [
        ['label' => 'Contact', 'url' => '/contact', 'icon' => 'envelope'],
    ];
}

/**
 * Generate breadcrumbs for about
 */
function getBreadcrumbsForAbout() {
    return [
        ['label' => 'About Us', 'url' => '/about', 'icon' => 'info-circle'],
    ];
}

// ============================================================================
// ADMIN PANEL BREADCRUMBS
// ============================================================================

/**
 * Generate breadcrumbs for admin pages
 */
function getAdminBreadcrumbs($page = null, $subpage = null, $item = null) {
    $breadcrumbs = [
        ['label' => 'Admin Dashboard', 'url' => '/admin/dashboard', 'icon' => 'speedometer2'],
    ];

    // Map of pages with their properties
    $pages = [
        'dashboard' => ['label' => 'Dashboard', 'url' => '/admin/dashboard', 'icon' => 'speedometer2'],
        'mobiles' => ['label' => 'Mobiles', 'url' => '/admin/mobiles/list', 'icon' => 'phone'],
        'posts' => ['label' => 'Posts', 'url' => '/admin/posts', 'icon' => 'journal-text'],
        'pages' => ['label' => 'Pages', 'url' => '/admin/pages', 'icon' => 'file-earmark-text'],
        'categories' => ['label' => 'Categories', 'url' => '/admin/categories', 'icon' => 'tags'],
        'tags' => ['label' => 'Tags', 'url' => '/admin/tags', 'icon' => 'hash'],
        'media' => ['label' => 'Media Manager', 'url' => '/admin/media', 'icon' => 'image'],
        'comments' => ['label' => 'Comments', 'url' => '/admin/comments', 'icon' => 'chat-left-text'],
        'users' => ['label' => 'Users', 'url' => '/admin/users', 'icon' => 'people'],
        'roles' => ['label' => 'Roles', 'url' => '/admin/roles', 'icon' => 'diagram-3'],
        'permissions' => ['label' => 'Permissions', 'url' => '/admin/permissions', 'icon' => 'shield-lock'],
        'contact' => ['label' => 'Contact Messages', 'url' => '/admin/contact', 'icon' => 'envelope'],
        'notifications' => ['label' => 'Notifications', 'url' => '/admin/notifications', 'icon' => 'bell-fill'],
        'email-templates' => ['label' => 'Email Templates', 'url' => '/admin/email-templates', 'icon' => 'file-earmark-text'],
        'settings' => ['label' => 'Settings', 'url' => '/admin/settings', 'icon' => 'gear'],
        'activity' => ['label' => 'Activity Log', 'url' => '/admin/log-activity', 'icon' => 'activity'],
        'profile' => ['label' => 'Profile', 'url' => '/admin/profile', 'icon' => 'person-circle'],
    ];

    // Add main page if exists
    if ($page && isset($pages[$page])) {
        $breadcrumbs[] = $pages[$page];
    }

    // Map of subpages
    $subpages = [
        'list' => ['label' => 'List', 'icon' => 'list-ul'],
        'create' => ['label' => 'Create New', 'icon' => 'plus-circle'],
        'edit' => ['label' => 'Edit', 'icon' => 'pencil-square'],
        'view' => ['label' => 'View', 'icon' => 'eye'],
        'add' => ['label' => 'Add New', 'icon' => 'plus-circle'],
        'delete' => ['label' => 'Delete', 'icon' => 'trash'],
        'insert' => ['label' => 'Insert New', 'icon' => 'plus-circle'],
        'send' => ['label' => 'Send', 'icon' => 'send'],
        'drafts' => ['label' => 'Drafts', 'icon' => 'file-earmark-text'],
        'analytics' => ['label' => 'Analytics', 'icon' => 'graph-up'],
        'upload' => ['label' => 'Upload', 'icon' => 'cloud-arrow-up'],
        'library' => ['label' => 'Library', 'icon' => 'collection'],
        'detail' => ['label' => 'Details', 'icon' => 'info-circle'],
        'detail' => ['label' => 'Details', 'icon' => 'info-circle'],
        'security' => ['label' => 'Security', 'icon' => 'shield-lock'],
        '2fa' => ['label' => 'Two-Factor Auth', 'icon' => 'shield-check'],
        'password' => ['label' => 'Change Password', 'icon' => 'key'],
        'user-roles' => ['label' => 'User Roles', 'icon' => 'diagram-3'],
    ];

    // Add subpage if exists
    if ($subpage && isset($subpages[$subpage])) {
        $breadcrumbs[] = $subpages[$subpage];
    }

    // Add specific item if provided
    if ($item) {
        $breadcrumbs[] = [
            'label' => is_array($item) ? $item['label'] : substr($item, 0, 40),
            'icon' => is_array($item) ? ($item['icon'] ?? 'file-earmark') : 'file-earmark'
        ];
    }

    return $breadcrumbs;
}

/**
 * Auto-generate admin breadcrumbs from the current request URI
 * Tries to resolve resource names when possible (fetches model by ID)
 */
function autoAdminBreadcrumbs(string $requestUri = null): array {
    $uri = $requestUri ?? ($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($uri, PHP_URL_PATH);
    $parts = array_values(array_filter(explode('/', $path), function($p){ return $p !== ''; }));

    // Find 'admin' segment
    $adminIndex = array_search('admin', $parts);
    if ($adminIndex === false) {
        return [];
    }

    $after = array_slice($parts, $adminIndex + 1);
    $page = $after[0] ?? null;
    $sub = $after[1] ?? null;
    $third = $after[2] ?? null;

    // Base breadcrumbs
    $breadcrumbs = [
        ['label' => 'Admin Dashboard', 'url' => '/admin/dashboard', 'icon' => 'speedometer2']
    ];

    // Reuse the pages map from getAdminBreadcrumbs
    $pages = [
        'dashboard' => ['label' => 'Dashboard', 'url' => '/admin/dashboard', 'icon' => 'speedometer2'],
        'mobiles' => ['label' => 'Mobiles', 'url' => '/admin/mobiles/list', 'icon' => 'phone'],
        'posts' => ['label' => 'Posts', 'url' => '/admin/posts', 'icon' => 'journal-text'],
        'pages' => ['label' => 'Pages', 'url' => '/admin/pages', 'icon' => 'file-earmark-text'],
        'categories' => ['label' => 'Categories', 'url' => '/admin/categories', 'icon' => 'tags'],
        'tags' => ['label' => 'Tags', 'url' => '/admin/tags', 'icon' => 'hash'],
        'media' => ['label' => 'Media Manager', 'url' => '/admin/media', 'icon' => 'image'],
        'comments' => ['label' => 'Comments', 'url' => '/admin/comments', 'icon' => 'chat-left-text'],
        'users' => ['label' => 'Users', 'url' => '/admin/users', 'icon' => 'people'],
        'roles' => ['label' => 'Roles', 'url' => '/admin/roles', 'icon' => 'diagram-3'],
        'permissions' => ['label' => 'Permissions', 'url' => '/admin/permissions', 'icon' => 'shield-lock'],
        'contact' => ['label' => 'Contact Messages', 'url' => '/admin/contact', 'icon' => 'envelope'],
        'notifications' => ['label' => 'Notifications', 'url' => '/admin/notifications', 'icon' => 'bell-fill'],
        'email-templates' => ['label' => 'Email Templates', 'url' => '/admin/email-templates', 'icon' => 'file-earmark-text'],
        'settings' => ['label' => 'Settings', 'url' => '/admin/settings', 'icon' => 'gear'],
        'activity' => ['label' => 'Activity Log', 'url' => '/admin/log-activity', 'icon' => 'activity'],
        'profile' => ['label' => 'Profile', 'url' => '/admin/profile', 'icon' => 'person-circle'],
    ];

    if ($page && isset($pages[$page])) {
        $breadcrumbs[] = $pages[$page];
    } elseif ($page) {
        $breadcrumbs[] = ['label' => ucwords(str_replace(['-','_'], ' ', $page)), 'url' => '/admin/' . $page];
    }

    // subpage handling
    $subpages = [
        'list' => ['label' => 'List', 'icon' => 'list-ul'],
        'create' => ['label' => 'Create New', 'icon' => 'plus-circle'],
        'edit' => ['label' => 'Edit', 'icon' => 'pencil-square'],
        'view' => ['label' => 'View', 'icon' => 'eye'],
        'add' => ['label' => 'Add New', 'icon' => 'plus-circle'],
        'delete' => ['label' => 'Delete', 'icon' => 'trash'],
        'insert' => ['label' => 'Insert New', 'icon' => 'plus-circle'],
        'send' => ['label' => 'Send', 'icon' => 'send'],
        'drafts' => ['label' => 'Drafts', 'icon' => 'file-earmark-text'],
        'analytics' => ['label' => 'Analytics', 'icon' => 'graph-up'],
        'upload' => ['label' => 'Upload', 'icon' => 'cloud-arrow-up'],
        'library' => ['label' => 'Library', 'icon' => 'collection'],
        'detail' => ['label' => 'Details', 'icon' => 'info-circle'],
        'security' => ['label' => 'Security', 'icon' => 'shield-lock'],
        '2fa' => ['label' => 'Two-Factor Auth', 'icon' => 'shield-check'],
        'password' => ['label' => 'Change Password', 'icon' => 'key'],
        'user-roles' => ['label' => 'User Roles', 'icon' => 'diagram-3'],
    ];

    // Attempt-to-resolve map for resource item labels
    $resolvers = [
        'services' => ['class' => 'ServiceModel', 'method' => 'findById', 'field' => 'name'],
        'posts' => ['class' => 'ContentModel', 'method' => 'getPostById', 'field' => 'title'],
        'pages' => ['class' => 'ContentModel', 'method' => 'getPageById', 'field' => 'title'],
        'media' => ['class' => 'MediaModel', 'method' => 'getById', 'field' => 'filename'],
        'email-templates' => ['class' => 'EmailTemplate', 'method' => 'getById', 'field' => 'name'],
        'categories' => ['class' => 'ContentModel', 'method' => 'getCategoryById', 'field' => 'name'],
        'tags' => ['class' => 'ContentModel', 'method' => 'getTagById', 'field' => 'name'],
        'users' => ['class' => 'UserModel', 'method' => 'findById', 'field' => 'username'],
        'roles' => ['class' => 'RoleModel', 'method' => 'getById', 'field' => 'name'],
        'permissions' => ['class' => 'PermissionModel', 'method' => 'getById', 'field' => 'name'],
    ];

    // If numeric second segment, treat as item id
    if ($sub && is_numeric($sub)) {
        $id = (int)$sub;
        $label = null;

        if ($page && isset($resolvers[$page])) {
            $r = $resolvers[$page];
            try {
                if (class_exists($r['class'])) {
                    $model = new $r['class']($GLOBALS['mysqli'] ?? null);
                    if (method_exists($model, $r['method'])) {
                        $obj = $model->{$r['method']}($id);
                        if (is_array($obj) && !empty($obj[$r['field']])) {
                            $label = $obj[$r['field']];
                        }
                    }
                }
            } catch (Throwable $e) {
                // ignore resolver failures
            }
        }

        $breadcrumbs[] = ['label' => $label ?? ('#' . $id), 'icon' => 'info-circle'];

        // If third segment exists like 'edit' add it
        if ($third) {
            $breadcrumbs[] = ['label' => ucfirst($third), 'icon' => (isset($subpages[$third]) ? $subpages[$third]['icon'] : 'pencil-square')];
        }

        return $breadcrumbs;
    }

    // Non-numeric subpage
    if ($sub && isset($subpages[$sub])) {
        $breadcrumbs[] = $subpages[$sub];

        // If third segment numeric, try to resolve the item and append
        if ($third && is_numeric($third) && $page) {
            $id = (int)$third;
            $label = null;
            if (isset($resolvers[$page])) {
                $r = $resolvers[$page];
                try {
                    if (class_exists($r['class'])) {
                        $model = new $r['class']($GLOBALS['mysqli'] ?? null);
                        if (method_exists($model, $r['method'])) {
                            $obj = $model->{$r['method']}($id);
                            if (is_array($obj) && !empty($obj[$r['field']])) {
                                $label = $obj[$r['field']];
                            }
                        }
                    }
                } catch (Throwable $e) {
                }
            }
            $breadcrumbs[] = ['label' => $label ?? ('#' . $id), 'icon' => 'info-circle'];
        }

        return $breadcrumbs;
    }

    // If no sub or unknown sub, just return the page breadcrumb
    return $breadcrumbs;
}

/**
 * USAGE EXAMPLES
 * -----------------
 * 1) Controller: render with auto breadcrumbs (recommended)
 *
 *    // In your controller action
 *    echo $twig->render('admin/services/forms.twig', [
 *        'service'     => $service,
 *        // optional explicit override (same as auto)
 *        'breadcrumbs' => autoAdminBreadcrumbs()
 *    ]);
 *
 * 2) Template override: set custom breadcrumbs in Twig
 *
 *    {# templates/admin/services/forms.twig #}
 *    {% set breadcrumbs = [
 *      { label: 'Services', url: '/admin/services', icon: 'gear' },
 *      { label: service.name, url: '/admin/services/' ~ service.id ~ '/edit', icon: 'info-circle' },
 *      { label: 'Edit', icon: 'pencil-square' }
 *    ] %}
 *    {% include 'admin/partials/breadcrumb.twig' %}
 *
 * 3) Add a pages entry for nicer label/icon (optional)
 *
 *    // Inside getAdminBreadcrumbs() $pages array:
 *    'myresource' => ['label' => 'My Resources', 'url' => '/admin/myresource', 'icon' => 'box-seam'],
 *
 * 4) Add a resolver to show item name for URLs like /admin/myresource/123/edit
 *
 *    // Inside autoAdminBreadcrumbs() $resolvers array:
 *    'myresource' => ['class' => 'MyResourceModel', 'method' => 'findById', 'field' => 'name'],
 *
 * 5) Example final breadcrumb output (what the template gets):
 *
 *    [
 *      {label: 'Admin Dashboard', url: '/admin/dashboard', icon: 'speedometer2'},
 *      {label: 'My Resources', url: '/admin/myresource', icon: 'box-seam'},
 *      {label: 'My Item Name', icon: 'info-circle'},
 *      {label: 'Edit', icon: 'pencil-square'}
 *    ]
 *
 * Notes:
 * - If you don't add a pages entry, autoAdminBreadcrumbs will create a fallback label from the URL
 *   (e.g., 'myresource' -> 'Myresource').
 * - Resolvers require the model class to exist and the specified method to return an array with the
 *   field name specified (e.g., ['name' => 'My Item']). Failures are silently ignored and the id
 *   (e.g., '#123') will be shown instead.
 */

// ============================================================================
// BREADCRUMB VALIDATION & SANITIZATION
// ============================================================================

/**
 * Sanitize breadcrumbs to ensure all URLs are valid for JSON-LD schema validation
 * - Removes breadcrumb items without URLs
 * - Converts relative URLs to absolute URLs if base_url provided
 * 
 * @param array $breadcrumbs The breadcrumb array
 * @param string $baseUrl The base URL (e.g., 'https://example.com')
 * @return array Sanitized breadcrumbs
 */
function sanitizeBreadcrumbs(array $breadcrumbs, string $baseUrl = null): array {
    // Get base URL from server if not provided
    if (!$baseUrl) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;
    }

    $sanitized = [];
    
    foreach ($breadcrumbs as $crumb) {
        // Skip items without URLs or labels
        if (empty($crumb['label']) || empty($crumb['url'])) {
            continue;
        }

        // Convert relative URLs to absolute URLs
        if (!empty($crumb['url']) && !filter_var($crumb['url'], FILTER_VALIDATE_URL)) {
            // Only convert if it looks like a relative path
            if (strpos($crumb['url'], '/') === 0 || strpos($crumb['url'], '?') === 0) {
                $crumb['url'] = $baseUrl . $crumb['url'];
            }
        }

        $sanitized[] = $crumb;
    }

    return $sanitized;
}

/**
 * Wrapper function to sanitize breadcrumbs in templates
 * Usage in Twig: {% set breadcrumbs = breadcrumbs|sanitize_breadcrumbs %}
 */
function sanitizeBreadcrumbsForTwig(array $breadcrumbs): array {
    return sanitizeBreadcrumbs($breadcrumbs);
}


