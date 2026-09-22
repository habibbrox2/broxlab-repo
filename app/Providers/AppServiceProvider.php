<?php

namespace App\Providers;

use App\Auth\LegacySessionGuard;
use App\Support\AppSettings;
use App\Support\HeaderNavService;
use App\Support\I18n\LanguageService;
use App\Support\I18n\Translator;
use App\Support\UserProfileService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\View\View as IlluminateView;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AppSettings::class);

        // i18n: singletons so the active language is detected once per request
        // (t() is called 800+ times per page) and dictionaries are read once.
        $this->app->singleton(LanguageService::class);
        $this->app->singleton(Translator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Custom guard reading the shared legacy native PHP session.
        Auth::extend('legacy-session', function ($app, $name, array $config) {
            $provider = $app['auth']->createUserProvider($config['provider'] ?? null);

            return new LegacySessionGuard($provider, $app['request']);
        });
        // @assetVersion('/assets/css/dist/tailwind-public.css') — mirrors
        // the legacy withAssetVersion() Twig helper.
        Blade::directive('assetVersion', function (string $expression) {
            return "<?php echo assetVersion($expression); ?>";
        });


        // ── Rate limiting (Phase 7 cache/rate-limiting review) ────────────────────
        // Conservative per-IP limits on the auth endpoints that are exposed to
        // anonymous traffic. Password-reset token generation already has a built-in
        // 60 s broker throttle (config/auth.php passwords.users.throttle).
        $this->configureRateLimiting();

        // Legacy Twig exposed `app_settings` to every template — same here.
        View::share('appSettings', $this->app->make(AppSettings::class)->all());

        // Share legacy-equivalent UI globals across all views (header/footer/breadcrumb parity).
        // $sharedTranslations is resolved once per request — the composer runs for
        // every view AND partial, so recomputing two dictionaries each time would
        // be wasteful.
        $sharedTranslations = null;

        // RBAC globals (isAdmin / isSuperAdmin / authRoles), cached per USER ID
        // rather than per request. A plain `$rbac = null` memo would be correct on
        // a classic one-process-per-request setup but would leak one user's admin
        // flags to the next request under a long-lived worker (Octane/Swoole) —
        // an admin-only escalation. Keying by id keeps it to one query per user
        // per process and is safe either way. The `users` table has no `role` or
        // `is_super_admin` column, so admin-ness comes from the roles/user_roles
        // tables only.
        $rbacByUser = [];

        View::composer('*', function (IlluminateView $view) use (&$sharedTranslations, &$rbacByUser) {
            $appSettings = $this->app->make(AppSettings::class)->all();

            $authUser = auth()->user();
            $userId = (int) ($authUser?->id ?? 0);

            $rbac = $rbacByUser[$userId] ??= $this->app->make(UserProfileService::class)->rbacFor($userId ?: null);

            $view->with('authUser', $authUser);
            $view->with('isAuthenticated', auth()->check());
            $view->with('isSuperAdmin', $rbac['is_super_admin']);
            $view->with('isAdmin', $rbac['is_admin']);
            $view->with('authRoles', $rbac['roles']);
            $view->with('canonicalUrl', request()->url());
            // Admin chrome preference: per-user sidebar width (null-safe —
            // guests get null and the layout falls back to localStorage/default).
            $view->with('sidebarWidth', $authUser?->admin_sidebar_width);

            if ($sharedTranslations === null) {
                $translator = $this->app->make(Translator::class);
                $sharedTranslations = [
                    'en' => $translator->dictionary('en'),
                    'bn' => $translator->dictionary('bn'),
                ];
            }

            $view->with('siteTranslations', $sharedTranslations);
            $view->with('availableLanguages', $this->app->make(LanguageService::class)->available());

            // Public nav items: the HeaderNavService merges any admin-persisted
            // overrides (stored on app_settings.header_nav_items) over the
            // built-in defaults, then filters hidden items and sorts by order.
            // Share the resolved list as $publicNavItems (which the header uses
            // via `??`) and the raw defaults as a fallback so the header never
            // renders an empty menu even if the service is unavailable.
            $headerNav = $this->app->make(HeaderNavService::class);
            $view->with('publicNavItems', $headerNav->configured());
            $view->with('headerNavDefaults', $headerNav->defaults());
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Login: 5 attempts per minute per IP. Conservative — keeps the legacy
        // login path (shared session, no legacy-side throttle) protected on the
        // Laravel side without locking out legitimate admins behind a single NAT.
        RateLimiter::for('login', function ($request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Registration: 3 attempts per minute per IP. Low because registration
        // is write-heavy (users row + user_roles + verification token + activity log).
        RateLimiter::for('register', function ($request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // Forgot password: 3 requests per minute per email address. Prevents
        // enumeration spam while still allowing a legitimate user to retry.
        RateLimiter::for('forgot-password', function ($request) {
            return Limit::perMinute(3)->by($request->input('email', $request->ip()));
        });

        // Resend verification email: 3 requests per minute per email address.
        // Same enumeration concern as forgot-password.
        RateLimiter::for('resend-verification', function ($request) {
            return Limit::perMinute(3)->by($request->input('email', $request->ip()));
        });
    }
}