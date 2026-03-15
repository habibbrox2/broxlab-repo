<?php
declare(strict_types=1);

/**
 * routes/web.php
 * ============================================================================
 * 
 * Consolidated Web Routes
 * 
 * All route definitions for the web application are consolidated here.
 * Individual controllers are required to register their routes with the Router.
 * 
 * This file serves as the central routing configuration point for:
 * - Frontend routes (user-facing pages)
 * - Admin routes (authenticated admin operations)
 * - API routes (RESTful endpoints)
 * - Firebase integration routes
 * 
 * Router instance ($router) is injected from public_html/index.php
 * Twig instance ($twig) is injected from public_html/index.php
 * Database ($mysqli) is injected from public_html/index.php
 * 
 * ============================================================================
 */

// Ensure router is available
if (!isset($router)) {
    throw new RuntimeException('Router not initialized. Routes must be loaded after router setup in public_html/index.php');
}

/**
 * ROUTE LOADING GUIDE
 * ==================
 * 
 * Route Registration Pattern:
 * --------------------------
 * $router->get('/path', ['middleware' => ['auth']], function() use ($twig, $mysqli) {
 *     // Handler code
 * });
 * 
 * Middleware Abbreviations:
 * - 'auth' - Requires authenticated user
 * - 'admin_only' - Requires admin role
 * - 'guest' - Requires unauthenticated user
 * - 'csrf' - Validates CSRF token
 * 
 * All routes are defined in app/Controllers/ directory files.
 * This file serves as documentation and ensures all routes are loaded properly.
 */

// ============================================================================
// ROUTE GROUPS & MIDDLEWARE
// ============================================================================

/**
 * Public Routes Group (all users)
 * Routes accessible without authentication
 */
$publicRoutes = [
    'GET /' => 'Home',
    'GET /about' => 'About',
    'GET /contact' => 'Contact form',
    'POST /contact' => 'Submit contact form',
    // Add public routes here
];

/**
 * Authenticated Routes Group
 * Routes requiring login
 */
$authRoutes = [
    'GET /dashboard' => 'User dashboard',
    'GET /profile' => 'User profile',
    'POST /profile' => 'Update profile',
    // Add auth routes here
];

/**
 * Admin Routes Group
 * Routes requiring admin role
 */
$adminRoutes = [
    'GET /admin' => 'Admin dashboard',
    'GET /admin/users' => 'User management',
    'GET /admin/content' => 'Content management',
    // Add admin routes here
];

/**
 * API Routes Group
 * RESTful API endpoints
 */
$apiRoutes = [
    'GET /api/v1/users' => 'List users',
    'POST /api/v1/users' => 'Create user',
    'GET /api/v1/users/:id' => 'Get user',
    // Add API routes here
];

/**
 * Firebase Integration Routes
 * Routes for Firebase operations
 */
$firebaseRoutes = [
    'POST /api/firebase/token' => 'Save FCM token',
    'POST /api/firebase/message' => 'Send message',
    // Add Firebase routes here
];

// ============================================================================
// DYNAMIC ROUTE LOADING
// ============================================================================

/**
 * Controllers automatically require route definitions via app/Controllers/*.php
 * when loaded in public_html/index.php
 * 
 * Individual controllers should register their routes like:
 * 
 * File: app/Controllers/HomeController.php
 * ----------------------------------------
 * $router->get('/', [...], function() { ... });
 * $router->get('/about', [...], function() { ... });
 */

// ============================================================================
// FALLBACK ROUTES
// ============================================================================

/**
 * 404 Not Found Handler
 * Handles all routes that don't match any registered pattern
 */
$router->any('.*', ['middleware' => []], function () use ($twig) {
    http_response_code(404);
    
    try {
        echo $twig->render('error.twig', [
            'code' => 404,
            'title' => 'Page Not Found',
            'message' => 'The page you are looking for does not exist.',
        ]);
    } catch (Throwable $e) {
        echo "404 - Page Not Found";
    }
    exit;
});

/**
 * Routes Documentation
 * ====================
 * 
 * To add new routes:
 * 
 * 1. Open the corresponding controller in app/Controllers/
 * 2. Add route registration code like:
 *    $router->get('/path', ['middleware' => [...]], function() { ... });
 * 3. Route will automatically load when controller is required in public_html/index.php
 * 
 * Route Naming:
 * - Use kebab-case for URL paths: /user-profile
 * - Use camelCase for handler names: getUserProfile()
 * - Use descriptive names: /admin/content-management
 * 
 * Best Practices:
 * - Group related routes in same controller
 * - Use middleware for access control
 * - Keep route definitions close to controller logic
 * - Document route purpose with inline comments
 * - Handle errors gracefully with try-catch
 * 
 * Security Considerations:
 * - Always validate and sanitize input
 * - Use prepared statements for database queries
 * - Escape output in templates
 * - Validate CSRF tokens for state-changing requests
 * - Check authentication and authorization before processing
 * 
 * Performance Tips:
 * - Cache template compilations
 * - Use database query caching where applicable
 * - Optimize database indexes for frequently searched columns
 * - Minimize external API calls
 */

// ============================================================================
// EOF: routes/web.php
// ============================================================================
